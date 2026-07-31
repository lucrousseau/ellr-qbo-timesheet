<?php

use App\Models\QuickBooksToken;
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
