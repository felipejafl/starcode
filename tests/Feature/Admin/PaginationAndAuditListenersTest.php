<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─── Users Pagination ───

test('users component uses pagination with configurable per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    User::query()->where('id', '!=', $admin->id)->delete();

    for ($i = 1; $i <= 15; $i++) {
        User::factory()->create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->assertSet('perPage', 10);
});

test('users page changes per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    for ($i = 1; $i <= 25; $i++) {
        User::factory()->create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->set('perPage', 20)
        ->assertSet('perPage', 20);
});

// ─── Roles Pagination ───

test('roles component uses pagination with configurable per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    for ($i = 1; $i <= 15; $i++) {
        Role::create(['name' => "test.role.{$i}", 'guard_name' => 'web']);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.roles')
        ->assertSet('perPage', 10);
});

test('roles page changes per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    for ($i = 1; $i <= 25; $i++) {
        Role::create(['name' => "test.role.{$i}", 'guard_name' => 'web']);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.roles')
        ->set('perPage', 20)
        ->assertSet('perPage', 20);
});

// ─── Permissions Pagination ───

test('permissions component uses pagination with configurable per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    for ($i = 1; $i <= 15; $i++) {
        Permission::create(['name' => "test.perm.{$i}", 'guard_name' => 'web']);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.permissions')
        ->assertSet('perPage', 10);
});

test('permissions page changes per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    for ($i = 1; $i <= 25; $i++) {
        Permission::create(['name' => "test.perm.{$i}", 'guard_name' => 'web']);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.permissions')
        ->set('perPage', 20)
        ->assertSet('perPage', 20);
});

// ─── Audit Log Per-Page Selector ───

test('audit log component has configurable per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    Activity::query()->delete();

    for ($i = 1; $i <= 30; $i++) {
        Activity::create([
            'log_name' => 'default',
            'description' => "action_{$i}",
            'causer_type' => User::class,
            'causer_id' => $admin->id,
            'created_at' => now()->subMinutes(30 - $i),
        ]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.audit-log')
        ->assertSet('perPage', 25);
});

test('audit log page changes per-page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    Activity::query()->delete();

    for ($i = 1; $i <= 50; $i++) {
        Activity::create([
            'log_name' => 'default',
            'description' => "action_{$i}",
            'causer_type' => User::class,
            'causer_id' => $admin->id,
            'created_at' => now()->subMinutes(50 - $i),
        ]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.audit-log')
        ->set('perPage', 10)
        ->assertSet('perPage', 10);
});

// ─── Audit Listeners Without Auth ───

test('role attached listener works without authenticated user', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);

    Activity::query()->delete();

    // No actingAs — simulate console/seeder context
    $user->assignRole($role);

    $activity = Activity::where('description', 'like', '%assigned role%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->causer_id)->toBeNull();
});

test('role detached listener works without authenticated user', function () {
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
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->causer_id)->toBeNull();
});

test('permission attached listener works without authenticated user', function () {
    $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'articles.create', 'guard_name' => 'web']);

    Activity::query()->delete();

    $role->givePermissionTo($permission);

    $activity = Activity::where('description', 'like', '%assigned permission%')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(Role::class)
        ->and($activity->subject_id)->toBe($role->id)
        ->and($activity->causer_id)->toBeNull();
});

test('permission detached listener works without authenticated user', function () {
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
        ->and($activity->subject_id)->toBe($role->id)
        ->and($activity->causer_id)->toBeNull();
});

// ─── Pagination Summary Visibility (Bug: summary disappears when perPage covers all records) ───

test('users pagination summary is visible even when all records fit on one page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    User::query()->where('id', '!=', $admin->id)->delete();

    // Create only 5 records — fewer than default perPage (10)
    for ($i = 1; $i <= 5; $i++) {
        User::factory()->create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
    }

    $this->actingAs($admin);

    // Total is 6 (admin + 5 created)
    Livewire::test('pages::admin.users')
        ->assertSee('Mostrando')
        ->assertSee('de 6');
});

test('users pagination links are hidden when all records fit on one page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    User::query()->where('id', '!=', $admin->id)->delete();

    for ($i = 1; $i <= 5; $i++) {
        User::factory()->create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
    }

    $this->actingAs($admin);

    // With 5 records and perPage=10, there should be no pagination links
    Livewire::test('pages::admin.users')
        ->assertDontSee('pagination');
});

test('users pagination summary remains visible after increasing perPage to cover all records', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    User::query()->where('id', '!=', $admin->id)->delete();

    for ($i = 1; $i <= 15; $i++) {
        User::factory()->create(['name' => "User {$i}", 'email' => "user{$i}@test.com"]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->set('perPage', 20)
        ->assertSet('perPage', 20)
        ->assertSee('Mostrando')
        ->assertSee('de 16'); // 15 created + 1 admin
});

test('audit log pagination summary is visible even when all records fit on one page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    Activity::query()->delete();

    // Create only 5 records — fewer than default perPage (25)
    for ($i = 1; $i <= 5; $i++) {
        Activity::create([
            'log_name' => 'default',
            'description' => "action_{$i}",
            'causer_type' => User::class,
            'causer_id' => $admin->id,
            'created_at' => now()->subMinutes(5 - $i),
        ]);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.audit-log')
        ->assertSee('Mostrando')
        ->assertSee('de 5');
});

test('roles pagination summary is visible even when all records fit on one page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    for ($i = 1; $i <= 5; $i++) {
        Role::create(['name' => "test.role.summary.{$i}", 'guard_name' => 'web']);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.roles')
        ->assertSee('Mostrando')
        ->assertSee('de ');
});

test('permissions pagination summary is visible even when all records fit on one page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Administrador');

    for ($i = 1; $i <= 5; $i++) {
        Permission::create(['name' => "test.perm.summary.{$i}", 'guard_name' => 'web']);
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.permissions')
        ->assertSee('Mostrando')
        ->assertSee('de ');
});
