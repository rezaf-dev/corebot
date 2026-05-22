# Multi-Tenant CRM AI Support Bot

Laravel 13 + Inertia React application for CRM owners who need an embeddable AI support bot. Tenants upload approved CRM knowledge, connect their own OpenAI API key, and install a vanilla JavaScript chat widget on their CRM or website.

The bot is intentionally constrained: it retrieves tenant-owned knowledge, answers only from that context, and falls back/escalates when the knowledge base does not safely answer the visitor's question.

## Current V1 Status

Implemented:

- Laravel 13 application with Breeze auth, Inertia, React, Tailwind, and Pest.
- Explicit tenant scoping without a tenancy package.
- Super admin tenant creation.
- Tenant admin AI settings with encrypted OpenAI key storage and connection test action.
- Bot CRUD with public embed key, domain allow-list, prompt, fallback, and retrieval settings.
- Knowledge sources for text, FAQ, PDF, and DOCX.
- PDF extraction through `spatie/pdf-to-text`.
- DOCX extraction through `scripts/extract_docx.py`.
- Text chunking, embedding job, pgvector-aware search, and SQLite test fallback.
- RAG chat answer service with message, retrieval, and AI usage logs.
- Public chat API and vanilla `public/widget.js` embed.
- Admin dashboard, knowledge review, conversation review, and widget install pages.
- CRM-focused demo seed data.

Not implemented in V1:

- OCR for scanned PDFs.
- Legacy `.doc` support.
- Multiple LLM providers.
- Streaming chat.
- Live human-agent handoff integration.
- Billing.
- Tenant impersonation.
- Production-grade analytics/cost calculation.

## Stack

- Backend: Laravel 13, PHP 8.3+
- Frontend/admin: Inertia React, Tailwind
- Database: PostgreSQL with `pgvector` for production
- Queue: Redis preferred
- Storage: Laravel filesystem, local by default, S3-compatible when configured
- LLM provider: OpenAI
- Embeddings: `text-embedding-3-small`, 1536 dimensions
- Chat default: `gpt-4o-mini`
- Tests: Pest
- Widget: framework-free JavaScript in `public/widget.js`

## Important Paths

- Admin routes: `routes/web.php`
- Public API routes: `routes/api.php`
- Tenant scoping helper: `app/Support/TenantAccess.php`
- AI client: `app/Services/AI/OpenAIService.php`
- RAG services: `app/Services/Rag`
- Document extraction services: `app/Services/Documents`
- Knowledge processing job: `app/Jobs/ProcessKnowledgeSourceJob.php`
- DOCX extractor: `scripts/extract_docx.py`
- Widget: `public/widget.js`
- Admin React pages: `resources/js/Pages`
- Database schema: `database/migrations`
- Demo seed data: `database/seeders/DatabaseSeeder.php`

## Local Setup

Install PHP and Node dependencies:

```bash
composer install
npm install --legacy-peer-deps
```

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

For quick local development, SQLite is acceptable:

```env
DB_CONNECTION=sqlite
QUEUE_CONNECTION=sync
```

Then create the SQLite file and seed demo data:

```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

Run the app:

```bash
php artisan serve
npm run dev
```

Or use Laravel's combined dev script:

```bash
composer run dev
```

Default seeded accounts:

```text
Super admin:  super@example.com / password
Tenant admin: tenant@example.com / password
```

## Production-Oriented Setup

`.env.example` defaults to PostgreSQL and Redis:

```env
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
```

PostgreSQL must have the `vector` extension available. The migration for `knowledge_chunks` runs:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
ALTER TABLE knowledge_chunks ADD COLUMN embedding vector(1536);
```

Run migrations and queues:

```bash
php artisan migrate --force
php artisan queue:work redis --tries=3 --timeout=300
npm run build
```

For PDF extraction, install `pdftotext` on the server. On macOS:

```bash
brew install poppler
```

For DOCX extraction, install Python and `python-docx`:

```bash
python3 -m pip install python-docx
```

## Data Model Overview

Core tenant/admin data:

- `tenants`: tenant account, slug, status.
- `users`: auth users, nullable `tenant_id`, role `super_admin` or `tenant_admin`.
- `tenant_ai_settings`: per-tenant OpenAI settings and encrypted API key.

Bot and knowledge data:

- `bots`: tenant bot configuration, public key, prompt, fallback, domain allow-list, retrieval settings.
- `knowledge_sources`: uploaded or entered source material.
- `knowledge_chunks`: chunk content plus `embedding_json`; in PostgreSQL also has `embedding vector(1536)`.

Chat/review data:

- `chat_conversations`: public visitor conversation metadata and escalation status.
- `chat_messages`: user and assistant messages.
- `retrieval_logs`: selected chunk IDs, distances, and context text used for an answer.
- `ai_usage_logs`: embedding/chat usage and provider errors.

## Tenant Boundaries

V1 deliberately does not use a tenancy package. Tenant safety is implemented with explicit filtering:

- Tenant admins have a non-null `tenant_id`.
- Super admins can view cross-tenant admin data where routes allow it.
- Tenant-scoped queries go through `TenantAccess::scope(...)` or explicit `tenant_id` checks.
- Controllers call `TenantAccess::ensureCanAccess(...)` before editing/showing tenant-owned records.
- Public chat resolves a bot by `public_key`, then validates active tenant and active bot.

When adding a feature, do not query tenant-owned tables without filtering by `tenant_id`, unless the route is intentionally super-admin-only.

## AI Key Handling

Tenant admins configure AI settings at `/ai-settings`.

Rules:

