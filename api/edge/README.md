# Edge API Endpoints (Hostinger/PHP)

These endpoints allow Cloudflare Workers to accelerate redirects while keeping MySQL as source of truth.

## Endpoints
- `POST /api/edge/resolve.php`
- `POST /api/edge/click.php`
- `POST /api/edge/invalidate.php`

## Authentication
`resolve.php` and `click.php` require:
- `X-Edge-Key`
- `X-Edge-Signature` (HMAC SHA-256 over raw JSON body)
- `X-Edge-Timestamp` (epoch seconds, max skew 5 minutes)

`invalidate.php` requires:
- `X-Invalidate-Token` (internal token only)

## Environment Variables (Hostinger)
- `EDGE_API_KEY`
- `EDGE_SHARED_SECRET`
- `EDGE_INVALIDATE_TOKEN`
- `EDGE_WORKER_INVALIDATE_URL` (optional but recommended)
- `EDGE_WORKER_INVALIDATE_SECRET` (optional but recommended)
- `PUBLIC_BASE_URL`

## Resolve Response Contract (simplified)
```json
{
  "ok": true,
  "action": "redirect",
  "destination_url": "https://destino.com",
  "redirect_type": "302",
  "tracking_enabled": true,
  "cacheable": true,
  "force_origin": false,
  "ttl_seconds": 60,
  "cache_version": "abc123"
}
```

## Click Ingest Behavior
- Uses `edge_click_ingest.click_id` for idempotent dedupe.
- Increments `links.clicks` only once per `click_id`.
- Writes to `clicks_log` when analytics policy is enabled for the link owner.

## Required Migration
Run:
- `php run_edge_worker_support_migration.php`

This creates:
- `links.ab_edge_safe` column
- `edge_click_ingest` table

Optional (browser geolocation enrichment + per-link flag):

- Run `php run_precise_location_migration.php` on the server (adds `links.request_precise_location` and optional `clicks_log.geo_*` columns).

