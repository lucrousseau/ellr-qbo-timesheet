<?php

use App\Models\User;
use App\Services\OrganizationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

covers(OrganizationAccessService::class);

uses(RefreshDatabase::class);

it('aborts when actor and target belong to different organizations', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    expect(fn () => app(OrganizationAccessService::class)->ensureSameOrganization($actor, $target))
        ->toThrow(NotFoundHttpException::class);
});

it('aborts when a timesheet user belongs to another organization', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Foreign User',
    ]);

    expect(fn () => app(OrganizationAccessService::class)->ensureTimesheetUser($actor, $target))
        ->toThrow(NotFoundHttpException::class);
});

it('allows access when actor and target share the same organization', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create([
        'organization_id' => $actor->organization_id,
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    app(OrganizationAccessService::class)->ensureSameOrganization($actor, $target);

    expect(true)->toBeTrue();
});

it('allows access to provisioned timesheet users in the same organization', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create([
        'organization_id' => $actor->organization_id,
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    app(OrganizationAccessService::class)->ensureTimesheetUser($actor, $target);

    expect(true)->toBeTrue();
});

it('aborts when the target has no quickbooks employee reference', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create([
        'organization_id' => $actor->organization_id,
    ]);

    expect(fn () => app(OrganizationAccessService::class)->ensureTimesheetUser($actor, $target))
        ->toThrow(NotFoundHttpException::class);
});

it('aborts when the target has an empty quickbooks employee reference', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create([
        'organization_id' => $actor->organization_id,
        'qbo_employee_ref' => '',
    ]);

    expect(fn () => app(OrganizationAccessService::class)->ensureTimesheetUser($actor, $target))
        ->toThrow(NotFoundHttpException::class);
});

it('aborts when the target is an administrator account', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->admin()->create([
        'organization_id' => $actor->organization_id,
    ]);

    expect(fn () => app(OrganizationAccessService::class)->ensureTimesheetUser($actor, $target))
        ->toThrow(NotFoundHttpException::class);
});
