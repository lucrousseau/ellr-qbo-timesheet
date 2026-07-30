<?php

use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\OrganizationTimezoneService;
use App\Services\QuickBooksCompanyInfoService;
use App\Support\UserTimezoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(OrganizationTimezoneService::class);
covers(UserTimezoneResolver::class);

uses(RefreshDatabase::class);

it('resolves organization company timezone for a realm', function () {
    Organization::factory()->withRealm('realm-1')->create([
        'company_timezone' => 'America/Chicago',
    ]);

    expect(app(OrganizationTimezoneService::class)->forRealm('realm-1'))->toBe('America/Chicago');
});

it('falls back to deployment timezone when realm timezone is missing', function () {
    config(['quickbooks.company_timezone' => 'America/Los_Angeles']);

    expect(app(OrganizationTimezoneService::class)->forRealm('missing-realm'))->toBe('America/Los_Angeles');
});

it('syncs company timezone from quickbooks only when missing', function () {
    $organization = Organization::factory()->create(['company_timezone' => null]);
    $admin = User::factory()->admin()->for($organization)->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create();

    $companyInfo = Mockery::mock(QuickBooksCompanyInfoService::class);
    $companyInfo->shouldReceive('fetchDefaultTimezone')
        ->once()
        ->with($token)
        ->andReturn('America/Toronto');

    $service = new OrganizationTimezoneService($companyInfo);
    $service->syncIfMissing($organization, $token);

    expect($organization->fresh()->company_timezone)->toBe('America/Toronto');
});

it('does not overwrite an existing company timezone during syncIfMissing', function () {
    $organization = Organization::factory()->create(['company_timezone' => 'America/Chicago']);
    $admin = User::factory()->admin()->for($organization)->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create();

    $companyInfo = Mockery::mock(QuickBooksCompanyInfoService::class);
    $companyInfo->shouldNotReceive('fetchDefaultTimezone');

    $service = new OrganizationTimezoneService($companyInfo);
    $service->syncIfMissing($organization, $token);

    expect($organization->fresh()->company_timezone)->toBe('America/Chicago');
});

it('prefers user timezone over organization timezone for display', function () {
    $organization = Organization::factory()->create(['company_timezone' => 'America/Chicago']);
    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'timezone' => 'Europe/Paris',
    ]);

    expect(UserTimezoneResolver::effectiveDisplayTimezone($user))->toBe('Europe/Paris');
});

it('uses organization timezone when user override is absent', function () {
    $organization = Organization::factory()->create(['company_timezone' => 'America/Chicago']);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    expect(UserTimezoneResolver::effectiveDisplayTimezone($user))->toBe('America/Chicago');
});
