<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─── User CRUD Audit (R2, R9) ───

test('creating a user produces an activity entry with attribute changes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => bcrypt('password'),
    ]);

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('description', 'created')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_type)->toBe(User::class)
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->attribute_changes)->toHaveKey('attributes')
        ->and($activity->attribute_changes['attributes'])->toHaveKey('name')
        ->and($activity->attribute_changes['attributes'])->toHaveKey('email');
});

test('updating a user produces an activity entry with old and new values', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $user = User::factory()->create(['name' => 'Original Name', 'email' => 'original@example.com']);

    Activity::query()->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->delete();

    $user->update(['name' => 'Updated Name']);

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('description', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['name'])->toBe('Updated Name')
        ->and($activity->attribute_changes['old']['name'])->toBe('Original Name');
});

test('deleting a user produces an activity entry', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $user = User::factory()->create();
    $userId = $user->id;

    $user->delete();

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $userId)
        ->where('description', 'deleted')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

// ─── Role CRUD Audit (R3) ───

test('creating a role produces an activity entry', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $role = Role::create(['name' => 'test.role', 'guard_name' => 'web']);

    $activity = Activity::where('subject_type', Role::class)
        ->where('subject_id', $role->id)
        ->where('description', 'created')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->attribute_changes['attributes'])->toHaveKey('name');
});

test('updating a role produces an activity entry', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $role = Role::create(['name' => 'old.name', 'guard_name' => 'web']);

    Activity::query()->where('subject_type', Role::class)
        ->where('subject_id', $role->id)
        ->delete();

    $role->update(['name' => 'new.name']);

    $activity = Activity::where('subject_type', Role::class)
        ->where('subject_id', $role->id)
        ->where('description', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['name'])->toBe('new.name')
        ->and($activity->attribute_changes['old']['name'])->toBe('old.name');
});

// ─── Permission CRUD Audit (R4) ───

test('creating a permission produces an activity entry', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $permission = Permission::create(['name' => 'test.permission', 'guard_name' => 'web']);

    $activity = Activity::where('subject_type', Permission::class)
        ->where('subject_id', $permission->id)
        ->where('description', 'created')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

// ─── Password Exclusion (R8) ───

test('password is excluded from user update attribute changes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $user = User::factory()->create(['name' => 'Test User']);

    Activity::query()->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->delete();

    $user->update(['name' => 'New Name', 'password' => bcrypt('new-secret')]);

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('description', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes'])->not->toHaveKey('password')
        ->and($activity->attribute_changes['old'])->not->toHaveKey('password')
        ->and($activity->attribute_changes['attributes'])->toHaveKey('name');
});

// ─── Role Assignment Audit (R6) ───

test('assigning a role to a user produces activity entry via listener', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $user = User::factory()->create();
    $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);

    Activity::query()->delete();

    $user->assignRole($role);

    $activity = Activity::where('description', 'like', '%assigned role%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($user->id);
});

test('removing a role from a user produces activity entry via listener', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $user = User::factory()->create();
    $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
    $user->assignRole($role);

    Activity::query()->delete();

    $user->removeRole($role);

    $activity = Activity::where('description', 'like', '%removed role%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($user->id);
});

// ─── Cleanup Retention (R10, R11) ───

test('scheduled cleanup removes entries older than 90 days and preserves recent ones', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    // Clear any seeder-created entries
    Activity::query()->delete();

    // Create an old entry (91 days ago — should be deleted)
    Activity::create([
        'log_name' => 'auth',
        'description' => 'login',
        'causer_type' => User::class,
        'causer_id' => $admin->id,
        'created_at' => now()->subDays(91),
    ]);

    // Create a recent entry (10 days ago — should be preserved)
    Activity::create([
        'log_name' => 'auth',
        'description' => 'logout',
        'causer_type' => User::class,
        'causer_id' => $admin->id,
        'created_at' => now()->subDays(10),
    ]);

    expect(Activity::count())->toBe(2);

    // Run the cleanup command
    $this->artisan('activitylog:clean')
        ->assertExitCode(0);

    // Old entry should be gone, recent entry should remain
    expect(Activity::count())->toBe(1)
        ->and(Activity::where('description', 'logout')->exists())->toBeTrue()
        ->and(Activity::where('description', 'login')->exists())->toBeFalse();
});

// ─── Permission Assignment Audit (R7) ───

test('assigning a permission to a role produces activity entry via listener', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'articles.create', 'guard_name' => 'web']);

    Activity::query()->delete();

    $role->givePermissionTo($permission);

    $activity = Activity::where('description', 'like', '%assigned permission%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(Role::class)
        ->and($activity->subject_id)->toBe($role->id);
});

test('revoking a permission from a role produces activity entry via listener', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    $this->actingAs($admin);

    $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'articles.create', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    Activity::query()->delete();

    $role->revokePermissionTo($permission);

    $activity = Activity::where('description', 'like', '%removed permission%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(Role::class)
        ->and($activity->subject_id)->toBe($role->id);
});
