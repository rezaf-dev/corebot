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
- If nothing relevant is found, the bot returns a **fallback** and can **escalate** the conversation.

### Public chat & widget

- REST + **SSE streaming** API for visitor chat.
- `public/widget.js` — no framework required on the host site.
- Widget: colors, corner position, labels, launcher icon, welcome message.
- Optional domain allow-list per bot; rate limiting on public endpoints.
- Visitor contact capture when the bot cannot answer.

### Operations

- Dashboard with usage logs.
- Conversation review including **retrieval logs** (which chunks were used).
- Live **demo page** (`/demo`) driven by `DEMO_BOT_PUBLIC_KEY`.
- Welcome-page contact form emails your inbox (`SUPPORT_REQUEST_EMAIL`).

## Requirements

| Requirement | Notes |
|-------------|--------|
| PHP 8.3+ | |
| PostgreSQL 15+ | **Required** — with `pgvector` extension |
| Redis | Queue worker for knowledge indexing |
| Node.js 20+ | Frontend build |
| `pdftotext` | PDF knowledge (Poppler) |
| `python3` + `python-docx` | DOCX knowledge |

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
npm install --legacy-peer-deps
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
| `QUEUE_CONNECTION` | Use `redis` and run `php artisan queue:work` |
| `REDIS_*` | Redis connection for queues |
| `MAIL_MAILER`, `MAIL_FROM_*` | Outbound mail |
| `SUPPORT_REQUEST_EMAIL` | Inbox for the welcome-page contact form |
| `DEMO_BOT_PUBLIC_KEY` | Bot `public_key` embedded on `/demo` |

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

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=hello@yourdomain.com
SUPPORT_REQUEST_EMAIL=hello@yourdomain.com

DEMO_BOT_PUBLIC_KEY=bot_xxxxxxxx
```

For local mail testing, `MAIL_MAILER=log` writes to `storage/logs/laravel.log`.

## Production checklist

```bash
php artisan migrate --force
php artisan queue:work redis --tries=3 --timeout=300
npm run build
php artisan config:cache
php artisan route:cache
```

Install system tools: Poppler (`pdftotext`), Python `python-docx`.

## Widget embed

Admin → **Widget** → customize → copy snippet:

```html
<script src="https://YOUR_DOMAIN/widget.js" data-bot-key="BOT_PUBLIC_KEY"></script>
```

## Public API

`/api/public/chat` — `widget-config`, `start`, `message`, `message/stream`, `contact` (rate-limited).

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
