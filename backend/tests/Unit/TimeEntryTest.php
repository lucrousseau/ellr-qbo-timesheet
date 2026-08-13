<?php

use App\Enums\TimeEntryStatus;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

covers(TimeEntry::class);

it('casts status and datetime attributes', function () {
    $user = User::factory()->create();
    $entry = TimeEntry::factory()->forUser($user)->create([
        'is_billable' => 1,
        'reviewed_at' => '2026-07-30 12:00:00',
    ])->fresh();

    expect($entry->status)->toBe(TimeEntryStatus::Draft)
        ->and($entry->is_billable)->toBeTrue()
        ->and($entry->start_time)->toBeInstanceOf(Carbon::class)
        ->and($entry->end_time)->toBeInstanceOf(Carbon::class)
        ->and($entry->reviewed_at)->toBeInstanceOf(Carbon::class);
});

it('relates to the owning user, organization, and reviewer', function () {
    $organization = Organization::factory()->create();
    $employee = User::factory()->create(['organization_id' => $organization->id]);
    $reviewer = User::factory()->create(['organization_id' => $organization->id]);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'reviewed_by_id' => $reviewer->id,
        'status' => TimeEntryStatus::Approved,
    ]);

    expect($entry->user->is($employee))->toBeTrue()
        ->and($entry->organization->is($organization))->toBeTrue()
        ->and($entry->reviewedBy->is($reviewer))->toBeTrue();
});

it('reports whether the entry has been synced to quickbooks', function () {
    $user = User::factory()->create();
    $unsynced = TimeEntry::factory()->forUser($user)->make(['qbo_id' => null]);
    $blank = TimeEntry::factory()->forUser($user)->make(['qbo_id' => '']);
    $synced = TimeEntry::factory()->forUser($user)->make(['qbo_id' => '9001']);

    expect($unsynced->isSyncedToQbo())->toBeFalse()
        ->and($blank->isSyncedToQbo())->toBeFalse()
        ->and($synced->isSyncedToQbo())->toBeTrue();
});
