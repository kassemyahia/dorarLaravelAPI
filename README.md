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
cd /home/kassem_yahia/Documents/Projects/Laravel/DorarLaravelAPI
composer install
cp .env.example .env
php artisan key:generate
```

## Run Locally

```bash
php artisan serve
```

Default URL:

- `http://127.0.0.1:8000`

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
