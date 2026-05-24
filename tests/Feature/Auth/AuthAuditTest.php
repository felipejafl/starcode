<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─── Auth Listeners ───

test('successful login produces auth activity entry', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, true));

    expect(Activity::where('log_name', 'auth')
        ->where('description', 'login')
        ->where('causer_type', User::class)
        ->where('causer_id', $user->id)
        ->exists())->toBeTrue();
});

test('failed login produces auth activity entry with null causer', function () {
    event(new Failed('web', new Request(['email' => 'test@example.com']), [
        'email' => 'test@example.com',
        'password' => 'wrong',
    ]));

    $activity = Activity::where('log_name', 'auth')
        ->where('description', 'failed_login')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->properties)->toHaveKey('email');
});

test('logout produces auth activity entry', function () {
    $user = User::factory()->create();

    event(new Logout('web', $user));

    expect(Activity::where('log_name', 'auth')
        ->where('description', 'logout')
        ->where('causer_type', User::class)
        ->where('causer_id', $user->id)
        ->exists())->toBeTrue();
});

test('lockout produces auth activity entry with null causer', function () {
    $request = new Request(['email' => 'locked@example.com']);

    event(new Lockout($request));

    $activity = Activity::where('log_name', 'auth')
        ->where('description', 'lockout')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->properties)->toHaveKey('ip');
});

test('two factor enabled produces auth activity entry', function () {
    $user = User::factory()->create();

    event(new TwoFactorAuthenticationEnabled($user));

    expect(Activity::where('log_name', 'auth')
        ->where('description', 'two_factor_enabled')
        ->where('causer_type', User::class)
        ->where('causer_id', $user->id)
        ->exists())->toBeTrue();
});

test('two factor disabled produces auth activity entry', function () {
    $user = User::factory()->create();

    event(new TwoFactorAuthenticationDisabled($user));

    expect(Activity::where('log_name', 'auth')
        ->where('description', 'two_factor_disabled')
        ->where('causer_type', User::class)
        ->where('causer_id', $user->id)
        ->exists())->toBeTrue();
});

test('email verified produces auth activity entry', function () {
    $user = User::factory()->create();

    event(new Verified($user));

    expect(Activity::where('log_name', 'auth')
        ->where('description', 'email_verified')
        ->where('causer_type', User::class)
        ->where('causer_id', $user->id)
        ->exists())->toBeTrue();
});

// ─── Permission Listeners ───

test('role attached produces activity entry', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'test.role', 'guard_name' => 'web']);

    $this->actingAs($user);
    $user->assignRole($role);

    $activity = Activity::where('description', 'like', '%assigned role%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($user->id);
});

test('role detached produces activity entry', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'test.role', 'guard_name' => 'web']);
    $user->assignRole($role);

    Activity::query()->delete();

    $this->actingAs($user);
    $user->removeRole($role);

    $activity = Activity::where('description', 'like', '%removed role%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($user->id);
});

test('permission attached produces activity entry', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'test.role', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'test.perm', 'guard_name' => 'web']);
    $user->assignRole($role);

    Activity::query()->delete();

    $this->actingAs($user);
    $role->givePermissionTo($permission);

    $activity = Activity::where('description', 'like', '%assigned permission%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(Role::class)
        ->and($activity->subject_id)->toBe($role->id);
});

test('permission detached produces activity entry', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'test.role', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'test.perm', 'guard_name' => 'web']);
    $user->assignRole($role);
    $role->givePermissionTo($permission);

    Activity::query()->delete();

    $this->actingAs($user);
    $role->revokePermissionTo($permission);

    $activity = Activity::where('description', 'like', '%removed permission%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(Role::class)
        ->and($activity->subject_id)->toBe($role->id);
});

// ─── Fortify Actions ───

test('password reset produces activity entry via ResetUserPassword action', function () {
    $user = User::factory()->create();

    app(ResetsUserPasswords::class)->reset($user, [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $activity = Activity::where('log_name', 'auth')
        ->where('description', 'password_reset')
        ->where('causer_type', User::class)
        ->where('causer_id', $user->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties)->not->toHaveKey('password');
});
