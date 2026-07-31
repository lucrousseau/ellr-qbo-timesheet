<?php

use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntryMergedListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(TimeEntryMergedListQuery::class);

uses(RefreshDatabase::class);

it('counts only local entries when snapshot context is absent', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    TimeEntry::factory()->forUser($employee)->count(2)->create();

    expect(TimeEntryMergedListQuery::count($employee, null))->toBe(2);
});

it('counts merged local and snapshot rows while excluding linked quickbooks ids', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-merge']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    TimeEntry::factory()->forUser($employee)->create();
    TimeEntry::factory()->forUser($employee)->approved('42')->create();

    TimeActivitySnapshot::factory()
        ->forRealm($token->realm_id)
        ->forEmployee('7')
        ->create(['qbo_id' => '42']);
    TimeActivitySnapshot::factory()
        ->forRealm($token->realm_id)
        ->forEmployee('7')
        ->create(['qbo_id' => '99']);

    $context = [
        'realm_id' => $token->realm_id,
        'employee_ref' => '7',
    ];

    expect(TimeEntryMergedListQuery::count($employee, $context))->toBe(3);
});

it('builds a local-only union query when snapshot context is absent', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    TimeEntry::factory()->forUser($employee)->create();

    $rows = TimeEntryMergedListQuery::unionQuery($employee, null)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->row_source)->toBe('local')
        ->and($rows[0]->list_id)->toBe('local:1');
});

it('builds a union query when snapshot context is present', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-merge']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    TimeEntry::factory()->forUser($employee)->create();
    TimeActivitySnapshot::factory()
        ->forRealm($token->realm_id)
        ->forEmployee('7')
        ->create(['qbo_id' => '99']);

    $context = [
        'realm_id' => $token->realm_id,
        'employee_ref' => '7',
    ];

    $rows = TimeEntryMergedListQuery::unionQuery($employee, $context)->get();

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('row_source')->sort()->values()->all())->toBe(['local', 'snapshot']);
});
