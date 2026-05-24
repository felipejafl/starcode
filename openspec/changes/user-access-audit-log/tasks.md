# Tasks: User Access Audit Log

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 1,200–1,400 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 → PR 4 |
| Delivery strategy | ask-always |
| Chain strategy | feature-branch-chain |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | PR | Base | ~Lines |
|------|------|-----|------|--------|
| 1 | Package, config, custom models, migration | PR 1 | feature/audit-log | 310 |
| 2 | Auth + permission event listeners, Fortify actions | PR 2 | PR 1 branch | 380 |
| 3 | Admin audit-log Livewire SFC + route + seeder | PR 3 | PR 2 branch | 260 |
| 4 | Feature tests: access control, auth events, model CRUD | PR 4 | PR 3 branch | 280 |

## Phase 1: Audit Infrastructure (PR 1)

- [x] 1.1 `composer require spatie/laravel-activitylog` and commit `composer.json` (version pinned to ^5.0)
- [x] 1.2 `vendor:publish` activitylog migration + config; run `php artisan migrate` (migration ran: 2026_05_19_211505)
- [x] 1.3 Edit `config/activitylog.php`: `default_except_attributes` (password, two_factor_secret, remember_token, two_factor_recovery_codes); `delete_records_older_than_days: 90`
- [x] 1.4 Edit `config/permission.php`: `events_enabled => true`; set `models.role` → `App\Models\Role` and `models.permission` → `App\Models\Permission`
- [x] 1.5 Create `app/Models/Role.php` extending `Spatie\Permission\Models\Role` with `LogsActivity` + `getActivitylogOptions()`
- [x] 1.6 Create `app/Models/Permission.php` extending `Spatie\Permission\Models\Permission` with `LogsActivity` + `getActivitylogOptions()`
- [x] 1.7 Update `Spatie\Permission\Models\Role` → `App\Models\Role` imports in `⚡users.blade.php`, `⚡roles.blade.php`, `⚡permissions.blade.php`, and `RolesAndPermissionsSeeder.php`
- [x] 1.8 Add `use LogsActivity` + `getActivitylogOptions()` to `app/Models/User.php`
- [x] 1.9 Add `$schedule->command('activitylog:clean')->weekly()` in `routes/console.php`

## Phase 2: Event Logging (PR 2)

- [x] 2.1 Create auth listeners in `app/Listeners/`: `LogSuccessfulLogin` (`Login`), `LogFailedLogin` (`Failed`), `LogLogout` (`Logout`), `LogLockout` (`Lockout`), `LogTwoFactorEnabled`, `LogTwoFactorDisabled`, `LogEmailVerified` (`Verified`) — 7 files
- [x] 2.2 Create permission listeners in `app/Listeners/`: `LogRoleAttached`, `LogRoleDetached`, `LogPermissionAttached`, `LogPermissionDetached` — 4 files
- [x] 2.3 Inject `activity()->log('registered', ...)` in `app/Actions/Fortify/CreateNewUser.php` after `User::create()`
- [x] 2.4 Inject `activity()->log('password_reset', ...)` in `app/Actions/Fortify/ResetUserPassword.php` after `->save()`
- [x] 2.5 Register all 11 listeners via `Event::listen()` in `app/Providers/AppServiceProvider.php::boot()`

## Phase 3: Admin Audit UI (PR 3)

- [x] 3.1 Add `auditoria.ver` to permissions array in `database/seeders/RolesAndPermissionsSeeder.php`
- [x] 3.2 Add `Route::livewire('audit-log', 'pages::admin.audit-log')->middleware('can:auditoria.ver')->name('audit-log.index')` in `routes/admin.php` inside the existing admin group
- [x] 3.3 Create `resources/views/pages/admin/⚡audit-log.blade.php` Livewire SFC: text search (`wire:model.live`), date range inputs, action-type `<flux:select>`, Flux table (timestamp, causer, action, subject, properties summary), pagination 25/page, `<flux:modal>` for full properties JSON

## Phase 4: Tests (PR 4)

- [x] 4.1 `tests/Feature/Admin/AuditLogAccessTest.php` — 403 without `auditoria.ver`, 200 with; renders sidebar entry
- [x] 4.2 `tests/Feature/Auth/AuthAuditTest.php` — login/logout/failed/register/reset/2FA/verify each produce activity entry per spec scenarios
- [x] 4.3 `tests/Feature/Admin/ModelAuditTest.php` — User/Role/Permission CRUD entries; role/permission assignment entries; password exclusion from properties
- [x] 4.4 Run `php artisan test --compact`; verify all existing + new pass
- [x] 4.5 Run `vendor/bin/pint --dirty --format agent`

## Phase 5: Verify Gaps (post-verify fixes)

- [x] 5.1 Add cleanup/retention test: `activitylog:clean` removes entries older than 90 days, preserves recent
- [x] 5.2 Add pagination page-2 navigation test: >25 entries, assert page 2 shows correct subset
- [x] 5.3 Fix filter overlap: wrap search input in `flux:field` with `sr-only` label, increase gap to `gap-4`
- [x] 5.4 Create apply-progress artifact with TDD Cycle Evidence table
