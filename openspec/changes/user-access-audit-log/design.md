# Design: User Access Audit Log

## Technical Approach

Implement a hybrid audit system using `spatie/laravel-activitylog` for model-level CRUD tracking (User, Role, Permission) combined with Spatie Permission native events for role/permission relationship changes. Fortify auth events are captured via Laravel's built-in auth event listeners (Login, Logout) and Fortify-specific events (Registered, PasswordReset, TwoFactor*, Verified). The admin UI is a Livewire SFC at `/admin/audit-log` using Flux components for filtering and pagination. All three specs (`audit-log`, `auth-audit`, `admin-audit-ui`) are covered.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| Audit package | Spatie Activitylog vs custom observers | Activitylog gives structured queries, cleanup commands, morph relations out-of-the-box; custom requires building everything | **Spatie Activitylog** — same vendor as Permission, proven |
| Custom Role/Permission models | Override vs vendor defaults | Override gives trait placement control; vendor defaults means no extra classes | **Override** — create `app/Models/Role.php` and `app/Models/Permission.php` extending vendor models to attach `LogsActivity` trait cleanly |
| Auth event logging | Fortify Actions injection vs event listeners | Actions require modifying existing classes; listeners are decoupled and catch all auth paths | **Listeners** for Laravel auth events (Login, Logout, Failed) + direct `activity()->log()` in Fortify Actions (CreateNewUser, ResetUserPassword) where no event fires at the right point |
| Permission events | Enable `events_enabled` + listeners vs manual logging | Events fire automatically on assignRole/syncRoles; manual requires touching every call site | **Enable events** — set `events_enabled => true` in `config/permission.php`, register 4 listeners |
| Audit UI | Livewire SFC vs MFC vs controller+blade | SFC matches existing admin pages pattern (`⚡users.blade.php`); consistent conventions | **Livewire SFC** — `⚡audit-log.blade.php` |
| Log storage | Sync vs queue | Sync is simpler, adequate for admin panel traffic; queue adds complexity | **Sync** — queue later if performance warrants |
| Sensitive data exclusion | Activitylog config vs per-model `$except` | Config-level exclusion applies globally; per-model is redundant | **Global config** — `default_except_attributes` in `config/activitylog.php` |

## Data Flow

### Auth Event Flow (Login Example)

```
User submits /login
  └─→ Fortify::authenticate()
       └─→ AttemptToAuthenticate action
            ├─→ credentials valid → event(Login) ──→ LogSuccessfulLogin listener
            │                                         └─→ activity()->log('login', log_name='auth')
            └─→ credentials invalid → event(Failed) ──→ LogFailedLogin listener
                                                          └─→ activity()->log('failed_login', causer=null)
```

### Model CRUD Flow (User Create via Admin Panel)

```
Admin submits user form (Livewire SFC)
  └─→ User::create([...])
       └─→ Eloquent created event
            └─→ LogsActivity trait (on User model)
                 └─→ activity()->log() → activity_log table
                      (password excluded via default_except_attributes)
```

### Role Assignment Flow

```
Admin calls $user->syncRoles(['writer'])
  └─→ Spatie Permission fires RoleAttachedEvent / RoleDetachedEvent
       └─→ LogRoleAttached / LogRoleDetached listener
            └─→ activity()->log("assigned role X" / "removed role X", log_name='default')
```

### Cleanup Flow

