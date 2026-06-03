# corebot

Multi-tenant **CRM AI support bot** — Laravel 13, Inertia React, PostgreSQL + pgvector, and an embeddable chat widget. Each tenant uses their own OpenAI key, uploads CRM knowledge, and embeds the widget on their site.

Professional services: [corefixlab.com/corebot](https://corefixlab.com/corebot)

## Features

- **Multi-tenant admin** — super admin creates workspaces; tenant admins manage bots, AI settings, knowledge, conversations, and widget styling. Data is scoped by `tenant_id`.
- **RAG chat** — text, FAQ, PDF, and DOCX sources; pgvector retrieval; SSE streaming; contact capture when answers are uncertain.
- **Bot integrations** — per-bot actions the AI can call (webhook, create lead, send email, HTTP GET). Configure under **Bots → Edit → Integrations**.
- **Widget** — vanilla JS embed, domain allow-list, customizable colors and labels.
- **Ops** — Horizon queues, conversation + retrieval logs, optional GeoIP and web research for knowledge.

## Requirements

| Requirement | Notes |
|-------------|--------|
| PHP 8.3+ | |
| PostgreSQL 15+ | with `pgvector` extension |
| Redis | queues + Horizon |
| Node.js 20+ | frontend build |
| `pdftotext` | PDF knowledge (Poppler) |
| `python3` + `python-docx` | DOCX knowledge (optional) |

SQLite is **not** supported for running the app (tests use SQLite only).

### Database

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
cp .env.example .env
php artisan key:generate
```

Set PostgreSQL and Redis in `.env`, then:

```bash
php artisan migrate --seed
composer run dev
```

Open **http://localhost:8000**

| Role | Email | Password |
|------|-------|----------|
| Super admin | super@example.com | password |
| Tenant admin | tenant@example.com | password |

Widget demo: **http://localhost:8000/demo** (set `DEMO_BOT_PUBLIC_KEY` in `.env` to a bot’s `public_key`).

One-shot setup (install, migrate, build assets):

```bash
composer run setup
```

## Local development

Start everything with one command:

```bash
composer run dev
```

This runs four processes (via `concurrently`):

| Process | Command | URL / purpose |
|---------|---------|----------------|
| **server** | `php artisan serve` | http://localhost:8000 |
| **horizon** | `php artisan horizon` | background jobs (knowledge indexing) |
| **logs** | `php artisan pail` | live log tail in the terminal |
| **vite** | `npm run dev` | hot reload for Inertia/React |

Stopping the terminal stops all four (`--kill-others`).

**Horizon dashboard:** http://localhost:8000/horizon (any logged-in user in `local`).

**Run pieces separately** if you prefer:

```bash
php artisan serve
php artisan horizon
npm run dev
```

**Tests:**

```bash
composer test
# or
php artisan test --compact
```

**Format PHP:**

```bash
vendor/bin/pint --dirty
```

## Environment

Common `.env` values:

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

MAIL_MAILER=log
MAIL_FROM_ADDRESS=hello@example.com
SUPPORT_REQUEST_EMAIL=hello@example.com

DEMO_BOT_PUBLIC_KEY=bot_xxxxxxxx
```

| Variable | Purpose |
|----------|---------|
| `APP_KEY` | Required; decrypts stored tenant API keys |
| `QUEUE_CONNECTION` | Use `redis` and run Horizon |
| `REDIS_QUEUE_RETRY_AFTER` | Must exceed job timeout (default 330) |
| `DOCX_PYTHON` | Optional path to Python 3 for DOCX indexing |
| `GEOIP_DATABASE_PATH` | Optional `GeoLite2-City.mmdb` for visitor geo |
| `TAVILY_API_KEY` | Optional; better web search in Knowledge → Research web |

For local mail, `MAIL_MAILER=log` writes to `storage/logs/laravel.log`.

## Create users (CLI)

```bash
php artisan app:user-create \
  --name="Acme Admin" \
  --email=admin@acme.com \
  --password='your-secure-password' \
  --role=tenant_admin \
  --tenant=acme \
  --verified
```

Super admin: omit `--tenant`, use `--role=super_admin`.

## Widget embed

Admin → **Widget** → copy snippet:

```html
<script src="https://YOUR_DOMAIN/widget.js" data-bot-key="BOT_PUBLIC_KEY"></script>
```

Public API (rate-limited): `/api/public/chat` — `start`, `message`, `message/stream`, `contact`.

## Optional setup

### DOCX knowledge

Queue workers need Python 3 with `python-docx`:

```bash
python3 -m pip install python-docx
# or
python3 -m venv .venv && .venv/bin/pip install python-docx
```

Set in `.env` if not on `PATH`:

```env
DOCX_PYTHON=/absolute/path/to/python3
```

Restart Horizon after changing env (`php artisan horizon:terminate`).

### MaxMind GeoLite2

Download [GeoLite2 City](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) (`.mmdb`), then:

```env
GEOIP_DATABASE_PATH=/path/to/GeoLite2-City.mmdb
```

Chat works without it; country/city fields stay empty.

### Knowledge web research

**Knowledge → Research web** fetches a URL or searches the web (DuckDuckGo by default; set `TAVILY_API_KEY` for Tavily).

## Production

```bash
php artisan migrate --force
npm run build
php artisan config:cache
php artisan route:cache
php artisan horizon   # Supervisor/systemd — keep running
```

Cron (Horizon metrics + scheduler):

```bash
* * * * * cd /path/to/corebot && php artisan schedule:run >> /dev/null 2>&1
```

After deploy: `php artisan horizon:terminate`

Install on the server: Poppler (`pdftotext`), Python + `python-docx` if you use DOCX sources.

## Security

- Scope tenant data by `tenant_id`.
- Never expose OpenAI keys to the browser or public APIs.
- Rotating `APP_KEY` breaks decryption of stored tenant API keys.

## License

MIT — see [LICENSE](LICENSE).

## Author

[CoreFix Lab](https://corefixlab.com/corebot)
