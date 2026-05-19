# admin-audit-ui Specification

## Purpose

Admin interface at `/admin/audit-log` to view, filter, search, and paginate all audit log entries. Built as a Livewire SFC with Flux UI components. Access restricted to users with the `auditoria.ver` permission.

## Requirements

| # | Requirement | Strength | Notes |
|---|-------------|----------|-------|
| R1 | Page MUST be at route `/admin/audit-log` | MUST | Livewire SFC, `GET` |
| R2 | Access MUST require `auditoria.ver` permission | MUST | Middleware; Super Admin bypass via Gate |
| R3 | Table MUST display: timestamp, causer name, action, subject type+ID, properties summary | MUST | Most recent first |
| R4 | Text search MUST filter by causer name, subject type, or description | MUST | `wire:model.live` debounced |
| R5 | Date range filter MUST accept start and end dates | MUST | `whereBetween('created_at', ...)` |
| R6 | Action type dropdown MUST filter by `log_name` (`auth`, `default`) or `description` | MUST | Flux `<flux:select>` with options |
| R7 | Pagination MUST show max 25 entries per page | MUST | Activitylog `simplePaginate(25)` |
| R8 | Unauthorized access MUST return HTTP 403 | MUST | Middleware enforces before component mounts |
| R9 | UI MUST use Flux components (table, input, select, badge, pagination) | SHOULD | Consistent with existing admin pages |
| R10 | Properties column SHOULD show a truncated summary with expand/collapse | SHOULD | Prevents wide columns; toggle full JSON |

### Scenario: Admin visits the audit log page

- GIVEN an authenticated user with `auditoria.ver` permission
- WHEN the user navigates to `/admin/audit-log`
- THEN the page loads showing audit entries in a table ordered by most recent first
- AND each row shows timestamp, causer, action, subject, and properties summary

### Scenario: Admin searches audit entries

- GIVEN the audit log page is loaded with entries
- WHEN the admin types a username or email in the search input
- THEN the table updates to show only entries whose causer name or subject type matches the search term

### Scenario: Admin filters by date range

- GIVEN the audit log page is loaded
- WHEN the admin selects a start date and an end date
- THEN only entries created within that date range are displayed

### Scenario: Admin filters by action type

- GIVEN the audit log page is loaded with mixed entries (auth, model changes)
- WHEN the admin selects `auth` from the action type dropdown
- THEN only entries with `log_name=auth` are displayed
- AND entries with `log_name=default` are hidden

### Scenario: Unauthorized user is denied

- GIVEN an authenticated user without `auditoria.ver` permission
- WHEN the user navigates to `/admin/audit-log`
- THEN the server returns HTTP 403
- AND no audit data is exposed

### Scenario: Pagination navigates between pages

- GIVEN more than 25 audit entries exist
- WHEN the audit log page loads
- THEN only 25 entries are visible
- AND pagination controls appear at the bottom
- WHEN the user clicks page 2
- THEN the next 25 entries are displayed
