---
paths:
  - "backend/app/Services/**/*.php"
  - "backend/app/Http/Controllers/**/*.php"
  - "backend/config/quickbooks.php"
  - "packages/api-client/src/**/*.{ts,tsx}"
  - "apps/admin/src/**/*.{ts,tsx}"
  - "apps/timesheet/src/**/*.{ts,tsx}"
  - "packages/ui/src/**/*.{ts,tsx}"
---

# QuickBooks API economy

Canonical detail: `.cursor/rules/quickbooks-api.mdc`, `docs/quickbooks-time-activity-sync.md`.

Intuit rate-limits the app. Treat every SDK call as costly.

- SDK only in services (`QuickBooksService` and domain list/CRUD services).
- Cache read-heavy lists (`QboListCacheService`, key `quickbooks:{resource}:{realm_id}`). TTL: `QUICKBOOKS_LIST_CACHE_TTL_MINUTES` (default 15).
- Lazy pickers: `useLazyApiSelect` plus `LazySearchCombobox`. Fetch on open from cache (`refresh=false`). `?refresh=1` is an explicit bypass.
- Do not cache auth probes or empty lists. Invalidate the realm cache on disconnect.
- Prefer `FindById` over re-listing a whole collection.
- Before a new QBO endpoint: can it be cached or derived, is one `FindById` enough, does the picker fetch on open, and are there Pest tests for cache hit vs `refresh=1`?
