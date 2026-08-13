<?php

use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\QboPickerDisplayNameService;
use App\Services\QuickBooksTokenResolverService;
use App\Services\TimeEntryPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TimeEntryPresentationService::class);

uses(RefreshDatabase::class);

it('maps one entry with resolved labels when a token is available', function () {
    $viewer = User::factory()->create();
    $entry = TimeEntry::factory()->forUser($viewer)->create([
        'customer_ref' => '42',
        'description' => 'Billable work',
    ]);
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('entryDisplayNames')
        ->once()
        ->with($token, $entry->user, $entry)
        ->andReturn([
            'customer_name' => 'Acme Corp',
            'project_name' => null,
            'item_name' => null,
        ]);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($viewer)->andReturn($token);

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $payload = $service->resource($entry, $viewer);

    expect($payload['customer_ref'])->toBe('42')
        ->and($payload['customer_name'])->toBe('Acme Corp')
        ->and($payload['description'])->toBe('Billable work');
});

it('uses a pre-resolved token without calling the token resolver again', function () {
    $viewer = User::factory()->create();
    $entry = TimeEntry::factory()->forUser($viewer)->create(['customer_ref' => '42']);
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('entryDisplayNames')
        ->once()
        ->with($token, $entry->user, $entry)
        ->andReturn([
            'customer_name' => 'Acme Corp',
            'project_name' => null,
            'item_name' => null,
        ]);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $payload = $service->resource($entry, $viewer, $token);

    expect($payload['customer_name'])->toBe('Acme Corp');
});

it('returns null labels when quickbooks is disconnected', function () {
    $viewer = User::factory()->create();
    $entry = TimeEntry::factory()->forUser($viewer)->create(['customer_ref' => '42']);

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldNotReceive('entryDisplayNames');

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')
        ->once()
        ->with($viewer)
        ->andThrow(new HttpResponseException(response()->json(['message' => 'not connected'], 403)));

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $payload = $service->resource($entry, $viewer);

    expect($payload['customer_ref'])->toBe('42')
        ->and($payload['customer_name'])->toBeNull();
});

it('maps a collection with one shared token lookup', function () {
    $viewer = User::factory()->create();
    $entries = TimeEntry::factory()->forUser($viewer)->count(2)->create();
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('entryDisplayNames')->twice()->andReturn([
        'customer_name' => 'Acme Corp',
        'project_name' => null,
        'item_name' => null,
    ]);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $rows = $service->collection($entries, $viewer, $token);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['customer_name'])->toBe('Acme Corp')
        ->and($rows[1]['customer_name'])->toBe('Acme Corp');
});

it('resolves one token for a collection when none is pre-resolved', function () {
    $viewer = User::factory()->create();
    $entries = TimeEntry::factory()->forUser($viewer)->count(2)->create();
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('entryDisplayNames')->twice()->andReturn([
        'customer_name' => 'Acme Corp',
        'project_name' => null,
        'item_name' => null,
    ]);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($viewer)->andReturn($token);

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $rows = $service->collection($entries, $viewer);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['customer_name'])->toBe('Acme Corp');
});

it('returns null labels for a collection when quickbooks is disconnected', function () {
    $viewer = User::factory()->create();
    $entries = TimeEntry::factory()->forUser($viewer)->count(2)->create();

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldNotReceive('entryDisplayNames');

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')
        ->once()
        ->with($viewer)
        ->andThrow(new HttpResponseException(response()->json(['message' => 'not connected'], 403)));

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $rows = $service->collection($entries, $viewer);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['customer_name'])->toBeNull()
        ->and($rows[1]['customer_name'])->toBeNull();
});

it('maps a snapshot with resolved picker labels when a token is available', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-labels']);
    $snapshot = TimeActivitySnapshot::factory()->make([
        'realm_id' => 'realm-labels',
        'qbo_id' => '42',
        'customer_ref' => '11',
        'customer_name' => null,
        'project_ref' => '22',
        'project_name' => null,
        'item_ref' => '33',
        'item_name' => null,
    ]);

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('snapshotDisplayNames')
        ->once()
        ->with($token, '11', '22', '33')
        ->andReturn([
            'customer_name' => 'Acme Corp',
            'project_name' => 'Website',
            'item_name' => 'Design',
        ]);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $payload = $service->fromSnapshot($snapshot, $token, 'UTC');

    expect($payload['list_id'])->toBe('qbo:42')
        ->and($payload['customer_name'])->toBe('Acme Corp')
        ->and($payload['project_name'])->toBe('Website')
        ->and($payload['item_name'])->toBe('Design')
        ->and($payload['status'])->toBe('approved');
});

it('skips picker lookups when snapshot already has display names', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-labels']);
    $snapshot = TimeActivitySnapshot::factory()->make([
        'realm_id' => 'realm-labels',
        'qbo_id' => '42',
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website',
        'item_ref' => '33',
        'item_name' => 'Design',
    ]);

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldNotReceive('snapshotDisplayNames');

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $payload = $service->fromSnapshot($snapshot, $token, 'UTC');

    expect($payload['customer_name'])->toBe('Acme Corp')
        ->and($payload['project_name'])->toBe('Website')
        ->and($payload['item_name'])->toBe('Design');
});

it('maps a snapshot without picker labels when no token is available', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'qbo_id' => '42',
        'customer_ref' => '11',
        'customer_name' => null,
        'item_ref' => '33',
        'item_name' => null,
    ]);

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldNotReceive('snapshotDisplayNames');

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new TimeEntryPresentationService($displayNames, $tokenResolver);
    $payload = $service->fromSnapshot($snapshot, null, 'UTC');

    expect($payload['customer_ref'])->toBe('11')
        ->and($payload['customer_name'])->toBeNull()
        ->and($payload['item_name'])->toBeNull();
});
