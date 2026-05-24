<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// --- Access Control (R2, R8) ---

test('unauthenticated user is redirected to login', function () {
    $this->get(route('admin.audit-log.index'))
        ->assertRedirect(route('login'));
});

test('user without auditoria.ver permission gets 403', function () {
    // Create a user with admin.acceder but NOT the Super Administrador role
    // (Super Admin bypasses all permissions via Gate::before)
    $user = User::factory()->create();
    // Don't assign Super Administrador — just give admin.acceder directly
    $user->givePermissionTo('admin.acceder');

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertForbidden();
});

test('user with auditoria.ver permission can access page', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertOk()
        ->assertSee('Auditoría');
});

// --- Page Rendering (R1, R3, R7, R9) ---

test('audit log page shows table headers', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertSee('Fecha')
        ->assertSee('Usuario')
        ->assertSee('Acción')
        ->assertSee('Sujeto');
});

test('audit log page displays entries ordered by most recent first', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    Activity::create([
        'log_name' => 'auth',
        'description' => 'login',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $user->id,
        'created_at' => now()->subMinutes(5),
    ]);

    Activity::create([
        'log_name' => 'auth',
        'description' => 'logout',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $user->id,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertSeeInOrder(['logout', 'login']);
});

test('audit log page shows empty state when no entries exist', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    // Clear activity entries created by the seeder
    Activity::query()->delete();

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertSee('No hay registros de auditoría');
});

// --- Text Search (R4) ---

test('text search filters by causer name', function () {
    $admin = User::factory()->create(['name' => 'Admin User']);
    $admin->assignRole('Super Administrador');

    $other = User::factory()->create(['name' => 'Other User']);

    // Clear seeder entries
    Activity::query()->delete();

    Activity::create([
        'log_name' => 'default',
        'description' => 'created',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $admin->id,
        'properties' => ['attributes' => ['name' => 'Test']],
    ]);

    Activity::create([
        'log_name' => 'default',
        'description' => 'created',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $other->id,
        'properties' => ['attributes' => ['name' => 'Test']],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.audit-log.index', ['search' => 'Admin']))
        ->assertSee('Admin User')
        ->assertDontSee('Other User');
});

// --- Action Type Filter (R6) ---

test('action type filter filters by log_name', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    // Clear seeder entries
    Activity::query()->delete();

    Activity::create([
        'log_name' => 'auth',
        'description' => 'login',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $user->id,
    ]);

    Activity::create([
        'log_name' => 'default',
        'description' => 'created',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.audit-log.index', ['action_type' => 'auth']))
        ->assertSee('login')
        ->assertDontSee('created');
});

// --- Date Range Filter (R5) ---

test('date range filter shows only entries within range', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    // Clear seeder entries
    Activity::query()->delete();

    Activity::create([
        'log_name' => 'auth',
        'description' => 'login',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $user->id,
        'created_at' => now()->subDays(10),
    ]);

    Activity::create([
        'log_name' => 'auth',
        'description' => 'logout',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $user->id,
        'created_at' => now()->subDay(),
    ]);

    $startDate = now()->subDays(5)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $this->actingAs($user)
        ->get(route('admin.audit-log.index', ['date_start' => $startDate, 'date_end' => $endDate]))
        ->assertSee('logout')
        ->assertDontSee('login');
});

// --- Pagination Page 2 Navigation (R7) ---

test('pagination shows 25 entries per page and navigates to page 2', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    // Clear seeder entries
    Activity::query()->delete();

    // Create 30 entries (more than 25 per page)
    for ($i = 1; $i <= 30; $i++) {
        Activity::create([
            'log_name' => 'default',
            'description' => "action_{$i}",
            'causer_type' => 'App\\Models\\User',
            'causer_id' => $user->id,
            'created_at' => now()->subMinutes(30 - $i),
        ]);
    }

    // Page 1 should show action_30 through action_6 (most recent first, 25 items)
    $response = $this->actingAs($user)
        ->get(route('admin.audit-log.index'));

    $response->assertSee('action_30')
        ->assertSee('action_6')
        ->assertDontSee('action_5');

    // Page 2 should show action_5 through action_1
    $response = $this->actingAs($user)
        ->get(route('admin.audit-log.index', ['page' => 2]));

    $response->assertSee('action_5')
        ->assertSee('action_1')
        ->assertDontSee('action_30');
});

// --- Properties Modal (R10) ---

test('page renders properties modal trigger', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    Activity::create([
        'log_name' => 'default',
        'description' => 'updated',
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $user->id,
        'properties' => ['attributes' => ['name' => 'New Name'], 'old' => ['name' => 'Old Name']],
    ]);

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertSee('Ver detalles');
});
