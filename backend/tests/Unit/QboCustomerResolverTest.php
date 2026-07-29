<?php

use App\Support\QboCustomerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(QboCustomerResolver::class);

uses(RefreshDatabase::class);

it('resolves job customers to their parent customer', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('99'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '99',
                'DisplayName' => 'Website redesign',
                'Job' => true,
                'ParentRef' => (object) ['value' => '11'],
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Acme Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);

    $dataService->shouldReceive('getLastError')->andReturn(null);

    $customers = $resolver->resolveTopLevelCustomers($dataService, ['99'], 100);

    expect($customers)->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('extracts customer and project references from time activities', function () {
    $resolver = app(QboCustomerResolver::class);
    $activity = (object) [
        'CustomerRef' => (object) ['value' => '11'],
        'ProjectRef' => (object) ['value' => '22'],
    ];

    expect($resolver->extractCustomerRef($activity))->toBe('11')
        ->and($resolver->extractProjectRef($activity))->toBe('22');
});

it('skips inactive customers and empty references', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Inactive Corp',
                'Job' => false,
                'Active' => false,
            ],
            (object) [
                'Id' => '12',
                'DisplayName' => 'Active Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $customers = $resolver->resolveTopLevelCustomers($dataService, ['11', '12', ''], 100);

    expect($customers)->toBe([
        ['id' => '12', 'display_name' => 'Active Corp'],
    ]);
});

it('returns null for missing activity references', function () {
    $resolver = app(QboCustomerResolver::class);

    expect($resolver->extractCustomerRef((object) []))->toBeNull()
        ->and($resolver->extractProjectRef((object) []))->toBeNull();
});

it('returns an empty list when no customer references are provided', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')->never();

    expect($resolver->resolveTopLevelCustomers($dataService, [], 100))->toBe([]);
});

it('deduplicates and normalizes numeric customer references', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Acme Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $customers = $resolver->resolveTopLevelCustomers($dataService, ['11', '11', 'ref-11'], 100);

    expect($customers)->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('extracts references from scalar and integer aggregates', function () {
    $resolver = app(QboCustomerResolver::class);

    expect($resolver->extractCustomerRef((object) ['CustomerRef' => '15']))->toBe('15')
        ->and($resolver->extractCustomerRef((object) ['CustomerRef' => 16]))->toBe('16')
        ->and($resolver->extractCustomerRef((object) ['CustomerRef' => (object) ['value' => '']]))->toBeNull();
});

it('sorts resolved customers by display name', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '12', 'DisplayName' => 'Zulu Corp', 'Job' => false, 'Active' => true],
            (object) ['Id' => '11', 'DisplayName' => 'Alpha Corp', 'Job' => false, 'Active' => true],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['11', '12'], 100))->toBe([
        ['id' => '11', 'display_name' => 'Alpha Corp'],
        ['id' => '12', 'display_name' => 'Zulu Corp'],
    ]);
});

it('skips inactive parent customers for job references', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('99'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '99',
                'DisplayName' => 'Website redesign',
                'Job' => true,
                'ParentRef' => (object) ['value' => '11'],
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Inactive parent',
                'Job' => false,
                'Active' => false,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['99'], 100))->toBe([]);
});

it('aborts when customer lookup query fails', function () {
    $resolver = app(QboCustomerResolver::class);
    $error = Mockery::mock();
    $error->shouldReceive('getHttpStatusCode')->andReturn(400);
    $error->shouldReceive('getResponseBody')->andReturn('query failed');

    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')->once()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    expect(fn () => $resolver->resolveTopLevelCustomers($dataService, ['11'], 100))
        ->toThrow(HttpResponseException::class);
});

it('skips job customers without a parent reference', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) [
                'Id' => '99',
                'DisplayName' => 'Orphan job',
                'Job' => true,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['99'], 100))->toBe([]);
});

it('skips parent customers that are also jobs', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('99'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '99',
                'DisplayName' => 'Website redesign',
                'Job' => true,
                'ParentRef' => (object) ['value' => '11'],
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Nested job parent',
                'Job' => true,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['99'], 100))->toBe([]);
});

it('paginates customer lookups in batches of one hundred', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $firstBatch = array_map(
        fn (int $id): object => (object) [
            'Id' => (string) $id,
            'DisplayName' => "Customer {$id}",
            'Job' => false,
            'Active' => true,
        ],
        range(1, 100),
    );
    $secondBatch = [
        (object) ['Id' => '101', 'DisplayName' => 'Customer 101', 'Job' => false, 'Active' => true],
    ];

    $dataService->shouldReceive('Query')->twice()->andReturn($firstBatch, $secondBatch);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $refs = array_map('strval', range(1, 101));
    $customers = $resolver->resolveTopLevelCustomers($dataService, $refs, 1000);

    expect($customers)->toHaveCount(101);
});

