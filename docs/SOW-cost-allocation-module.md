# SOW: AI Cost Allocation Module

Statement of work and implementation spec for a new bounded context inside the
existing `ellr-qbo-timesheet` monorepo.

- **Target repo:** `lucrousseau/ellr-qbo-timesheet`
- **Execution mode:** Cursor agents, ticket by ticket
- **Language:** English (per `.cursor/rules/language.mdc`)
- **Copy rule:** no em dash anywhere (per `.cursor/rules/copywriting.mdc`)
- **Revision:** 2. Adds the generic dimension model (AD-9), replacing hardcoded
  client / project / task columns. Adds seat-based ingestion to the non-goals.
- **Status:** draft for execution, pending commercial validation (see Gate 1)

---

## 1. Context

The repo currently ships a minimal QuickBooks Online timesheet. The valuable
part is not the timesheet. It is the QBO chassis: OAuth 2.0 with encrypted token
storage, webhook intake, a reconcile scheduler, tenant seeding, a shared API
client, and a hard quality gate.

This SOW adds a second bounded context on top of that chassis: **AI cost
allocation**. It takes a single provider invoice (Anthropic, OpenAI) and turns
it into per client, per project, per task cost, posted to the customer's QuickBooks
Online general ledger, with optional rebilling at markup.

The timesheet context is frozen, not deleted. It stays as a working demo.

---

## 2. Goal and non-goals

### Goal

Given a month of LLM usage, produce:

1. A per client, per project, per task cost breakdown that reconciles to the
   provider's actual invoice within a stated variance.
2. Journal entries or bills posted to QBO, idempotently, with an audit trail.
3. Optional billable lines so the tenant can rebill the client at markup.

### Non-goals (explicit, do not build)

- No LLM observability, tracing, prompt inspection, or latency metrics. Langfuse
  and LiteLLM own that layer.
- No cost optimization, model routing, or caching recommendations.
- No agent orchestration, no Kanban, no workflow engine.
- No MCP tooling that writes to QBO. The accounting layer decides what gets
  posted, never a model.
- No agent self-reported token counts. See invariant INV-2.
- No bookkeeping services. This is process automation with accounting oversight.
- No seat-based tooling ingestion (GitHub Copilot, Cursor, Claude Code seats). That
  is a different data shape, a different granularity, and a segment that is not on
  QuickBooks Online. See `product-context.md` section 2.
- No internal chargeback reporting as a primary use case. We allocate cost so it can
  be posted and rebilled externally. Internal budget chargeback is owned by the
  FinOps platforms.

---

## 3. Architecture decisions

| ID | Decision | Rationale |
|----|----------|-----------|
| AD-1 | New bounded context under `App\Allocation`, no shared models with `App\Timesheet` | Allows deleting the timesheet later without a rewrite |
| AD-2 | Enforce AD-1 with a Pest architecture test that forbids cross-namespace imports | Coupling is invisible until it is expensive |
| AD-3 | `usage_events` and `ledger_postings` are append-only. Corrections are reversing entries, never UPDATE | Accounting rule and audit requirement |
| AD-4 | Ingestion via provider admin API or gateway export, never agent self-report | Token counts are produced by the provider (INV-2) |
| AD-5 | MySQL in local dev, matching production. Drop SQLite for this context | Transactional integrity is the product; dev/prod divergence is unacceptable here |
| AD-6 | Money as integer minor units (`bigint`), never float. Token counts as `bigint` | Float rounding in a ledger is a defect |
| AD-7 | All provider prices versioned with `effective_from` / `effective_to`, never mutated | A January run recomputed in March must produce the same number |
| AD-8 | Every QBO write goes through a single `LedgerPoster` service with a mandatory idempotency key | Duplicate entries in a client's books is a product-ending failure |
| AD-9 | Allocation dimensions are generic key/value pairs declared per tenant, not hardcoded client / project / task columns | Every tenant slices differently (client, project, team, cost center). Hardcoding three columns is an assumption that breaks at the third customer |
| AD-10 | Exactly one dimension per tenant is flagged `is_billing_dimension`. Postings and rebilling key off that one | Without a single billing axis, a posting has no deterministic target and idempotency keys become ambiguous |

### Open decisions requiring human input

