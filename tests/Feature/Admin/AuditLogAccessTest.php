<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// --- Access Control (R2, R8) ---
// Note: 403/200 assertions are also covered in AuditLogPageTest.
// This file focuses on sidebar rendering and access integration.

test('unauthorized user cannot access audit log route', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin.acceder');

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertForbidden();
});

test('authorized user can access audit log route', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    $this->actingAs($user)
        ->get(route('admin.audit-log.index'))
        ->assertOk();
});

// --- Sidebar Entry (admin-audit-ui spec) ---

test('sidebar shows auditoria entry for user with permission', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Administrador');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee('Auditoría');
});

test('sidebar does not show auditoria entry for user without permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('admin.acceder');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertDontSee('Auditoría');
});
