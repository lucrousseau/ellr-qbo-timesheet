<?php

use App\Models\ActiveTimeSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(ActiveTimeSession::class);

it('reports whether quickbooks picker selections are present', function () {
    $session = ActiveTimeSession::factory()->make([
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
    ]);

    expect($session->hasPickerSelections())->toBeFalse();

    $session->customer_ref = '11';
    expect($session->hasPickerSelections())->toBeTrue();

    $session->customer_ref = '   ';
    expect($session->hasPickerSelections())->toBeFalse();

    $session->customer_ref = null;
    $session->service_ref = '33';
    expect($session->hasPickerSelections())->toBeTrue();

    $session->service_ref = null;
    $session->project_ref = '22';
    expect($session->hasPickerSelections())->toBeTrue();
});

it('casts timer attributes for active sessions', function () {
    $session = ActiveTimeSession::factory()->create([
        'accumulated_seconds' => '45',
        'running_since' => '2026-07-28 12:00:00',
        'is_billable' => true,
    ]);

    expect($session->accumulated_seconds)->toBeInt()->toBe(45)
        ->and($session->running_since)->toBeInstanceOf(Carbon::class)
        ->and($session->is_billable)->toBeTrue();
});

it('casts is_billable to a boolean attribute', function () {
    $billable = ActiveTimeSession::factory()->create(['is_billable' => 1]);
    $nonBillable = ActiveTimeSession::factory()->create(['is_billable' => 0]);

    expect($billable->is_billable)->toBeBool()->toBeTrue()
        ->and($nonBillable->is_billable)->toBeBool()->toBeFalse();
});

it('reports running state from running_since', function () {
    $session = ActiveTimeSession::factory()->make([
        'running_since' => null,
    ]);

    expect($session->isRunning())->toBeFalse();

    $session->running_since = CarbonImmutable::parse('2026-07-28 12:00:00');

    expect($session->isRunning())->toBeTrue();
});

it('belongs to the owning user', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = ActiveTimeSession::factory()->for($user)->create();

    expect($session->user->is($user))->toBeTrue();
});
