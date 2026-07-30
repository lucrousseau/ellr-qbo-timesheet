<?php

use App\Models\QuickBooksToken;
use App\Services\QuickBooksCompanyInfoService;
use App\Services\QuickBooksService;
use QuickBooksOnline\API\DataService\DataService;

covers(QuickBooksCompanyInfoService::class);

it('reads default timezone from quickbooks company info', function () {
    $token = QuickBooksToken::factory()->make(['realm_id' => '123']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('getCompanyInfo')
        ->once()
        ->andReturn((object) ['DefaultTimeZone' => 'America/New_York']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->is($token)))
        ->andReturn($dataService);

    expect((new QuickBooksCompanyInfoService($quickBooks))->fetchDefaultTimezone($token))
        ->toBe('America/New_York');
});

it('returns null when quickbooks company info is unavailable', function () {
    $token = QuickBooksToken::factory()->make();
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('getCompanyInfo')->once()->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    expect((new QuickBooksCompanyInfoService($quickBooks))->fetchDefaultTimezone($token))->toBeNull();
});
