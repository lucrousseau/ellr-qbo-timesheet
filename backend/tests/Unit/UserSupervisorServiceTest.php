<?php

use App\Models\User;
use App\Services\UserSupervisorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(UserSupervisorService::class);

uses(RefreshDatabase::class);

it('assigns a supervisor and returns the updated user with relations', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $supervisor = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '9',
    ]);

    $updated = app(UserSupervisorService::class)->updateSupervisor($admin, $employee, $supervisor->id);

    expect($updated->supervisor_id)->toBe($supervisor->id)
        ->and($updated->relationLoaded('supervisor'))->toBeTrue()
        ->and($updated->supervisor?->id)->toBe($supervisor->id)
        ->and($updated->relationLoaded('organization'))->toBeTrue()
        ->and($updated->relationLoaded('userLevel'))->toBeTrue();
});

it('clears a supervisor assignment', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
        'supervisor_id' => $supervisor->id,
    ]);

    $updated = app(UserSupervisorService::class)->updateSupervisor($admin, $employee, null);

    expect($updated->supervisor_id)->toBeNull();
});
