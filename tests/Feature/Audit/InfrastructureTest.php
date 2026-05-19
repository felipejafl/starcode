<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('activity_log table exists and is accessible', function () {
    expect(Schema::hasTable('activity_log'))->toBeTrue();
});

test('User model has LogsActivity trait configured', function () {
    $user = User::factory()->create();

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('created')
        ->and($activity->causer_id)->toBeNull(); // No authenticated user during factory
});

test('User model excludes password from log properties', function () {
    $user = User::factory()->create();

    $user->update(['name' => 'Updated Name']);

    $activity = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('description', 'updated')
        ->first();

    expect($activity)->not->toBeNull();

    // In v5, attribute_changes is a Collection with 'attributes' and 'old' keys
    $changes = $activity->attribute_changes;

    expect($changes->get('attributes'))->not->toHaveKey('password')
        ->and($changes->get('old'))->not->toHaveKey('password');
});

test('Role model logs creation with LogsActivity', function () {
    $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

    $activity = Activity::where('subject_type', Role::class)
        ->where('subject_id', $role->id)
        ->where('description', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes)->toHaveKey('attributes')
        ->and($activity->attribute_changes->get('attributes'))->toHaveKey('name')
        ->and($activity->attribute_changes->get('attributes')['name'])->toBe('Test Role');
});

test('Role model logs updates with old and new values', function () {
    $role = Role::create(['name' => 'Original Name', 'guard_name' => 'web']);

    $role->update(['name' => 'Updated Name']);

    $activity = Activity::where('subject_type', Role::class)
        ->where('subject_id', $role->id)
        ->where('description', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();

    expect($activity->attribute_changes)->toHaveKey('attributes')
        ->and($activity->attribute_changes->get('attributes'))->toHaveKey('name')
        ->and($activity->attribute_changes->get('attributes')['name'])->toBe('Updated Name');
});

test('Permission model logs creation with LogsActivity', function () {
    $permission = Permission::create(['name' => 'test.permission', 'guard_name' => 'web']);

    $activity = Activity::where('subject_type', Permission::class)
        ->where('subject_id', $permission->id)
        ->where('description', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes)->toHaveKey('attributes')
        ->and($activity->attribute_changes->get('attributes'))->toHaveKey('name')
        ->and($activity->attribute_changes->get('attributes')['name'])->toBe('test.permission');
});

test('Permission model logs updates', function () {
    $permission = Permission::create(['name' => 'original.perm', 'guard_name' => 'web']);

    $permission->update(['name' => 'updated.perm']);

    $activity = Activity::where('subject_type', Permission::class)
        ->where('subject_id', $permission->id)
        ->where('description', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();

    expect($activity->attribute_changes)->toHaveKey('attributes')
        ->and($activity->attribute_changes->get('attributes'))->toHaveKey('name')
        ->and($activity->attribute_changes->get('attributes')['name'])->toBe('updated.perm');
});

test('config permission events are enabled', function () {
    expect(config('permission.events_enabled'))->toBeTrue();
});

test('config permission models point to custom classes', function () {
    expect(config('permission.models.permission'))->toBe(Permission::class)
        ->and(config('permission.models.role'))->toBe(Role::class);
});

test('config activitylog excludes sensitive attributes', function () {
    $excluded = config('activitylog.default_except_attributes');

    expect($excluded)->toContain('password')
        ->and($excluded)->toContain('two_factor_secret')
        ->and($excluded)->toContain('remember_token')
        ->and($excluded)->toContain('two_factor_recovery_codes');
});

test('config activitylog clean_after_days is 90', function () {
    expect(config('activitylog.clean_after_days'))->toBe(90);
});

test('activitylog clean command is registered in scheduler', function () {
    $events = collect(app('events')->getListeners('Illuminate\Console\Events\ScheduledTaskStarting'));

    // Verify the schedule is defined by checking the console routes
    $consolePath = base_path('routes/console.php');
    $content = file_get_contents($consolePath);

    expect($content)->toContain('activitylog:clean')
        ->and($content)->toContain('weekly');
});
