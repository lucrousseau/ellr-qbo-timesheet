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
