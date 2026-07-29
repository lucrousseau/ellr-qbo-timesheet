<?php

use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\TimeActivityDisplayEnricherService;
use App\Support\QboCustomerResolver;
use App\Support\TimeActivityReferenceNameLookup;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeActivityDisplayEnricherService::class);
covers(TimeActivityReferenceNameLookup::class);

/**
 * @return TimeActivityDisplayEnricherService
 */
function makeTimeActivityDisplayEnricherService(): TimeActivityDisplayEnricherService
{
    return new TimeActivityDisplayEnricherService(
        new QboCustomerResolver(new QuickBooksApiErrorFormatterService),
        new TimeActivityReferenceNameLookup(new QuickBooksApiErrorFormatterService),
    );
}

it('enriches customer, project, and item references with display names', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with("SELECT Id, DisplayName, Job, ParentRef, Active FROM Customer WHERE Id IN ('58','22') MAXRESULTS 1000")
        ->andReturn([
            (object) ['Id' => '58', 'DisplayName' => 'Test ABC'],
            (object) ['Id' => '22', 'DisplayName' => 'Website'],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with("SELECT Id, Name FROM Item WHERE Id IN ('6') MAXRESULTS 1000")
        ->andReturn([(object) ['Id' => '6', 'Name' => 'Gardening']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $activity = (object) [
        'Id' => '1',
        'CustomerRef' => '58',
        'ProjectRef' => '22',
        'ItemRef' => '6',
    ];

    $enriched = makeTimeActivityDisplayEnricherService()->enrich($dataService, [$activity])[0];

    expect($enriched->CustomerRef)->toMatchArray(['value' => '58', 'name' => 'Test ABC'])
        ->and($enriched->ProjectRef)->toMatchArray(['value' => '22', 'name' => 'Website'])
        ->and($enriched->ItemRef)->toMatchArray(['value' => '6', 'name' => 'Gardening']);
});

it('skips enrichment when reference names are already present', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldNotReceive('Query');

    $activity = (object) [
        'CustomerRef' => (object) ['value' => '58', 'name' => 'Cached Client'],
        'ItemRef' => (object) ['value' => '6', 'name' => 'Cached Service'],
    ];

    $enriched = makeTimeActivityDisplayEnricherService()->enrich($dataService, [$activity])[0];

    expect($enriched->CustomerRef->name)->toBe('Cached Client')
        ->and($enriched->ItemRef->name)->toBe('Cached Service');
});

it('returns an empty list unchanged', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldNotReceive('Query');

    expect(makeTimeActivityDisplayEnricherService()->enrich($dataService, []))->toBe([]);
});

it('enriches array shaped activities and skips blank array reference names', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Customer WHERE Id IN \\('58'\\)/"))
        ->andReturn([(object) ['Id' => '58', 'DisplayName' => 'Test ABC']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $activities = [[
        'CustomerRef' => '58',
        'ProjectRef' => ['value' => '22', 'name' => 'Cached Project'],
    ]];

    $enriched = makeTimeActivityDisplayEnricherService()->enrich($dataService, $activities);

    expect($enriched[0]['CustomerRef'])->toBe(['value' => '58', 'name' => 'Test ABC'])
        ->and($enriched[0]['ProjectRef'])->toBe(['value' => '22', 'name' => 'Cached Project']);
});

it('skips enrichment when references are missing', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldNotReceive('Query');

    $activity = (object) ['Id' => '1'];

    expect(makeTimeActivityDisplayEnricherService()->enrich($dataService, [$activity]))->toHaveCount(1);
});

it('enriches object references with blank names', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Item WHERE Id IN \\('6'\\)/"))
        ->andReturn([(object) ['Id' => '6', 'Name' => 'Gardening']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $activity = (object) [
        'ItemRef' => (object) ['value' => '6', 'name' => ''],
    ];

    $enriched = makeTimeActivityDisplayEnricherService()->enrich($dataService, [$activity])[0];

    expect($enriched->ItemRef)->toEqual((object) ['value' => '6', 'name' => 'Gardening']);
});

it('enriches object references that only include a value', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Customer WHERE Id IN \\('58'\\)/"))
        ->andReturn([(object) ['Id' => '58', 'DisplayName' => 'Test ABC']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $activity = (object) [
        'CustomerRef' => (object) ['value' => '58'],
    ];

    $enriched = makeTimeActivityDisplayEnricherService()->enrich($dataService, [$activity])[0];

    expect($enriched->CustomerRef)->toEqual((object) ['value' => '58', 'name' => 'Test ABC']);
});

it('skips enrichment when lookup results omit a display name', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Customer WHERE Id IN \\('58'\\)/"))
        ->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $activity = (object) ['CustomerRef' => '58'];

    $enriched = makeTimeActivityDisplayEnricherService()->enrich($dataService, [$activity])[0];

    expect($enriched->CustomerRef)->toBe('58');
});

it('ignores non-object activity rows during enrichment', function () {
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldNotReceive('Query');

    $activities = ['not-an-activity'];

    expect(makeTimeActivityDisplayEnricherService()->enrich($dataService, $activities))->toBe($activities);
});
