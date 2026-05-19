# Proposal: User Access Audit Log

## Intent

The application has no audit trail: no record of who performed authentication actions (login, logout, failed attempts, registration, password resets) nor who modified users, roles, or permissions. Spatie Permission events are disabled (`events_enabled = false`), so even programmatic tracking is impossible. This change adds a complete audit log with a filterable admin UI.

## Scope

### In Scope
- Install `spatie/laravel-activitylog` and configure it
- Log all Fortify auth events: login, logout, failed login, registration, password reset, 2FA enable/disable, email verification
- Log all admin CRUD actions on User, Role, Permission models
- Enable Spatie Permission events and create listeners for role/permission assignment changes
- Create admin UI page (Livewire SFC + Flux) to view, filter, and search audit log entries
- Configure automatic log cleanup (`clean_after_days: 90`) via scheduled command
- Protect sensitive attributes from being logged (passwords, tokens, secrets)

### Out of Scope
- Real-time audit notifications or alerts
- Export audit log to CSV/PDF
- Audit log for model changes outside admin panel (future extensibility point)
- Custom retention policies beyond days-based cleanup

## Capabilities

### New Capabilities
- `audit-log`: Core audit infrastructure — migration, model, Spatie Activitylog config, trait wiring on User/Role/Permission models
- `auth-audit`: Logging of Fortify authentication events via listeners/observers (login, logout, failed, register, reset, 2FA, verification)
- `admin-audit-ui`: Livewire page at `/admin/audit-log` with Flux table, text filter, date range, action type dropdown, and pagination

### Modified Capabilities
None — no existing specs in `openspec/specs/`.

## Approach

Hybrid (exploration Option 4): Spatie Activitylog for model-level changes (User, Role, Permission CRUD) + Spatie Permission native events for relationship changes (assignRole, syncPermissions). This gives maximum coverage with minimal custom code. Auth events are logged via listeners registered in `AppServiceProvider` tied to Fortify events or manual `activity()->log()` calls in Fortify Actions. Logging is synchronous by default; queueable if performance warrants it post-launch.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `composer.json` | New | Add `spatie/laravel-activitylog` |
| `config/permission.php` | Modified | Set `events_enabled => true` |
| `config/activitylog.php` | New | Publish and configure defaults + sensitive attr exclusion |
| `database/migrations/` | New | Activitylog migration (vendor publish) |
| `app/Models/User.php` | Modified | Add `LogsActivity` trait |
| `app/Observers/` | New | `UserObserver`, `RoleObserver`, `PermissionObserver` |
| `app/Listeners/` | New | Listeners for Spatie Permission events (`RoleAttached`, etc.) |
| `app/Actions/Fortify/` | Modified | Inject `activity()->log()` in `CreateNewUser`, `ResetUserPassword` |
| `app/Providers/` | Modified | `AppServiceProvider` — register observers, listeners, and `Gate::before` for Super Admin |
| `routes/admin.php` | Modified | Add `GET /admin/audit-log` route |
| `resources/views/pages/admin/` | New | `audit-log.blade.php` (Livewire SFC) |
| `tests/Feature/Admin/` | New | Audit log page tests, auth event logging tests |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Table growth (unbounded `activity_log`) | High | `clean_after_days: 90` config + scheduled `activitylog:clean` weekly |
| Sensitive data in logs | Medium | Exclude `password`, `two_factor_secret`, `remember_token` in `default_except_attributes` |
| Performance on high-traffic auth | Low | Logging is lightweight; queueable listeners deferred until needed |
| Existing tests break from activity side-effects | Medium | Use `RefreshDatabase`; update affected assertions |

## Rollback Plan

1. Remove `spatie/laravel-activitylog` via `composer remove`
2. Delete `config/activitylog.php` and the activitylog migration
3. Revert `config/permission.php`: set `events_enabled => false`
4. Remove trait usage from `User` model
5. Delete `app/Observers/` and `app/Listeners/` directories created by this change
6. Remove `/admin/audit-log` route and page
7. Rollback DB migration: `php artisan migrate:rollback --step=1` (if immediately after deploy)

## Dependencies
- `spatie/laravel-activitylog` ^4 — needs Composer install
- Spatie Permission events must remain `events_enabled = true` once enabled

## Success Criteria
- [ ] Auth events (login, logout, failed attempt, register, reset, 2FA, verification) appear in `activity_log` table with correct causer and properties
- [ ] Admin CRUD on User/Role/Permission creates audit entries with old/new values
- [ ] Spatie Permission events (assignRole, syncPermissions) produce audit entries via listeners
- [ ] Audit log UI at `/admin/audit-log` shows entries with working text search, date range, and action type filters
- [ ] `clean_after_days` purge works via scheduled command
- [ ] All existing tests pass; new tests cover audit log page access and auth event logging
