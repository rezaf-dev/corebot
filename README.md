# corebot

Open-source, multi-tenant **CRM AI support bot** — Laravel 13, Inertia React, PostgreSQL + **pgvector**, and a vanilla JavaScript chat widget. Each tenant uses their own OpenAI key, uploads approved CRM knowledge, and embeds a customizable widget on their site.

Professional services: [corefixlab.com/corebot](https://corefixlab.com/corebot)

## What it does

corebot is built for **SaaS CRM operators** who want client-specific support bots without cross-tenant data leaks or hallucinated business rules.

### Multi-tenant admin

- **Super admin** creates isolated tenant workspaces (UI or seed data).
- **Tenant admin** accounts can be added with `php artisan app:user-create` — see [Create users (CLI)](#create-users-cli).
- Tenant admins manage bots, AI settings, knowledge, conversations, and widget appearance.
- Every query is scoped by `tenant_id` through `TenantAccess` — no generic tenancy package.

### Per-tenant OpenAI

- Encrypted API key storage; only masked keys appear in the UI.
- Connection test must pass before chat or knowledge indexing runs.
- Configurable chat and embedding models per tenant.

### Knowledge & RAG

- Sources: plain text, FAQ pairs, PDF (`pdftotext`), DOCX (`python-docx`).
- Background job chunks content, generates embeddings, stores vectors in **PostgreSQL pgvector**.
- Chat retrieves the closest chunks per bot, applies similarity thresholds, and builds a strict context-only prompt.
- When retrieval is not confident, the bot escalates and can prompt for visitor contact (configurable fields per bot).

### Public chat & widget

- REST + **SSE streaming** API for visitor chat.
- `public/widget.js` — no framework required on the host site.
- Widget: colors, corner position, labels, launcher icon, welcome message.
- Optional domain allow-list per bot; rate limiting on public endpoints.
- Visitor contact capture when the bot cannot answer confidently; per-bot notification email on lead submit.
- Visitor context on conversations: IP, referrer, country/city (MaxMind GeoLite2), UTM, timezone, and language.

### Operations

- Dashboard with usage logs.
- **Laravel Horizon** for Redis queue workers and job monitoring (`/horizon`).
- Conversation review including **retrieval logs** (which chunks were used).
- Live **demo page** (`/demo`) driven by `DEMO_BOT_PUBLIC_KEY`.
- Welcome-page contact form emails your inbox (`SUPPORT_REQUEST_EMAIL`).

## Requirements

| Requirement | Notes |
|-------------|--------|
| PHP 8.3+ | |
| PostgreSQL 15+ | **Required** — with `pgvector` extension |
| Redis | Queues + Horizon (knowledge indexing jobs) |
| Node.js 20+ | Frontend build |
| `pdftotext` | PDF knowledge (Poppler) |
| `python3` + `python-docx` | DOCX knowledge — see [Python (DOCX extraction)](#python-docx-extraction) |
| MaxMind GeoLite2 City (optional) | Visitor country/city on widget chat — see [MaxMind GeoLite2](#maxmind-geolite2) |

## Python (DOCX extraction)

Python is used **only** to turn uploaded Word (`.docx`) knowledge sources into plain text before chunking and embedding. The rest of the app is PHP/Laravel; there is no Python web server or separate Python service.

### How it fits in the pipeline

1. A tenant uploads a DOCX file as a knowledge source.
2. `ProcessKnowledgeSourceJob` runs on the queue (Horizon).
3. `DocumentTextExtractor` calls `DocxExtractionClient`, which runs a short-lived subprocess:

   ```text
   {DOCX_PYTHON or python3} scripts/extract_docx.py /absolute/path/to/file.docx
   ```

4. The script prints extracted text to **stdout**; Laravel reads that output and continues with chunking, OpenAI embeddings, and pgvector storage.

Set `DOCX_PYTHON` in `.env` when you use a virtualenv or a non-default interpreter (see below). Horizon does not read this variable itself — PHP queue workers do when they spawn the subprocess.

Relevant code:

| Piece | Location |
|-------|----------|
| Extraction script | `scripts/extract_docx.py` |
| PHP wrapper | `app/Services/Documents/DocxExtractionClient.php` |
| Type routing (text / FAQ / PDF / DOCX) | `app/Services/Documents/DocumentTextExtractor.php` |

The script uses the [`python-docx`](https://python-docx.readthedocs.io/) library. It collects non-empty paragraph text and table rows (cells joined with ` | `). If nothing is extractable, it exits with a non-zero status and Laravel surfaces the error on the knowledge source.

### What to install

You need **Python 3** on the same machine that runs queue workers (Horizon), not only the web process — indexing happens in the background job.

1. **Python 3.9+** with **`python-docx`** installed for that interpreter.
2. **`DOCX_PYTHON`** (optional) — absolute path to the interpreter used for DOCX jobs. If unset, Laravel runs `python3` from the queue worker’s `PATH`.

   ```bash
   python3 -m venv .venv
   .venv/bin/pip install python-docx
   ```

   In `.env` (use the real path on your server):

   ```env
   DOCX_PYTHON=/path/to/corebot/.venv/bin/python3
   ```

   After changing `.env`, restart Horizon so workers reload config (`php artisan horizon:terminate`, or restart Supervisor/systemd).

   You do **not** configure Python inside `config/horizon.php`. Point `DOCX_PYTHON` at the venv’s `python3` instead of tweaking Supervisor `PATH`, unless you prefer PATH-only setup.

There is no `requirements.txt` in the repo today; the only Python dependency is `python-docx`.

### OS examples

**macOS (Homebrew)**

```bash
brew install python@3
python3 -m pip install python-docx
```

**Debian / Ubuntu**

```bash
sudo apt update
sudo apt install python3 python3-pip
python3 -m pip install python-docx
```

**Verify**

```bash
python3 -c "import docx; print('python-docx OK')"
python3 scripts/extract_docx.py /path/to/sample.docx
```

The second command should print plain text on success. Exit code `1` means no extractable content; `2` means missing file argument.

### Production notes

- Install Python and `python-docx` on every host that runs `php artisan horizon` (or any `queue:work` setup).
- The subprocess timeout is **30 seconds** per file (`DocxExtractionClient`).
- PDFs do **not** use Python; they use `pdftotext` (Poppler) via `PdfTextExtractor`.
- If DOCX indexing fails with import errors, set `DOCX_PYTHON` to the interpreter where you ran `pip install python-docx`, or install the package for the `python3` on the worker `PATH` (`which python3` as the Horizon user).

### Laravel Forge (zero-downtime)

With Forge zero-downtime, `current` is a symlink that moves to a **new release folder** on every deploy (`releases/1234567890`, etc.). Anything you put **inside** that release tree (including `current/.venv`) is tied to that deploy unless you recreate it each time.

**Do not** rely on a venv that only exists under a single release path, and avoid baking a release id into `DOCX_PYTHON`:

```env
# Fragile — old release is removed; new release may have no venv yet
DOCX_PYTHON=/home/forge/example.com/releases/1234567890/.venv/bin/python3
```

Using `current` in the path is slightly better (`/home/forge/example.com/current/.venv/bin/python3`) because the symlink always resolves to the active release — but you must still **create** `.venv` on every deploy inside the new release, or DOCX jobs break right after deploy.

**Recommended:** keep one virtualenv **outside** `releases/` and `current/`, at the **site root** (same level as `releases/`, `storage/`, shared `.env`):

```text
/home/forge/example.com/
  .env                 # Forge shared env — survives deploys
  .venv/               # stable Python env (not in releases/)
  current -> releases/…
  releases/
  storage/
```

One-time (or in the first deploy script):

```bash
cd /home/forge/example.com
python3 -m venv .venv
.venv/bin/pip install python-docx
```

In Forge **Environment** (shared `.env`):

```env
DOCX_PYTHON=/home/forge/example.com/.venv/bin/python3
```

Use your real site path from Forge (not `example.com`). This path stays the same across zero-downtime deploys; only `current` changes. After deploy, Forge restarts Horizon — workers pick up the same `DOCX_PYTHON`.

Optional: in the Forge **Deploy Script**, only ensure the venv exists (idempotent), not per-release:

```bash
if [ ! -d "$FORGE_SITE_ROOT/.venv" ]; then
  python3 -m venv "$FORGE_SITE_ROOT/.venv"
fi
"$FORGE_SITE_ROOT/.venv/bin/pip" install -q python-docx
```

Forge injects `$FORGE_SITE_ROOT` as the site directory above `releases/` (e.g. `/home/forge/example.com`). `$FORGE_SITE_PATH` points at `current` and changes each deploy — use `$FORGE_SITE_ROOT` for `.venv`, not `$FORGE_SITE_PATH`.

**Alternative:** skip `DOCX_PYTHON` and install `python-docx` for the system `python3` on the server; then PATH alone is enough and deploy layout does not matter.

## MaxMind GeoLite2

corebot uses a local **MaxMind GeoLite2 City** database (`.mmdb`) to resolve visitor **country** and **city** when a widget conversation starts. Lookup runs in PHP via [`geoip2/geoip2`](https://github.com/maxmind/GeoIP2-php); no per-request external GeoIP API is called.

Geo fields are stored on `chat_conversations` and shown in the admin **Conversations** UI. Chat still works if GeoIP is not configured — country/city are simply left empty.

### What to install

1. A free [MaxMind account](https://www.maxmind.com/en/geolite2/signup) and access to **GeoLite2** downloads.
2. The **GeoLite2 City** binary database file, usually named `GeoLite2-City.mmdb` after you extract the downloaded archive.
3. **`GEOIP_DATABASE_PATH`** in `.env` — absolute path to that `.mmdb` file.

### Download the database

1. Sign in at [MaxMind](https://www.maxmind.com/en/account/login).
2. Open **Download Files** (or **GeoIP / GeoLite2** → **Download Databases**).
3. Download **GeoLite2 City** (GZIP). MaxMind updates these files regularly; plan to refresh on a schedule (e.g. monthly).
4. Extract the archive and locate `GeoLite2-City.mmdb`.

Keep the file **outside** per-release deploy folders on production (same idea as `.venv` above), e.g.:

```text
/home/forge/example.com/
  storage/
    app/
      geoip/
        GeoLite2-City.mmdb
```

Or use MaxMind’s [`geoipupdate`](https://github.com/maxmind/geoipupdate) tool with a license key to refresh the file automatically into a fixed path.

### Configure corebot

In `.env`:

```env
GEOIP_DATABASE_PATH=/home/forge/example.com/storage/app/geoip/GeoLite2-City.mmdb
```

Use your real absolute path. After changing `.env`, restart PHP-FPM / queue workers if they cache config (`php artisan config:clear` in development).

**Verify** (optional):

```bash
php artisan tinker --execute 'dump(app(\App\Services\GeoIp\MaxMindGeoIpService::class)->lookup("8.8.8.8"));'
```

You should see `country_code`, `country_name`, and optionally `city`. Local/private IPs (e.g. `127.0.0.1`) intentionally return empty values.

### Privacy

Widget `/start` also records IP address, user agent, referrer, UTM parameters, timezone, and `Accept-Language` for support context. Document this in your site privacy policy if required. GeoLite2 is subject to [MaxMind’s GeoLite2 EULA](https://www.maxmind.com/en/geolite2/eula); do not redistribute the raw database.

Relevant code:

| Piece | Location |
|-------|----------|
| GeoIP service | `app/Services/GeoIp/MaxMindGeoIpService.php` |
| Config key | `config/corebot.php` → `geoip_database_path` |
| Capture on chat start | `app/Http/Controllers/PublicChatController.php` |

## Database setup

corebot **requires PostgreSQL with pgvector**. SQLite is not supported for running the application.

```sql
CREATE DATABASE corebot;
\c corebot
CREATE EXTENSION IF NOT EXISTS vector;
```

## Quick start

```bash
git clone https://github.com/rezaf-dev/corebot.git
cd corebot
composer install
npm install
python3 -m pip install python-docx
cp .env.example .env
php artisan key:generate
```

Configure `.env` (see below), then:

```bash
php artisan migrate --seed
composer run dev
```

Seeded accounts:

| Role | Email | Password |
|------|-------|----------|
| Super admin | super@example.com | password |
| Tenant admin | tenant@example.com | password |

- Admin: `http://localhost:8000`
- Widget demo: `http://localhost:8000/demo` (set `DEMO_BOT_PUBLIC_KEY` first)

## Create users (CLI)

After tenants exist, add admin accounts with the Artisan command defined in `app/Console/Commands/CreateUserCommand.php`:

```bash
php artisan app:user-create
```

The command prompts for name, email, password, and tenant (for tenant admins). You can pass everything via options for scripts and production setup:

**Tenant admin** (must belong to an existing tenant — use id or slug from `/tenants` or seed data):

```bash
php artisan app:user-create \
  --name="Acme Admin" \
  --email=admin@acme.com \
  --password='your-secure-password' \
  --role=tenant_admin \
  --tenant=acme \
  --verified
```

**Super admin** (no tenant; can create tenants in the UI):

```bash
php artisan app:user-create \
  --name="Platform Admin" \
  --email=super@yourcompany.com \
  --password='your-secure-password' \
  --role=super_admin \
  --verified
```

| Option | Description |
|--------|-------------|
| `--name` | Display name |
| `--email` | Login email (must be unique) |
| `--password` | Initial password (min. 8 characters) |
| `--role` | `tenant_admin` (default) or `super_admin` |
| `--tenant` | Tenant id or slug — required for `tenant_admin` |
| `--verified` | Mark email as verified (skips verification flow) |

## Environment variables

Only the variables you typically need to change:

| Variable | Purpose |
|----------|---------|
| `APP_KEY` | `php artisan key:generate` — also decrypts stored API keys |
| `APP_URL` | Public URL of the app |
| `DB_*` | PostgreSQL connection (`DB_CONNECTION=pgsql`) |
| `QUEUE_CONNECTION` | Use `redis` and run Horizon (`php artisan horizon`) |
| `REDIS_*` | Redis connection for queues and Horizon metadata |
| `REDIS_QUEUE_RETRY_AFTER` | Must be greater than job timeout (default `330` for 300s indexing jobs) |
| `MAIL_MAILER`, `MAIL_FROM_*` | Outbound mail |
| `SUPPORT_REQUEST_EMAIL` | Inbox for the welcome-page contact form |
| `DEMO_BOT_PUBLIC_KEY` | Bot `public_key` embedded on `/demo` |
| `DOCX_PYTHON` | Optional absolute path to Python 3 for DOCX indexing (default: `python3` on worker `PATH`) |
| `GEOIP_DATABASE_PATH` | Optional absolute path to `GeoLite2-City.mmdb` for visitor country/city — see [MaxMind GeoLite2](#maxmind-geolite2) |

Example `.env` fragment:

```env
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=corebot
DB_USERNAME=postgres
DB_PASSWORD=

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_QUEUE_RETRY_AFTER=330

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=hello@yourdomain.com
SUPPORT_REQUEST_EMAIL=hello@yourdomain.com

DEMO_BOT_PUBLIC_KEY=bot_xxxxxxxx

# Optional venv interpreter for DOCX knowledge sources
# DOCX_PYTHON=/var/www/corebot/.venv/bin/python3

# Optional MaxMind GeoLite2 City database for visitor geo on widget chat
# GEOIP_DATABASE_PATH=/var/www/corebot/storage/app/geoip/GeoLite2-City.mmdb
```

For local mail testing, `MAIL_MAILER=log` writes to `storage/logs/laravel.log`.

## Queue & Horizon

Knowledge sources are indexed in the background (`ProcessKnowledgeSourceJob`). This project uses **Laravel Horizon** on top of Redis instead of a plain `queue:work` process.

### Local development

`composer run dev` starts Horizon together with the app server, logs, and Vite. You can also run it alone:

```bash
php artisan horizon
```

Open the dashboard at `http://localhost:8000/horizon`. In the `local` environment, any logged-in user can view it. Failed and completed jobs, throughput, and wait times are visible there.

### Production

Run a single long-lived Horizon process (Supervisor or systemd is recommended):

```bash
php artisan horizon
```

After deploys, gracefully restart workers so they pick up code changes:

```bash
php artisan horizon:terminate
```

The scheduler records metrics snapshots every five minutes (`horizon:snapshot`). Ensure your server cron runs Laravel’s scheduler:

```bash
* * * * * cd /path/to/corebot && php artisan schedule:run >> /dev/null 2>&1
```

### Dashboard access

Horizon lives at `/horizon` and requires authentication. In non-local environments, only **super admins** may open the dashboard (`viewHorizon` gate in `app/Providers/HorizonServiceProvider.php`).

Sign in as the seeded super admin (`super@example.com`) or a super admin created via `app:user-create --role=super_admin`.

### Worker settings

Horizon supervisors are defined in `config/horizon.php`:

| Setting | Value | Why |
|---------|-------|-----|
| `timeout` | 300 | Matches `ProcessKnowledgeSourceJob` (PDF/DOCX + embeddings) |
| `tries` | 3 | Retries transient OpenAI or I/O failures |
| `memory` | 256 | Headroom for document extraction |

`REDIS_QUEUE_RETRY_AFTER` must stay **above** the job timeout so Redis does not re-queue a job that is still running.

## Production checklist

```bash
php artisan migrate --force
php artisan horizon   # keep running via Supervisor/systemd
npm run build
php artisan config:cache
php artisan route:cache
```

Configure cron for `php artisan schedule:run` (Horizon metrics snapshots).

Install system tools: Poppler (`pdftotext`), Python 3 + `python-docx` (see [Python (DOCX extraction)](#python-docx-extraction)).

## Widget embed

Admin → **Widget** → customize → copy snippet:

```html
<script src="https://YOUR_DOMAIN/widget.js" data-bot-key="BOT_PUBLIC_KEY"></script>
```

If the app is served from a subfolder (e.g. `https://YOUR_DOMAIN/corebot/widget.js`), the widget resolves API calls under that same path (`…/corebot/api/public/chat`). Set `APP_URL` to the subfolder URL so admin embed snippets match. Optional override: `data-api-base="api/public/chat"` (relative to the script) or a full URL.

## Public API

`/api/public/chat` — `widget-config`, `start`, `message`, `message/stream`, `contact` (rate-limited). Under a subfolder deploy, prefix with the app path (e.g. `/corebot/api/public/chat`).

## Tests

Automated tests use SQLite in `phpunit.xml`; the application itself expects PostgreSQL + pgvector.

```bash
php artisan test --compact
```

## Security

- Scope all tenant-owned data by `tenant_id`.
- Never expose raw OpenAI keys to the browser or public APIs.
- Rotate `APP_KEY` with care — it decrypts tenant API keys.

## License

MIT — see [LICENSE](LICENSE).

## Author

[CoreFix Lab](https://corefixlab.com/corebot)