| ID | Question | Owner |
|----|----------|-------|
| OD-1 | Cost posting shape: `JournalEntry` with customer-tagged lines, or `Bill` with `CustomerRef` + `BillableStatus=Billable` per line? The `Bill` path is QBO's native billable-expense flow and makes rebilling automatic, but requires a vendor record and changes AP aging. | Eve (QBO Elite) |
| OD-2 | Multicurrency: does the target tenant have QBO multicurrency enabled? If yes, post in USD with `CurrencyRef` and `ExchangeRate`. If no, convert to CAD before posting and record the rate in `allocation_runs`. Both paths must exist. | Eve + first design partner |
| OD-3 | GST/QST treatment on imported digital services from a non-resident supplier when the tenant is a registrant. Self-assessment vs supplier-charged under the simplified regime. **This is not settled in this document and must not be guessed by an agent.** | Eve |
| OD-4 | Chart of accounts mapping: which expense account, which income account for rebilling. Tenant-configurable or opinionated default? | Eve |

Agents must not implement OD-1 through OD-4 by guessing. Where a decision is
missing, build the seam and leave a documented `TODO(OD-n)` with a failing
skipped test.

---

## 4. Invariants

These are non negotiable. Each has a dedicated test file. A PR that weakens one
is rejected.

### INV-1: Idempotent QBO posting

No QBO write may execute without a deterministic idempotency key stored in
`ledger_postings` before the request is sent.

- Key format: `sha256(tenant_id | run_id | billing_dimension_value | posting_type | period)`
  where `billing_dimension_value` is the value of the tenant's single
  `is_billing_dimension` dimension (AD-10)
- Write the `ledger_postings` row with `status = pending` **before** the HTTP call.
- On timeout or ambiguous failure, never blind retry. Re-read QBO by
  `DocNumber` or `PrivateNote` carrying the idempotency key, then reconcile.
- A unique index on `(tenant_id, idempotency_key)` enforces this at the database
  level, not only in application code.

### INV-2: Verifiable ingestion

Every `usage_event` carries an `external_id` originating from the provider,
unique per `(tenant_id, source_id, external_id)`. Events without a provider
origin are typed `manual` and carry the identity of whoever recorded them.

An agent stating its own token usage is not an acceptable source for LLM tokens.

### INV-3: Time-versioned pricing

`model_prices` rows are immutable once referenced by a computed run. Price
changes create a new row with a new `effective_from` and close the previous row's
`effective_to`. Cost computation resolves price by `occurred_at`, never by
"current price".

### INV-4: Period locking

An `allocation_run` with `status = posted` is locked. No `usage_event` with an
`occurred_at` inside a locked period may enter that run. Late-arriving events are
assigned to the current open period with a `late_from_period` reference.

### INV-5: Single billing dimension

Each tenant declares exactly one dimension with `is_billing_dimension = true`,
enforced by a partial unique index. Cost computation and posting always key off
that dimension. Other dimensions are reporting facets only and never affect what
gets posted.

A tenant with zero or two billing dimensions is an invalid configuration and must
fail loudly at run creation, not silently at posting time.

---

## 5. Domain model

Migrations live in `backend/database/migrations`. Models under
`backend/app/Allocation/Models`.

```
cost_sources
  id, tenant_id, provider (enum: anthropic|openai|gateway|manual),
  display_name, credentials_ref (encrypted), is_active,
  last_ingested_at, created_at, updated_at
  unique (tenant_id, provider, display_name)

model_prices
  id, provider, model, token_type (enum: input|output|cache_read|cache_write),
  price_per_million_micros (bigint), currency (char 3),
  effective_from (datetime), effective_to (datetime, nullable),
  created_at
  index (provider, model, token_type, effective_from)
  NO updated_at. Rows are immutable.

usage_events
  id, tenant_id, source_id, external_id (string),
  occurred_at (datetime, indexed), model (string, nullable),
  input_tokens, output_tokens, cache_read_tokens, cache_write_tokens (bigint, default 0),
  dimensions (json)                -- {"client":"acme","project":"checkout","team":"platform"}
  billing_dimension_value (string, nullable, indexed)  -- denormalized from dimensions
                                    -- for join and index performance, written on ingest
  computed_cost_micros (bigint, nullable), currency (char 3),
  raw_payload (json), ingested_at, run_id (nullable, FK)
  unique (tenant_id, source_id, external_id)
  index (tenant_id, billing_dimension_value, occurred_at)
  GIN / generated-column index on dimensions for non-billing dimension filtering
  NO updated_at except run_id assignment. Append-only otherwise.

tenant_dimensions
  id, tenant_id, key (string), label (string),
  is_billing_dimension (bool, default false),
  is_required (bool, default false),
  source_path (string, nullable),  -- where to read it from the provider payload
  created_at, updated_at
  unique (tenant_id, key)
  partial unique (tenant_id) where is_billing_dimension = true

allocation_runs
  id, tenant_id, period_start (date), period_end (date),
  status (enum: draft|computed|reconciled|posted|failed),
  computed_total_micros (bigint), provider_invoice_total_micros (bigint, nullable),
  variance_micros (bigint, nullable), variance_note (text, nullable),
  fx_rate (decimal 18,8, nullable), fx_source (string, nullable),
  locked_at (nullable), created_at, updated_at
  unique (tenant_id, period_start, period_end)

ledger_postings
  id, tenant_id, run_id, idempotency_key (string),
  posting_type (enum: cost|rebill), billing_dimension_value,
  qbo_entity_type (string), qbo_entity_id (string, nullable),
  request_payload (json), response_payload (json, nullable),
  status (enum: pending|posted|failed|reversed),
  posted_at (nullable), reversed_by_posting_id (nullable), created_at
  unique (tenant_id, idempotency_key)
```