- The raw OpenAI key is accepted only on save/test.
- It is stored encrypted in `tenant_ai_settings.api_key_encrypted`.
- The model exposes it through the virtual `api_key` attribute.
- The frontend receives only `masked_api_key`, for example `sk-...abcd`.
- New or changed keys mark settings inactive until the test action succeeds.
- Knowledge processing and chat fail safely when AI settings are missing or inactive.

## Knowledge Processing Flow

`ProcessKnowledgeSourceJob` handles indexing:

1. Load the source with tenant, bot, and AI settings.
2. Verify tenant AI settings are active.
3. Mark the source `processing`.
4. Extract source text.
5. Chunk text with `TextChunker`.
6. Generate an embedding for each chunk with the tenant's key.
7. Delete old chunks only after new chunks are ready.
8. Insert replacement chunks.
9. Mark the source `ready`, update `chunks_count`, and set `last_indexed_at`.
10. On failure, mark the source `failed` and store a sanitized error.

PDF extraction:

- Uses `PdfTextExtractor`.
- Requires `pdftotext`.
- No OCR in V1.

DOCX extraction:

- Uses `DocxExtractionClient`.
- Calls `python3 scripts/extract_docx.py /absolute/path/to/file.docx`.
- Uses Symfony Process array arguments, not shell strings.
- Extracts paragraphs and tables.

## RAG Chat Flow

Public chat calls `POST /api/public/chat/message`, which delegates to `ChatAnswerService`:

1. Save the user message.
2. Generate query embedding with tenant API key.
3. Search chunks by `tenant_id` and `bot_id`.
4. Sort by cosine distance.
5. Apply bot `similarity_threshold`.
6. If no chunks pass, save fallback message and mark the conversation `escalated`.
7. Build a strict context-only prompt.
8. Call OpenAI chat completion.
9. Save assistant message.
10. Save retrieval log and usage log.
11. Return answer and source metadata.

PostgreSQL uses pgvector:

```sql
embedding <=> ? AS distance
```

SQLite/local tests use `embedding_json` and PHP cosine distance fallback.

## Public API

Routes are defined in `routes/api.php` under `/api/public/chat`.

Start:

```http
POST /api/public/chat/start
```

```json
{
  "bot_public_key": "bot_xxx",
  "visitor_id": "browser-uuid",
  "source_url": "https://clientcrm.com/help"
}
```

Message:

```http
POST /api/public/chat/message
```

```json
{
  "bot_public_key": "bot_xxx",
  "conversation_id": 123,
  "message": "How do I change an order status?"
}
```

Contact:

```http
POST /api/public/chat/contact
```

```json
{
  "bot_public_key": "bot_xxx",
  "conversation_id": 123,
  "visitor_name": "John",
  "visitor_email": "john@example.com",
  "visitor_phone": "+1..."
}
```

Security behavior:

- Validates active bot and active tenant.
- Validates `allowed_domains` when configured.
- Rate limits by IP and `bot_public_key`.
- Caps messages at 2,000 characters.
- Does not expose raw API/provider errors publicly.

## Widget

Admin page: `/widget-install`

Embed example:

```html
<script src="https://YOUR_APP_DOMAIN/widget.js" data-bot-key="BOT_PUBLIC_KEY"></script>
```

V1 widget behavior:

- Floating bottom-right button.
- Chat panel.
- Persists `visitor_id` and `conversation_id` in `localStorage`.
- Starts a conversation through the public API.
- Sends visitor messages.
- Displays loading/error state.
- Shows contact form after fallback.

## Admin Pages

- `/dashboard`: counts and recent AI usage.
- `/tenants`: super-admin tenant creation.
- `/ai-settings`: tenant OpenAI settings and test action.
- `/bots`: bot list and CRUD.
- `/knowledge-sources`: add text/FAQ/PDF/DOCX knowledge and reprocess sources.
- `/knowledge-sources/{id}`: source status and chunk review.
- `/conversations`: conversation list.
- `/conversations/{id}`: messages, visitor data, retrieval logs, context text.
- `/widget-install`: embed snippets.

## Tests

Run:

```bash
php artisan test
```

Current coverage includes:

- Auth starter tests.
- API key encryption/masking.
- Tenant isolation for bot edits.
- Public chat inactive bot rejection.
- Public chat allowed-domain validation.
- Text chunking behavior.

Run formatting:

```bash
./vendor/bin/pint
```

Build frontend assets:

```bash
npm run build
```

## Known Implementation Notes

- `npm install` currently needs `--legacy-peer-deps` because Laravel's Vite plugin targets Vite 8 while the React plugin peer range lags behind. Production build currently succeeds.
- The exact pgvector index is intentionally omitted in V1. Add an IVFFlat/HNSW index later after production data exists and query behavior is measured.
- `embedding_json` is retained for local SQLite tests and non-Postgres fallback behavior.
- The local dev database can be SQLite, but production should use PostgreSQL with `pgvector`.
- Queue workers must run for PDF/DOCX/text/FAQ sources to become searchable.
- Public chat quality depends on successful source processing and active tenant AI settings.

## Agent/Developer Guidelines

When extending the app:

- Preserve explicit tenant filtering.
- Never return raw API keys to Inertia or public APIs.
- Keep public chat errors generic.
- Do not delete old knowledge chunks until replacement extraction/chunking/embedding has succeeded.
- Use Process array arguments for local commands.
- Avoid adding a tenancy package unless the architecture is intentionally revisited.
- Add tests for every tenant boundary, public endpoint, and AI failure path you change.
- Keep demo data CRM-specific, not generic company FAQ content.
