<?php

use App\Models\TimeActivitySnapshot;
use App\Services\TimeActivitySnapshotPruneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(TimeActivitySnapshotPruneService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-30 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('hard-deletes soft-deleted snapshots older than the retention window', function () {
    $service = app(TimeActivitySnapshotPruneService::class);

    $expired = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'expired']);
    $expired->delete();
    $expired->forceFill(['deleted_at' => now()->subDays(120)])->saveQuietly();

    $recent = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'recent']);
    $recent->delete();
    $recent->forceFill(['deleted_at' => now()->subDays(5)])->saveQuietly();

    expect($service->pruneExpiredSoftDeletes(90))->toBe(1)
        ->and(TimeActivitySnapshot::withTrashed()->where('qbo_id', 'expired')->exists())->toBeFalse()
        ->and(TimeActivitySnapshot::onlyTrashed()->where('qbo_id', 'recent')->exists())->toBeTrue();
});

it('returns zero when snapshot retention days is disabled', function () {
    expect(app(TimeActivitySnapshotPruneService::class)->pruneExpiredSoftDeletes(0))->toBe(0);
});

it('returns zero when snapshot retention days is negative', function () {
    expect(app(TimeActivitySnapshotPruneService::class)->pruneExpiredSoftDeletes(-1))->toBe(0);
});

it('does not prune when retention is zero even if expired snapshots exist', function () {
    $expired = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'keep-when-disabled']);
    $expired->delete();
    $expired->forceFill(['deleted_at' => now()->subDays(120)])->saveQuietly();

    expect(app(TimeActivitySnapshotPruneService::class)->pruneExpiredSoftDeletes(0))->toBe(0)
        ->and(TimeActivitySnapshot::onlyTrashed()->where('qbo_id', 'keep-when-disabled')->exists())->toBeTrue();
});

it('prunes when retention is one day for older soft-deletes', function () {
    $expired = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'one-day-retention']);
    $expired->delete();
    $expired->forceFill(['deleted_at' => now()->subDays(2)])->saveQuietly();

    expect(app(TimeActivitySnapshotPruneService::class)->pruneExpiredSoftDeletes(1))->toBe(1)
        ->and(TimeActivitySnapshot::withTrashed()->where('qbo_id', 'one-day-retention')->exists())->toBeFalse();
});

it('floors zero chunk size to one so pruning still completes', function () {
    config(['quickbooks.snapshot_purge_chunk_size' => 0]);

    $expired = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => 'chunk-zero']);
    $expired->delete();
    $expired->forceFill(['deleted_at' => now()->subDays(120)])->saveQuietly();

    expect(app(TimeActivitySnapshotPruneService::class)->pruneExpiredSoftDeletes(90))->toBe(1)
        ->and(TimeActivitySnapshot::withTrashed()->where('qbo_id', 'chunk-zero')->exists())->toBeFalse();
});

it('prunes snapshots in bounded chunks using the configured chunk size', function () {
    config(['quickbooks.snapshot_purge_chunk_size' => 1]);

    $service = app(TimeActivitySnapshotPruneService::class);

    foreach (['one', 'two'] as $qboId) {
        $snapshot = TimeActivitySnapshot::factory()
            ->forRealm('realm-1')
            ->forEmployee('7')
            ->create(['qbo_id' => $qboId]);
        $snapshot->delete();
        $snapshot->forceFill(['deleted_at' => now()->subDays(120)])->saveQuietly();
    }

    expect($service->pruneExpiredSoftDeletes(90))->toBe(2)
        ->and(TimeActivitySnapshot::onlyTrashed()->count())->toBe(0);
});
