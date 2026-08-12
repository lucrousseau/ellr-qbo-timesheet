<?php

use App\Models\QuickBooksToken;
use App\Services\QboCustomerListService;
use App\Services\QboListCacheService;
use App\Services\QboProjectService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\DataService\DataService;
use Tests\Support\MockeryCapture;

covers(QboProjectService::class);

/**
 * Builds a QboProjectService with injectable dependencies.
 *
 * @param  QuickBooksService  $quickBooks  QuickBooks SDK access mock.
 * @param  QboCustomerListService  $customers  Customer list mock.
 * @param  QboListCacheService|null  $listCache  Optional list cache instance.
 * @return QboProjectService
 */
function makeQboProjectService(
    QuickBooksService $quickBooks,
    QboCustomerListService $customers,
    ?QboListCacheService $listCache = null,
): QboProjectService {
    return new QboProjectService(
        $quickBooks,
        $customers,
        $listCache ?? new QboListCacheService,
        new QuickBooksApiErrorFormatterService,
    );
}

it('creates a quickbooks project under a valid parent customer', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $captured = null;

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->with(Mockery::capture($captured))
        ->andReturn((object) ['Id' => '55', 'DisplayName' => 'Website redesign']);
    $dataService->shouldReceive('getLastError')->once()->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token, true)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $listCache = Mockery::mock(QboListCacheService::class);
    $listCache->shouldReceive('forget')
        ->once()
        ->with(QboListCacheService::RESOURCE_PROJECTS, 'realm-42');

    $service = makeQboProjectService($quickBooks, $customers, $listCache);

    expect($service->createForCustomer($token, [
        'customer_ref' => '11',
        'display_name' => 'Website redesign',
    ]))->toBe([
        'id' => '55',
        'display_name' => 'Website redesign',
    ]);

    $payload = MockeryCapture::unwrap($captured);
    expect((string) $payload->DisplayName)->toBe('Website redesign')
        ->and((string) $payload->Job)->toBe('true')
        ->and((string) $payload->BillWithParent)->toBe('true')
        ->and((string) $payload->ParentRef->value)->toBe('11')
        ->and((string) $payload->ParentRef->name)->toBe('Acme Corp');
});

it('falls back to the submitted name when quickbooks omits display name', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->andReturn((object) ['Id' => '55', 'DisplayName' => '']);
    $dataService->shouldReceive('getLastError')->once()->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')->once()->andReturn([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);

    $listCache = Mockery::mock(QboListCacheService::class);
    $listCache->shouldReceive('forget')->once();

    $service = makeQboProjectService($quickBooks, $customers, $listCache);

    expect($service->createForCustomer($token, [
        'customer_ref' => '11',
        'display_name' => 'Website redesign',
    ]))->toBe([
        'id' => '55',
        'display_name' => 'Website redesign',
    ]);
});

it('prefers the display name returned by quickbooks when present', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->andReturn((object) ['Id' => '55', 'DisplayName' => 'Website Redesign (QBO)']);
    $dataService->shouldReceive('getLastError')->once()->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')->once()->andReturn([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);

    $listCache = Mockery::mock(QboListCacheService::class);
    $listCache->shouldReceive('forget')->once();

    $service = makeQboProjectService($quickBooks, $customers, $listCache);

    expect($service->createForCustomer($token, [
        'customer_ref' => '11',
        'display_name' => 'Website redesign',
    ]))->toBe([
        'id' => '55',
        'display_name' => 'Website Redesign (QBO)',
    ]);
});

it('rejects project creation when the parent customer is unknown', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldNotReceive('dataService');

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $service = makeQboProjectService($quickBooks, $customers);

    try {
        $service->createForCustomer($token, [
            'customer_ref' => '99',
            'display_name' => 'Missing parent',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['message'])->toBe(
                __('api.admin_invalid_project_parent'),
            );
    }
});

it('rejects blank project names after trimming', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldNotReceive('dataService');

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listAllActive');

    $service = makeQboProjectService($quickBooks, $customers);

    try {
        $service->createForCustomer($token, [
            'customer_ref' => '11',
            'display_name' => '   ',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => __('api.admin_invalid_project_parent'),
            ]);
    }
});

it('rejects blank customer references after normalization', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldNotReceive('dataService');

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listAllActive');

    $service = makeQboProjectService($quickBooks, $customers);

    try {
        $service->createForCustomer($token, [
            'customer_ref' => 'abc',
            'display_name' => 'Website redesign',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => __('api.admin_invalid_project_parent'),
            ]);
    }
});

it('aborts when quickbooks returns an sdk error', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('Duplicate Name Exists Error');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->andReturn((object) ['Id' => '55', 'DisplayName' => 'Website redesign']);
    $dataService->shouldReceive('getLastError')->once()->andReturn($error);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')->once()->andReturn([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);

    $listCache = Mockery::mock(QboListCacheService::class);
    $listCache->shouldNotReceive('forget');

    $service = makeQboProjectService($quickBooks, $customers, $listCache);

    try {
        $service->createForCustomer($token, [
            'customer_ref' => '11',
            'display_name' => 'Website redesign',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['message'])->toBe(
                __('api.quickbooks_api_error'),
            );
    }
});

it('aborts when quickbooks returns a project without an id', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['DisplayName' => 'Website redesign']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')->once()->andReturn([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);

    $listCache = Mockery::mock(QboListCacheService::class);
    $listCache->shouldReceive('forget')->once();

    $service = makeQboProjectService($quickBooks, $customers, $listCache);

    try {
        $service->createForCustomer($token, [
            'customer_ref' => '11',
            'display_name' => 'Website redesign',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => __('api.quickbooks_api_error'),
            ]);
    }
});

it('invalidates the cached project list after a successful create', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);
    Cache::flush();

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $cache = new QboListCacheService;
    $cacheKey = $cache->cacheKey(QboListCacheService::RESOURCE_PROJECTS, 'realm-42');
    Cache::put($cacheKey, [['id' => '22', 'display_name' => 'Old']], now()->addMinutes(15));

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '55', 'DisplayName' => 'New']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')->once()->andReturn([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);

    $service = makeQboProjectService($quickBooks, $customers, $cache);
    $service->createForCustomer($token, [
        'customer_ref' => '11',
        'display_name' => 'New',
    ]);

    expect(Cache::get($cacheKey))->toBeNull();
});
