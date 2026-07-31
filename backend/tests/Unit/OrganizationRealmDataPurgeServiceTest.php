<?php

use App\Models\Organization;
use App\Models\QboRealmSyncState;
use App\Models\TimeActivitySnapshot;
use App\Services\OrganizationRealmDataPurgeService;
use App\Services\QboListCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

covers(OrganizationRealmDataPurgeService::class);

it('purges snapshots sync state and list cache for a realm', function () {
    TimeActivitySnapshot::factory()->create(['realm_id' => 'realm-42', 'qbo_id' => '1']);
    QboRealmSyncState::query()->create(['realm_id' => 'realm-42']);
    Cache::forever('quickbooks:realm_version:realm-42', 2);

    $listCache = $this->mock(QboListCacheService::class);
    $listCache->shouldReceive('forgetRealm')
        ->once()
        ->with('realm-42');

    app(OrganizationRealmDataPurgeService::class)->purgeRealm('realm-42');

    expect(TimeActivitySnapshot::withTrashed()->where('realm_id', 'realm-42')->count())->toBe(0)
        ->and(QboRealmSyncState::query()->where('realm_id', 'realm-42')->exists())->toBeFalse();
});

it('purges realm data when no organization claims the realm', function () {
    TimeActivitySnapshot::factory()->create(['realm_id' => 'realm-orphan', 'qbo_id' => '1']);

    app(OrganizationRealmDataPurgeService::class)->purgeRealmWhenUnclaimed('realm-orphan');

    expect(TimeActivitySnapshot::query()->where('realm_id', 'realm-orphan')->exists())->toBeFalse();
});

it('keeps realm data when another organization still claims the realm', function () {
    Organization::factory()->withRealm('realm-shared')->create();
    TimeActivitySnapshot::factory()->create(['realm_id' => 'realm-shared', 'qbo_id' => '1']);

    app(OrganizationRealmDataPurgeService::class)->purgeRealmWhenUnclaimed('realm-shared');

    expect(TimeActivitySnapshot::query()->where('realm_id', 'realm-shared')->exists())->toBeTrue();
});

it('ignores empty realm identifiers', function () {
    TimeActivitySnapshot::factory()->create(['realm_id' => 'realm-42', 'qbo_id' => '1']);

    app(OrganizationRealmDataPurgeService::class)->purgeRealm('');
    app(OrganizationRealmDataPurgeService::class)->purgeRealmWhenUnclaimed('');

    expect(TimeActivitySnapshot::query()->where('realm_id', 'realm-42')->exists())->toBeTrue();
});

it('purges large realms in chunks without leaving rows behind', function () {
    config(['quickbooks.snapshot_purge_chunk_size' => 2]);

    foreach (range(1, 5) as $index) {
        TimeActivitySnapshot::factory()->create([
            'realm_id' => 'realm-chunk',
            'qbo_id' => (string) $index,
        ]);
    }

    app(OrganizationRealmDataPurgeService::class)->purgeRealm('realm-chunk');

    expect(TimeActivitySnapshot::withTrashed()->where('realm_id', 'realm-chunk')->count())->toBe(0);
});
