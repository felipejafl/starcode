# audit-log Specification

## Purpose

Core audit infrastructure: model-level logging for User, Role, Permission CRUD; role-permission assignment tracking via Spatie Permission events; sensitive attribute exclusion; and automatic log retention.

## Requirements

| # | Requirement | Strength | Notes |
|---|-------------|----------|-------|
| R1 | Activitylog MUST be installed and configured | MUST | `spatie/laravel-activitylog`, migration, config published |
| R2 | User model MUST log `created`, `updated`, `deleted` | MUST | `LogsActivity` trait on `User` |
| R3 | Role model MUST log `created`, `updated`, `deleted` | MUST | `LogsActivity` via `app/Models/Role` override |
| R4 | Permission model MUST log `created`, `updated`, `deleted` | MUST | `LogsActivity` via `app/Models/Permission` override |
| R5 | Spatie Permission events MUST be enabled | MUST | `config/permission.php`: `events_enabled => true` |
| R6 | Role-to-user assignment/removal MUST produce audit entries | MUST | Listeners for `RoleAttached`, `RoleDetached` |
| R7 | Permission-to-role assignment/removal MUST produce audit entries | MUST | Listeners for `PermissionAttached`, `PermissionDetached` |
| R8 | Sensitive attributes MUST NOT appear in log properties | MUST | Exclude `password`, `two_factor_secret`, `remember_token`, `two_factor_recovery_codes` |
| R9 | Log entries MUST capture causer (authenticated user), subject (model), action, timestamp, IP, user agent | MUST | Activitylog default columns |
| R10 | Log entries older than 90 days SHALL be purged | SHALL | `activitylog:clean` command, `clean_after_days: 90` |
| R11 | Purge command SHALL run weekly via scheduler | SHALL | `$schedule->command('activitylog:clean')->weekly()` |

### Scenario: Admin creates a user via admin panel

- GIVEN an authenticated admin user with `usuarios.crear` permission
- WHEN the admin submits the create-user form in the admin panel
- THEN an activity entry is recorded with `subject_type=User`, `description=created`
- AND the entry's `causer_id` matches the admin's user ID
- AND the `properties` include the new user's `name`, `email` but NOT `password`

### Scenario: Admin updates user roles

- GIVEN an authenticated admin with `usuarios.actualizar` permission
- WHEN the admin changes a user's assigned roles via admin panel
- THEN a `RoleAttached` or `RoleDetached` event fires
- AND the listener records an activity entry with subject=User, description="assigned role X"

### Scenario: Password hash is excluded from update log

- GIVEN `password` is in Activitylog's excluded attributes
- WHEN an admin updates a user's password via admin panel
- THEN the audit entry's properties do NOT contain `password`, `password_confirmation`, or any hash
- AND other changed attributes (name, email) appear normally

### Scenario: Scheduled cleanup removes old entries

- GIVEN the activity_log table contains entries older than 90 days
- WHEN the scheduled `activitylog:clean` command executes
- THEN entries older than 90 days are deleted
- AND entries younger than 90 days are preserved

### Scenario: Events fire on programmatic role changes

- GIVEN `config/permission.php` has `events_enabled => true`
- WHEN `$user->assignRole('writer')` is called
- THEN `RoleAttached` event is dispatched
- AND the attached listener records an audit entry with causer=auth()->user()
