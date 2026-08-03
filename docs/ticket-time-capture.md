# Ticket time capture

**Status:** Phase 1 shipped (manual ticket key). Phase 2 scaffold (Jira / Linear picker) behind config.

## Employee reality vs QBO ledger

Developers work on **tickets**. QuickBooks Online has no ticket entity on `TimeActivity`. Ellr therefore captures a structured ticket on the local write path and maps it into QBO at sync time.

```
Ticket (Ellr) → client / project / service (QBO) → TimeActivity.Description includes [KEY]
```

## Phase 1: structured ticket key

| Field | Storage | Notes |
|-------|---------|-------|
| `ticket_key` | `active_time_sessions`, `time_entries` | e.g. `PROJ-123` |
| `ticket_source` | same | `manual` \| `jira` \| `linear` |
| `ticket_url` | same | optional deep link |
| `ticket_title` | same | optional cached title |

- Timer UI: ticket text field above description
- Approval UI: shows ticket key (and link when URL is set)
- QBO sync: `TimeEntryQboDescription` prefixes Description as `[PROJ-123] notes`
- Snapshots stay QBO mirrors (no ticket columns)

## Phase 2: Jira / Linear picker (scaffold)

| Piece | Location |
|-------|----------|
| Config | `backend/config/integrations.php`, `.env.example` |
| Status / search API | `GET /api/integrations/tickets/status`, `GET /api/integrations/tickets?q=` |
| Service | `TicketIntegrationService` (search returns `integration_disabled` until OAuth) |
| Admin UI | Integrations tab → Ticket trackers panel |
| Health flags | `ticket_integrations.jira_enabled` / `linear_enabled` |

Next implementation steps for OAuth connect, token storage, and live issue search belong in a follow-up PR.