```
Scheduler (weekly)
  └─→ activitylog:clean command
       └─→ DELETE FROM activity_log WHERE created_at < NOW() - 90 days
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `composer.json` | Modify | Add `spatie/laravel-activitylog` ^4.9 |
| `config/activitylog.php` | Create | Published config with `default_log_name`, `default_except_attributes`, `delete_records_older_than_days=90` |
| `database/migrations/*_create_activity_log_table.php` | Create | Published vendor migration |
| `config/permission.php` | Modify | Set `events_enabled => true` |
| `app/Models/Role.php` | Create | Extends `Spatie\Permission\Models\Role`, adds `LogsActivity`, `getActivitylogOptionDefaults()` |
| `app/Models/Permission.php` | Create | Extends `Spatie\Permission\Models\Permission`, adds `LogsActivity`, `getActivitylogOptionDefaults()` |
| `app/Models/User.php` | Modify | Add `LogsActivity` trait, `getActivitylogOptionDefaults()` |
| `app/Observers/UserObserver.php` | Create | Supplementary observer if trait doesn't cover edge cases (likely not needed — trait handles it) |
| `app/Listeners/LogSuccessfulLogin.php` | Create | Listens to `Illuminate\Auth\Events\Login` |
| `app/Listeners/LogFailedLogin.php` | Create | Listens to `Illuminate\Auth\Events\Failed` |
| `app/Listeners/LogLogout.php` | Create | Listens to `Illuminate\Auth\Events\Logout` |
| `app/Listeners/LogRoleAttached.php` | Create | Listens to `Spatie\Permission\Events\RoleAttachedEvent` |
| `app/Listeners/LogRoleDetached.php` | Create | Listens to `Spatie\Permission\Events\RoleDetachedEvent` |
| `app/Listeners/LogPermissionAttached.php` | Create | Listens to `Spatie\Permission\Events\PermissionAttachedEvent` |
| `app/Listeners/LogPermissionDetached.php` | Create | Listens to `Spatie\Permission\Events\PermissionDetachedEvent` |
| `app/Listeners/LogTwoFactorEnabled.php` | Create | Listens to `Laravel\Fortify\Events\TwoFactorAuthenticationEnabled` |
| `app/Listeners/LogTwoFactorDisabled.php` | Create | Listens to `Laravel\Fortify\Events\TwoFactorAuthenticationDisabled` |
| `app/Listeners/LogEmailVerified.php` | Create | Listens to `Illuminate\Auth\Events\Verified` |
| `app/Actions/Fortify/CreateNewUser.php` | Modify | Add `activity()->log('registered', ...)` after user creation |
| `app/Actions/Fortify/ResetUserPassword.php` | Modify | Add `activity()->log('password_reset', ...)` after password save |
| `app/Providers/AppServiceProvider.php` | Modify | Register observers (if any), event listener mappings in `boot()` |
| `routes/admin.php` | Modify | Add `Route::livewire('audit-log', 'pages::admin.audit-log')->middleware('can:auditoria.ver')->name('audit-log.index')` |
| `resources/views/pages/admin/⚡audit-log.blade.php` | Create | Livewire SFC with Flux table, search, date range, action type filter, pagination |
| `database/seeders/RolesAndPermissionsSeeder.php` | Modify | Add `auditoria.ver` permission to Super Administrador role |
| `tests/Feature/Admin/AuditLogAccessTest.php` | Create | Access control tests for `/admin/audit-log` |
| `tests/Feature/Auth/AuthAuditTest.php` | Create | Auth event logging tests (login, logout, failed, register, reset, 2FA, verify) |
| `tests/Feature/Admin/ModelAuditTest.php` | Create | Model CRUD audit entry tests (User, Role, Permission) |

## Interfaces / Contracts

### ActivityLog Entry Structure

```php
// activity_log table columns (from Spatie Activitylog migration)
// id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties (JSON), batch_uuid, created_at, updated_at

// Properties shape for auth events:
{
  "ip": "127.0.0.1",
  "user_agent": "Mozilla/5.0...",
  "email": "user@example.com"  // only on failed_login
}

// Properties shape for model events (auto-generated by LogsActivity):
{
  "attributes": { "name": "New Name", "email": "new@example.com" },
  "old": { "name": "Old Name", "email": "old@example.com" }
}
```

### Custom Model Structure

```php
// app/Models/Role.php
namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Role extends SpatieRole
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name'])
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }
}

// app/Models/Permission.php — same pattern
```

### Event Listener Registration (AppServiceProvider)

```php
// In boot():
Event::listen(\Illuminate\Auth\Events\Login::class, LogSuccessfulLogin::class);
Event::listen(\Illuminate\Auth\Events\Failed::class, LogFailedLogin::class);
Event::listen(\Illuminate\Auth\Events\Logout::class, LogLogout::class);
Event::listen(\Laravel\Fortify\Events\TwoFactorAuthenticationEnabled::class, LogTwoFactorEnabled::class);
Event::listen(\Laravel\Fortify\Events\TwoFactorAuthenticationDisabled::class, LogTwoFactorDisabled::class);
Event::listen(\Illuminate\Auth\Events\Verified::class, LogEmailVerified::class);
Event::listen(\Spatie\Permission\Events\RoleAttachedEvent::class, LogRoleAttached::class);
Event::listen(\Spatie\Permission\Events\RoleDetachedEvent::class, LogRoleDetached::class);
Event::listen(\Spatie\Permission\Events\PermissionAttachedEvent::class, LogPermissionAttached::class);
Event::listen(\Spatie\Permission\Events\PermissionDetachedEvent::class, LogPermissionDetached::class);
```

### Scheduled Command (routes/console.php)

```php
Schedule::command('activitylog:clean')->weekly();
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Listener logic (description generation, property extraction) | Pest `test()` with mocked events |
| Integration | Auth events produce activity_log entries | `RefreshDatabase`, `actingAs()`, submit forms, assert `Activity::count()` |
| Integration | Model CRUD produces activity entries with old/new values | Factory → update → assert activity |
| Integration | Role/Permission events fire and produce entries | `events_enabled=true`, `assignRole()` → assert activity |
| Integration | Sensitive attributes excluded from properties | Update password → assert `properties` has no `password` key |
| Integration | Audit UI access control (403 without permission, 200 with) | `actingAs()` + `get(route('admin.audit-log.index'))` |
| Integration | Audit UI filters work (search, date range, action type) | Seed entries → visit page → assert filtered results |
| Integration | Scheduled cleanup removes old entries | Travel back 91 days, seed entry → run command → assert deleted |

## Migration / Rollout

**No data migration needed** — this is a greenfield feature. The activity_log table is created fresh by the vendor migration.

**Rollout steps:**
1. `composer require spatie/laravel-activitylog`
2. Publish migration and config: `php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"`
3. Run migration: `php artisan migrate`
4. Update `config/permission.php`: `events_enabled => true`
5. Run full test suite to catch any side effects from enabled permission events
6. Deploy — audit log starts recording immediately

**Rollback:** Follow the plan in `proposal.md` — remove package, revert config, drop table, delete new files.

## Open Questions

- [ ] Should we add a dedicated `auditoria.ver` permission via the seeder, or create it as part of a separate permissions expansion task? **Decision: Add it to the existing `RolesAndPermissionsSeeder` as part of this change** — it's required for the UI spec.
- [ ] Should the audit UI show a "properties" expand/collapse toggle, or just a truncated summary? **Decision: Truncated summary with a Flux modal trigger to show full JSON** — keeps the table clean while providing full detail on demand.
- [ ] Should we log the `Lockout` Fortify event? **Decision: Yes** — it's a security-relevant event. Add `LogLockout` listener for `Laravel\Fortify\Events\Lockout`.
