<?php

use App\Services\QuickBooksApiErrorFormatterService;
use App\Support\TimeActivityReferenceNameLookup;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TimeActivityReferenceNameLookup::class);

function makeTimeActivityReferenceNameLookup(): TimeActivityReferenceNameLookup
{
    return new TimeActivityReferenceNameLookup(new QuickBooksApiErrorFormatterService);
}

it('loads customer and item names in bounded batches', function () {
    config(['quickbooks.list_max_results' => 500]);

    $lookup = makeTimeActivityReferenceNameLookup();
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Customer WHERE Id IN \\('11','22'\\)/"))
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => '  Acme  '],
            (object) ['Id' => '22', 'DisplayName' => ''],
            (object) ['Id' => '', 'DisplayName' => 'Ignored'],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Item WHERE Id IN \\('6'\\)/"))
        ->andReturn([(object) ['Id' => '6', 'Name' => 'Gardening']]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($lookup->customerNames($dataService, ['11', 'ref-22', '']))
        ->toBe(['11' => 'Acme'])
        ->and($lookup->itemNames($dataService, ['6']))->toBe(['6' => 'Gardening']);
});

it('returns empty maps when no references are provided', function () {
    $lookup = makeTimeActivityReferenceNameLookup();
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')->never();

    expect($lookup->customerNames($dataService, []))->toBe([])
        ->and($lookup->itemNames($dataService, ['', '   ']))->toBe([]);
});

it('aborts when quickbooks name lookups fail', function () {
    $error = Mockery::mock();
    $error->shouldReceive('getHttpStatusCode')->andReturn(400);
    $error->shouldReceive('getResponseBody')->andReturn('lookup failed');

    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    expect(fn () => makeTimeActivityReferenceNameLookup()->customerNames($dataService, ['11']))
        ->toThrow(HttpResponseException::class);
});

it('paginates reference lookups in chunks of one hundred', function () {
    $lookup = makeTimeActivityReferenceNameLookup();
    $dataService = Mockery::mock(stdClass::class);
    $firstBatch = array_map(
        fn (int $id): object => (object) ['Id' => (string) $id, 'DisplayName' => "Customer {$id}"],
        range(1, 100),
    );
    $secondBatch = [(object) ['Id' => '101', 'DisplayName' => 'Customer 101']];

    $dataService->shouldReceive('Query')->twice()->andReturn($firstBatch, $secondBatch);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $names = $lookup->customerNames($dataService, array_map('strval', range(1, 101)));

    expect($names)->toHaveCount(101)
        ->and($names['101'])->toBe('Customer 101');
});

it('skips entities without ids or display names', function () {
    $lookup = makeTimeActivityReferenceNameLookup();
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '', 'DisplayName' => 'Ignored'],
            (object) ['Id' => '11', 'DisplayName' => '   '],
            (object) ['Id' => '12', 'DisplayName' => 'Valid Corp'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($lookup->customerNames($dataService, ['11', '12']))->toBe(['12' => 'Valid Corp']);
});

it('skips item entities with blank names', function () {
    $lookup = makeTimeActivityReferenceNameLookup();
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '6', 'Name' => '   '],
            (object) ['Id' => '7', 'Name' => 'Gardening'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($lookup->itemNames($dataService, ['6', '7']))->toBe(['7' => 'Gardening']);
});