---

## 6. Tickets

Each ticket is independently executable. Format is designed for Cursor:
scope, files, acceptance criteria, and the gate that must pass.

---

### Sprint 0: Make the base safe

**AC-000 | Repository hygiene | BLOCKING, do first, human task**

Not a Cursor ticket. Luc executes.

- Set the repo to private.
- Run `git log --all --full-history -- "*.env" "*.env.*"` and a `gitleaks detect
  --log-opts="--all"` scan across full history.
- If any live credential appears, rotate it at the source. Deleting the commit is
  not sufficient.
- Remove the literal dev password from `README.md`, reference the env var name only.

---

**AC-001 | Per-tenant QBO token isolation | BLOCKING**

The current behavior where a timesheet user falls back to the latest administrator
token is a cross-tenant data leak the moment a second tenant exists.

- Scope: `backend/app/Services/QuickBooksService.php`, `quickbooks_tokens` table,
  any controller resolving a token.
- Remove the administrator token fallback entirely.
- Token resolution is scoped to `tenant_id`. A user with no tenant token gets a
  `409 QBO_NOT_CONNECTED`, never someone else's token.
- Add a `tenant_id` foreign key and a unique constraint on `quickbooks_tokens`.

Acceptance:
- Feature test: user in tenant B cannot read, refresh, or use a token belonging
  to tenant A, across every endpoint that touches QBO.
- Feature test: user with no tenant token receives `409` with code
  `QBO_NOT_CONNECTED`.
- Architecture test: no code path outside `QuickBooksService` reads
  `quickbooks_tokens` directly.

Gate: `npm run prepush` green, backend mutation score >= 90 percent on the
touched service.

---

**AC-002 | MySQL parity in local and Docker dev**

- Scope: `docker-compose.yml`, `backend/.env.example`, `docker/`, README.
- Add a MySQL service matching the production version. Default `DB_CONNECTION=mysql`
  in dev.
- Keep in-memory SQLite for the test suite only.
- Update `docker:reset` to drop and recreate the MySQL schema rather than deleting
  a SQLite file.

Acceptance:
- `npm run docker:up:build` then `npm run docker:smoke` pass against MySQL.
- `docs/` updated. No SQLite references remain outside the test configuration.

---

**AC-003 | Bounded context scaffold and architecture guard**

- Create `backend/app/Allocation/{Models,Services,Jobs,Http/Controllers}`.
- Move nothing. Create only the empty structure plus a `README.md` in the folder
  stating the context boundary.
- Add a Pest architecture test:
  - `App\Allocation` must not import `App\Timesheet` or timesheet models.
  - `App\Timesheet` must not import `App\Allocation`.
  - Both may import `App\Services\QuickBooksService` and shared infrastructure.

Acceptance: the architecture test fails when a deliberate cross-import is added,
verified by a temporary commit in the PR description.

---

### Sprint 1: Ingest and compute

**AC-101 | Domain migrations and models**

- Implement the six tables in section 5 exactly, including every unique index
  and the partial unique index on `tenant_dimensions`.
- Models with strict typed properties, no `$guarded = []`.
- `UsageEvent` and `LedgerPosting` override `update()` to throw unless the change
  is on the explicitly allowlisted columns (`run_id`, `computed_cost_micros`,
  `status`, `qbo_entity_id`, `response_payload`, `posted_at`).
- Factories for all six models.

