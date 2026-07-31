<?php

use App\Console\Commands\PruneTimeActivitySnapshotsCommand;
use App\Models\TimeActivitySnapshot;
use App\Services\TimeActivitySnapshotPruneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(PruneTimeActivitySnapshotsCommand::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-30 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('prunes expired soft-deleted snapshots via artisan', function () {
    $expired = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'old']);
    $expired->delete();
    $expired->forceFill(['deleted_at' => now()->subDays(120)])->saveQuietly();

    $recent = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'recent']);
    $recent->delete();
    $recent->forceFill(['deleted_at' => now()->subDays(10)])->saveQuietly();

    $this->artisan('quickbooks:prune-time-activity-snapshots')
        ->expectsOutputToContain('Pruned 1 expired soft-deleted snapshot(s).')
        ->assertSuccessful();

    expect(TimeActivitySnapshot::withTrashed()->where('qbo_id', 'old')->exists())->toBeFalse()
        ->and(TimeActivitySnapshot::onlyTrashed()->where('qbo_id', 'recent')->exists())->toBeTrue();
});

it('skips pruning when retention days is disabled', function () {
    config(['quickbooks.snapshot_soft_delete_retention_days' => 0]);

    $snapshots = Mockery::mock(TimeActivitySnapshotPruneService::class);
    $snapshots->shouldNotReceive('pruneExpiredSoftDeletes');
    app()->instance(TimeActivitySnapshotPruneService::class, $snapshots);

    $this->artisan('quickbooks:prune-time-activity-snapshots')
        ->expectsOutputToContain('Snapshot soft-delete pruning is disabled')
        ->assertSuccessful();
});
