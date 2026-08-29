# Dorar Laravel API

Laravel port of the original `dorar-hadith-api` project.

## What This Project Provides

- Equivalent API endpoints under `/v1`.
- Same Dorar search/scraping behavior implemented with Laravel services.
- Static data endpoints (`/v1/data/*`) backed by local JSON files.
- Swagger UI docs at `/api-docs`.

## Requirements

- PHP `8.4+`
- Composer `2+`

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

In a second terminal, start the resumable chunk worker:

```bash
php artisan queue:work --tries=1 --timeout=600
```

## Run Locally

```bash
php artisan serve
```

Default URL:

- `http://127.0.0.1:8000`
- Enrichment UI: `http://127.0.0.1:8000/tools/enrichment`

The enrichment importer accepts the wrapper JSON files from
`db/by_book/the_9_books/*.json`. It preserves `id`, `metadata`, `chapters`, every
original hadith field (including `idInBook`), and adds `dorar` and `matching`.
Requests are processed in chunks of 10. The delay and confidence thresholds can
be selected per import. Dorar source filters are not inferred from the local
1–9 `bookId` values.

Upstream URLs and failure behavior can be configured with `DORAR_SITE_URL`,
`DORAR_API_URL`, `FETCH_TIMEOUT`, `FETCH_RETRIES`, and
`FETCH_RETRY_BASE_MS`. HTTP 403 means permitted upstream access is required;
the application reports it and does not attempt to bypass Cloudflare.

## Documentation

- JSON docs index: `GET /docs`
- Swagger UI: `GET /api-docs`
- OpenAPI file: `GET /api-docs/openapi.yaml`

## Main API Routes

- `GET /v1/api/hadith/search?value=...&page=1`
- `GET /v1/site/hadith/search?value=...&page=1`
- `GET /v1/site/hadith/{id}`
- `GET /v1/site/hadith/similar/{id}`
- `GET /v1/site/hadith/alternate/{id}`
- `GET /v1/site/hadith/usul/{id}`
- `GET /v1/site/sharh/search?value=...&page=1`
- `GET /v1/site/sharh/{id}`
- `GET /v1/site/sharh/text/{text}`
- `GET /v1/site/book/{id}`
- `GET /v1/site/mohdith/{id}`
- `GET /v1/data/book`
- `GET /v1/data/degree`
- `GET /v1/data/methodSearch`
- `GET /v1/data/mohdith`
- `GET /v1/data/rawi`
- `GET /v1/data/zoneSearch`

## Notes

- The API depends on upstream Dorar website/API responses. If Dorar changes HTML structure, parsing endpoints may need updates.
- Cache and timeout behavior are configurable through `.env` values used in `config/dorar.php`.

## Quick Checks

```bash
php artisan route:list --path=v1
curl "http://127.0.0.1:8000/v1/api/hadith/search?value=انما%20الاعمال&page=1"
```