Acceptance:
- Unit test: attempting a disallowed update on `UsageEvent` throws.
- Unit test: inserting a duplicate `(tenant_id, source_id, external_id)` raises a
  database integrity exception, not an application-level check only.
- Unit test (INV-5): a tenant with two `is_billing_dimension` rows cannot be
  persisted. The database rejects it, not only the application.
- Unit test: `billing_dimension_value` on a `UsageEvent` always matches the value
  in `dimensions` for the tenant's billing dimension key.
- Migration rolls back cleanly.

---

**AC-102 | Tenant dimension configuration**

- CRUD service for `tenant_dimensions`. No public API yet, platform operator only.
- `DimensionResolver::extract(array $providerPayload, Tenant $t): array` reading
  each dimension from its `source_path`.
- Validation: exactly one `is_billing_dimension`, enforced at the service layer
  and by the partial unique index.
- Seeder with a sensible default set (`client`, `project`, `task`) where `client`
  is the billing dimension, so a new tenant is usable immediately.

Acceptance:
- Unit test: extraction from a nested `source_path` (`metadata.client_id`).
- Unit test: a missing `is_required` dimension raises, a missing optional one
  yields null without raising.
- Feature test: creating a second billing dimension fails at both layers.

---

**AC-103 | Price catalog with temporal resolution**

- Service `PriceResolver::resolve(provider, model, tokenType, occurredAt): ModelPrice`.
- Throws a typed `PriceNotFoundException` when no row covers `occurredAt`. Never
  falls back to the nearest price.
- Seeder with current published Anthropic and OpenAI prices.
- Prices live in a seeder, not in code constants.

Acceptance:
- Unit test: an event dated before any `effective_from` throws.
- Unit test: an event dated in an overlapping window resolves to the row whose
  `effective_from` is latest and still <= `occurredAt`.
- Unit test (INV-3): closing a price and adding a new one does not change the
  computed cost of an event dated in the old window.

---

**AC-104 | Anthropic usage ingestion**

- Job `IngestAnthropicUsage` pulling from the provider admin API for a date range.
- Idempotent by `external_id`. Re-running the same range inserts zero duplicates.
- Dimension extraction via `DimensionResolver` (AC-102). Events missing a required
  dimension are ingested with null values, flagged, and counted separately. They
  are never dropped and never guessed (P2).
- Events with no billing dimension value appear in the run as an explicit
  "unattributed" bucket with its own total.
- Store the full provider response in `raw_payload`.
- Rate limit aware with exponential backoff. Partial failure leaves already
  ingested events committed.

Acceptance:
- Feature test with a recorded fixture: 3 pages of usage ingest into N events.
- Feature test: re-running the identical range produces zero new rows.
- Feature test: a mid-run HTTP 429 followed by success ingests the full set.
- Behat scenario in `backend/features/api/` covering the unattributed event path,
  asserting the unattributed total is surfaced and not silently absorbed.

Note: build against a fixture, not the live API. The exact response shape must be
confirmed against current Anthropic admin API documentation before merge. If the
shape differs, adjust the fixture and the mapper, not the invariants.

---

**AC-105 | Cost computation**

- Service `CostCalculator::compute(UsageEvent): int` returning micros.
- Formula per token type: `tokens * price_per_million_micros / 1_000_000`, with
  banker's rounding applied once at the end, not per token type.
- Writes `computed_cost_micros` and `currency` back to the event.
- Command `allocation:compute {tenant} {period}` computing all unassigned events
  in a period.
- Aggregation is grouped by the tenant's billing dimension (INV-5). Non-billing
  dimensions are available as reporting facets but never change the totals.

Acceptance:
- Unit test: a known token vector produces an exact expected micros value.
- Unit test: computing twice is stable and does not double.
- Property test: total of per-event costs equals the sum computed in one pass over
  the period, for 1000 random events.

---

**GATE 1: commercial validation | BLOCKING for Sprint 2 and beyond**

Sprints 2 through 5 do not start until:

- 15 discovery calls completed with owners of agencies, dev shops, or integrators
  that resell AI to their clients.
- At least 5 confirm they track AI cost per client in a spreadsheet or not at all.
- At least 2 confirm they rebill AI usage to clients.
- At least 1 signed paid design partner agreement.

If the gate fails, Sprint 1 still delivers a working internal tool: Luc's own cost
per client, per mandate. That alone justifies the sprint. Stop there.

---

### Sprint 2: Reconcile

**AC-201 | Allocation runs**

- `AllocationRunService::open(tenant, periodStart, periodEnd)` creating a draft run
  and claiming all unassigned events in the window.
