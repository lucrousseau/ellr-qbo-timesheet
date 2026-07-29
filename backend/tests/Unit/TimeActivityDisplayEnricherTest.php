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
