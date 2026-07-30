# Time entry approval (Phase 3)

Supervisor approval is required before employee time entries are synchronized to QuickBooks Online (QBO).

## Architecture overview

```mermaid
flowchart LR
  subgraph writePath [Write path]
    Timer[Timer log / manual create]
    Local[(time_entries)]
    Timer --> Local
  end

  subgraph approval [Approval]
    Supervisor[Supervisor or admin]
    Review{Approve?}
    Local --> Supervisor
    Supervisor --> Review
    Review -->|Reject| Rejected[(status: rejected)]
    Review -->|Approve| QBO[QBO TimeActivity Add]
  end

  subgraph readPath [Read path after sync]
    QBO --> Snapshots[(time_activity_snapshots)]
  end
```

| Layer | Responsibility |
|-------|----------------|
| `time_entries` | **Write model**: all new entries start here as `pending` |
| `time_activity_snapshots` | **Read model**: QBO-synced activities (unchanged Phase 2 design) |
| `users.supervisor_id` | Routes pending entries to the employee's supervisor |
| `TimeEntryApprovalService` | Approve pushes to QBO; reject keeps the row local only |

## Status lifecycle

| Status | Editable by employee | Synced to QBO |
|--------|---------------------|---------------|
| `pending` | Yes | No |
| `approved` | No | Yes (`qbo_id` set) |
| `rejected` | No (deletable) | Never |

## API routes

### Employee (`auth:sanctum`, `organization`, `verified.email`)

| Method | Route | Action |
|--------|-------|--------|
| `GET` | `/api/time-entries` | List own entries (all statuses) |
| `POST` | `/api/time-entries` | Create pending entry |
| `PATCH` | `/api/time-entries/{id}` | Update pending entry |
| `DELETE` | `/api/time-entries/{id}` | Delete pending/rejected entry |

`POST /api/time-activities` and `POST /api/time-tracker/log` also create **pending** local entries (backward-compatible paths).

### Reviewer (supervisor of employee, or org admin)

| Method | Route | Action |
|--------|-------|--------|
| `GET` | `/api/time-entry-approvals` | List pending entries for direct reports |
| `POST` | `/api/time-entry-approvals/{id}/approve` | Approve and sync to QBO |
| `POST` | `/api/time-entry-approvals/{id}/reject` | Reject (stays local) |

### Administrator (`middleware: admin`)

| Method | Route | Action |
|--------|-------|--------|
| `GET` | `/api/admin/time-entry-approvals` | List all pending entries in org |
| `POST` | `/api/admin/time-entry-approvals/{id}/approve` | Approve and sync |
| `POST` | `/api/admin/time-entry-approvals/{id}/reject` | Reject |
| `PATCH` | `/api/admin/users/{user}/supervisor` | Assign supervisor |

## Authorization rules

- Employees cannot review their own entries.
- Supervisors review entries where `employee.supervisor_id = reviewer.id`.
- Org administrators (`is_admin`) can review any pending entry in their organization.
- Rejected entries are never sent to QBO.

## UI surfaces

| App | Screen | Behavior |
|-----|--------|----------|
| Timesheet | Recent entries | Shows approval status (`pending`, `approved`, `rejected`) |
| Admin | Time approvals tab | List, approve, reject pending entries |

## Supervisor assignment

Assign a supervisor per timesheet user via `PATCH /api/admin/users/{user}/supervisor` with `{ "supervisor_id": <user_id> }` or `null` to clear.

Supervisors must belong to the same organization. An employee cannot be their own supervisor.

## Legacy QBO entries

Entries that exist only in QuickBooks (or predate the approval workflow) are included in `GET /api/time-entries` with:

- `status: approved`
- `list_id: qbo:{qbo_id}`
- no local `id`

They are excluded when a local `time_entries` row already references the same `qbo_id`.

## Related code

| Piece | Location |
|-------|----------|
| Model | `backend/app/Models/TimeEntry.php` |
| Enum | `backend/app/Enums/TimeEntryStatus.php` |
| Services | `TimeEntryService`, `TimeEntryApprovalService`, `TimeEntryAuthorizationService` |
| Controllers | `TimeEntryController`, `TimeEntryApprovalController`, `AdminUserSupervisorController` |
| API client | `packages/api-client/src/timeEntries.ts` |
| Admin UI | `apps/admin/src/components/TimeEntryApprovalsPanel.tsx` |
| Phase 2 sync | `docs/quickbooks-time-activity-sync.md` |
