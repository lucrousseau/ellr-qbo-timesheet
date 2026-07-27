<?php

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeService;
use App\Services\QuickBooksService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Http\Exceptions\HttpResponseException;
use QuickBooksOnline\API\DataService\DataService;

covers(QboEmployeeService::class);

it('updates a user mapping when the employee exists in quickbooks', function () {
    $admin = User::factory()->admin()->make();
    $target = User::factory()->make(['id' => 2]);
    $token = QuickBooksToken::factory()->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) ['Id' => '42']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($admin)->andReturn($token);

    $target = Mockery::mock(User::class)->makePartial();
    $target->shouldReceive('update')->once()->with([
        'qbo_employee_ref' => '42',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $target->shouldReceive('fresh')->once()->andReturnSelf();

    $service = new QboEmployeeService($quickBooks, $tokenResolver);

    expect($service->updateMapping($admin, $target, [
        'qbo_employee_ref' => '42',
        'qbo_employee_name' => 'Jane Doe',
    ]))->toBe($target);
});

it('casts numeric employee refs before quickbooks lookup', function () {
    $admin = User::factory()->admin()->make();
    $target = User::factory()->make(['id' => 2]);
    $token = QuickBooksToken::factory()->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) ['Id' => '42']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($admin)->andReturn($token);

    $target = Mockery::mock(User::class)->makePartial();
    $target->shouldReceive('update')->once()->with([
        'qbo_employee_ref' => '42',
        'qbo_employee_name' => null,
    ]);
    $target->shouldReceive('fresh')->once()->andReturnSelf();

    $service = new QboEmployeeService($quickBooks, $tokenResolver);

    expect($service->updateMapping($admin, $target, [
        'qbo_employee_ref' => 42,
    ]))->toBe($target);
});

it('aborts when the employee does not exist in quickbooks', function () {
    $admin = User::factory()->admin()->make();
    $target = User::factory()->make();
    $token = QuickBooksToken::factory()->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->andReturn($token);

    $service = new QboEmployeeService($quickBooks, $tokenResolver);

    try {
        $service->updateMapping($admin, $target, ['qbo_employee_ref' => '999']);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => 'QuickBooks employee not found.',
                'error' => 'qbo_employee_invalid',
            ]);
    }
});

it('throws when quickbooks employee lookup fails', function () {
    $admin = User::factory()->admin()->make();
    $target = User::factory()->make();
    $token = QuickBooksToken::factory()->make();

    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('lookup failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->andReturn($token);

    $service = new QboEmployeeService($quickBooks, $tokenResolver);

    expect(fn () => $service->updateMapping($admin, $target, ['qbo_employee_ref' => '42']))
        ->toThrow(QuickBooksException::class, 'QuickBooks employee lookup failed.');
});