- `close()` transitioning draft to computed, summing `computed_total_micros`.
- INV-4 enforcement: a locked period rejects new events. Late events land in the
  current open run with `late_from_period` populated in `raw_payload`.

Acceptance:
- Feature test: an event dated inside a posted run's period does not enter it.
- Feature test: the same event lands in the open run with a traceable reference.

---

**AC-202 | Provider invoice reconciliation**

- Command `allocation:reconcile {run} --invoice-total= --currency=`.
- Computes `variance_micros` and blocks transition to `reconciled` when variance
  exceeds a tenant-configured threshold (default 2 percent) unless
  `variance_note` is supplied.
- FX: when the provider invoice currency differs from the tenant home currency,
  record `fx_rate` and `fx_source` on the run. The rate is stored, never
  recomputed later.

Acceptance:
- Feature test: 3 percent variance without a note blocks the transition.
- Feature test: same variance with a note allows it and persists the note.
- Feature test: the stored `fx_rate` is used for posting, not a fresh lookup.

---

### Sprint 3: Post to QBO

**AC-301 | LedgerPoster with idempotency**

The single most important ticket in this SOW. Read INV-1 before writing code.

- `LedgerPoster::post(AllocationRun, PostingPlan): Collection<LedgerPosting>`.
- Writes every `ledger_postings` row with `status = pending` inside a database
  transaction **before** any HTTP call leaves the process.
- Idempotency key format per INV-1. Unique index enforces it.
- The key is echoed into the QBO entity's `PrivateNote` so it is recoverable from
  QBO alone.
- On ambiguous failure (timeout, 5xx, connection reset): query QBO for an entity
  carrying the key before any retry. Never blind retry.
- Unattributed cost (null billing dimension value) never posts per client. It
  posts as a single residual line with the literal key segment `__unattributed__`,
  so the idempotency key stays deterministic.
- Implements OD-1 behind a strategy interface with both `JournalEntryStrategy`
  and `BillStrategy`, one of which stays a documented stub until OD-1 is decided.
- Implements OD-2 with both a `MulticurrencyPoster` and a `ConvertedPoster`.

Acceptance:
- Feature test: posting the same run twice creates the QBO entity exactly once.
- Feature test: a simulated timeout followed by a retry finds the existing entity
  by key and does not create a second one.
- Feature test: a mid-run failure on posting 4 of 7 leaves 3 posted, 1 failed,
  3 pending, and the run in `failed` status with no orphan QBO entities.
- Feature test: a run containing unattributed cost posts exactly one residual
  line, and posting twice does not duplicate it.
- Behat scenario covering the full happy path end to end.

Gate: backend mutation score >= 95 percent on `LedgerPoster` and its strategies.
This zone is stricter than the repo default because it writes to a third party's
books.

---

**AC-302 | Reversal instead of update**

- `LedgerPoster::reverse(LedgerPosting)` creating a counter-entry, setting the
  original to `reversed` and linking `reversed_by_posting_id`.
- No code path modifies a posted QBO entity.

Acceptance:
- Feature test: reversal creates a new QBO entity, the original stays untouched
  in QBO, and both are linked in `ledger_postings`.
- Architecture test: no `update` or `delete` call against a QBO accounting entity
  exists outside `reverse()`.

---

### Sprint 4: Rebill and UI

**AC-401 | Markup rebilling**

- Markup configuration per tenant, per billing dimension value: percentage or
  fixed uplift, with a tenant-level default.
- Generates billable lines on a QBO `Invoice`, or relies on QBO billable expense
  flow if OD-1 resolves to `BillStrategy`.
- Posting type `rebill`, same idempotency guarantees as AC-301.

Acceptance:
- Feature test: a 40 percent markup on a known cost produces the exact expected
  invoice line total in minor units.
- Feature test: rebilling a run twice produces one invoice.

---

**AC-402 | Admin UI**

- New section in `apps/admin`: run list, run detail with per client breakdown,
  variance banner, posting status per client, reversal action with confirmation.
- Extend `packages/api-client` with the allocation endpoints.
- Quality bar for this ticket is the repo default (85 percent coverage), not the
  ledger bar. Do not gold-plate the UI.

Acceptance:
- Component tests for run list and run detail.
- Stryker frontend thresholds met at repo default.

---

### Sprint 5: MCP for non-telemetry expenses

**AC-501 | MCP server, single write tool**

- Exposes exactly one write tool: `record_expense`.
- Creates a `usage_event` with `source.provider = manual`, carrying the calling
  agent identity, an amount in micros, and client, project, task refs.