it('treats customers without an active flag as active', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Legacy Corp',
                'Job' => false,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['11'], 100))->toBe([
        ['id' => '11', 'display_name' => 'Legacy Corp'],
    ]);
});

it('returns an empty list when every customer reference is blank', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')->never();

    expect($resolver->resolveTopLevelCustomers($dataService, ['', '   '], 100))->toBe([]);
});

it('extracts project references from scalar values', function () {
    $resolver = app(QboCustomerResolver::class);

    expect($resolver->extractProjectRef((object) ['ProjectRef' => '22']))->toBe('22')
        ->and($resolver->extractProjectRef((object) ['ProjectRef' => 23]))->toBe('23');
});

it('maps customers with missing display names to empty strings', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '11', 'Job' => false, 'Active' => true],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['11'], 100))->toBe([
        ['id' => '11', 'display_name' => ''],
    ]);
});

it('resolves a mix of top-level customers and job references', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('11','99'\\)/"))
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => 'Acme Corp', 'Job' => false, 'Active' => true],
            (object) [
                'Id' => '99',
                'DisplayName' => 'Website',
                'Job' => true,
                'ParentRef' => (object) ['value' => '12'],
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('12'\\)/"))
        ->andReturn([
            (object) ['Id' => '12', 'DisplayName' => 'Parent Corp', 'Job' => false, 'Active' => true],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['11', '99'], 100))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
        ['id' => '12', 'display_name' => 'Parent Corp'],
    ]);
});

it('reads activity references from array rows and item refs', function () {
    $resolver = app(QboCustomerResolver::class);

    expect($resolver->activityRef([
        'CustomerRef' => (object) ['value' => '11'],
        'ItemRef' => 33,
    ], 'CustomerRef'))->toBe('11')
        ->and($resolver->activityRef([
            'CustomerRef' => (object) ['value' => '11'],
            'ItemRef' => 33,
        ], 'ItemRef'))->toBe('33')
        ->and($resolver->extractItemRef((object) ['ItemRef' => (object) ['value' => '6']]))->toBe('6')
        ->and($resolver->activityRef('not-an-activity', 'CustomerRef'))->toBeNull();
});

it('returns null for empty quickbooks reference values', function () {
    $resolver = app(QboCustomerResolver::class);

    expect($resolver->extractCustomerRef((object) ['CustomerRef' => (object) ['value' => '']]))->toBeNull()
        ->and($resolver->extractCustomerRef((object) ['CustomerRef' => '']))->toBeNull()
        ->and($resolver->activityRef(123, 'CustomerRef'))->toBeNull();
});

it('resolves a direct customer without parent lookups', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Acme Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['11'], 100))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('deduplicates parent lookups for multiple job customers', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('99','98'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '99',
                'DisplayName' => 'Job A',
                'Job' => true,
                'ParentRef' => (object) ['value' => '11'],
                'Active' => true,
            ],
            (object) [
                'Id' => '98',
                'DisplayName' => 'Job B',
                'Job' => true,
                'ParentRef' => (object) ['value' => '11'],
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/Id IN \\('11'\\)/"))
        ->andReturn([
            (object) [
                'Id' => '11',
                'DisplayName' => 'Parent Corp',
                'Job' => false,
                'Active' => true,
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['99', '98'], 100))->toBe([
        ['id' => '11', 'display_name' => 'Parent Corp'],
    ]);
});

it('deduplicates direct customers returned under the same identifier', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => 'Acme Corp', 'Job' => false, 'Active' => true],
            (object) ['Id' => '11', 'DisplayName' => 'Duplicate Corp', 'Job' => false, 'Active' => true],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, ['11'], 100))->toBe([
        ['id' => '11', 'display_name' => 'Duplicate Corp'],
    ]);
});

it('returns null for reference objects without values', function () {
    $resolver = app(QboCustomerResolver::class);

    expect($resolver->extractCustomerRef((object) ['CustomerRef' => (object) ['name' => 'Acme']]))->toBeNull();
});

it('queries one batch for exactly one hundred customer references', function () {
    $resolver = app(QboCustomerResolver::class);
    $dataService = Mockery::mock(stdClass::class);
    $batch = array_map(
        fn (int $id): object => (object) [
            'Id' => (string) $id,
            'DisplayName' => "Customer {$id}",
            'Job' => false,
            'Active' => true,
        ],
        range(1, 100),
    );

    $dataService->shouldReceive('Query')->once()->andReturn($batch);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    expect($resolver->resolveTopLevelCustomers($dataService, array_map('strval', range(1, 100)), 1000))
        ->toHaveCount(100);
});

it('returns null when activity rows are neither arrays nor objects', function () {
    $resolver = app(QboCustomerResolver::class);

    expect($resolver->activityRef(42, 'CustomerRef'))->toBeNull();
});