- Read tools are allowed (`list_runs`, `get_run_summary`).
- **No tool writes to QBO.** No tool creates, modifies, or posts a ledger entry.
- Rejects any payload claiming LLM token counts, per INV-2, with a clear error.

Acceptance:
- Feature test: `record_expense` creates a manual event with agent identity.
- Feature test: a payload with `input_tokens` is rejected with code
  `TOKENS_NOT_ACCEPTED_VIA_MCP`.
- Architecture test: the MCP namespace cannot import `LedgerPoster`.

---

## 7. Quality gates by zone

The repo default is strong. This raises it where money moves and relaxes it
where it does not.

| Zone | Coverage | Mutation | Rationale |
|------|----------|----------|-----------|
| `App\Allocation\Services\LedgerPoster` and strategies | 100 percent | >= 95 percent | Writes to a third party's general ledger |
| `CostCalculator`, `PriceResolver`, `AllocationRunService` | 95 percent | >= 90 percent | Determines the numbers |
| Ingestion jobs | 90 percent | >= 90 percent | Repo default plus |
| Allocation controllers | 85 percent | repo default | Repo default |
| `apps/admin` allocation UI | 85 percent | repo default | Do not gold-plate |

Every ticket must pass `npm run prepush` before the PR opens.

---

## 8. Security requirements

- Provider credentials encrypted at rest with `APP_KEY`, never logged, never
  returned by any API response. Same pattern as `quickbooks_tokens`.
- `raw_payload` may contain prompt metadata. It must not contain prompt or
  completion content. Strip on ingest, verified by a test.
- Every `ledger_postings` row is an audit record. No hard delete, ever.
- Tenant isolation verified by a dedicated Pest test group `@tenant-isolation`
  covering every allocation endpoint.
- Before the first paying tenant: move off shared hosting. Financial data of
  Quebec SMBs on US shared hosting is an objection you will lose. Canadian
  region required (OVHcloud Beauharnois, Azure Canada East, or GCP Montreal).

---

## 9. Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Duplicate posting into a client's books | Product ending | INV-1, database-level unique index, mutation gate at 95 percent |
| Provider API response shape differs from fixture | Rework in AC-103 | Build the mapper behind an interface, fixture-driven, confirm against live docs before merge |
| OD-1 through OD-4 guessed by an agent | Wrong accounting, silent | Seams and skipped failing tests, never a default guess |
| Gate 1 fails and work continues anyway | Months spent, no revenue | Gate is written into the SOW. Sprint 1 alone is independently useful |
| Scope creep into observability or orchestration | Loss of focus, competes with funded teams | Section 2 non-goals are binding |
| Scope creep into enterprise segment (100+ devs, seat-based tools, NetSuite) | Loses the QBO moat, frontal competition with funded FinOps vendors | Non-goals plus `product-context.md` section 2 sizing table |
| Dimension model over-generalized into a query builder | Complexity with no buyer | Dimensions are flat key/value strings only. No nesting, no hierarchy, no computed dimensions |
| Shared hosting queue drain under real volume | Missed or delayed postings | AC-002 plus hosting migration before first paying tenant |

---

## 10. Sequencing summary

```
AC-000  human, blocking, today
AC-001 ─┐
AC-002  ├─ Sprint 0, can run in parallel after AC-000
AC-003 ─┘
AC-101 → AC-102 → AC-103 → AC-104 → AC-105   Sprint 1, strictly sequential
        ══════ GATE 1 ══════
AC-201 → AC-202                          Sprint 2
AC-301 → AC-302                          Sprint 3
AC-401 → AC-402                          Sprint 4
AC-501                                   Sprint 5
```

Realistic effort with the existing chassis: 6 to 10 weeks of focused work.
Sprint 1 alone is 1.5 to 2 weeks and is worth doing regardless of Gate 1.

---

## 11. Instructions for the executing agent

- Read `AGENTS.md` and the relevant `.cursor/rules/*.mdc` before touching code.
- One ticket per pull request. Never batch tickets.
- Never weaken an invariant in section 4 to make a test pass. If an invariant
  blocks progress, stop and escalate.
- Never implement OD-1 through OD-4 by guessing. Build the seam, add a skipped
  failing test, and reference the OD id in the PR description.
- No em dash in any code comment, commit message, UI copy, or documentation.
- If the provider API response shape does not match the fixture, stop and report.
  Do not adapt the invariants to fit a guess about the API.
