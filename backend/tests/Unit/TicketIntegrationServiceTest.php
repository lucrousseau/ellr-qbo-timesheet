<?php

use App\Services\TicketIntegrationService;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TicketIntegrationService::class);

it('reports disabled providers as disconnected by default', function () {
    config([
        'integrations.jira.enabled' => false,
        'integrations.linear.enabled' => true,
    ]);

    $service = new TicketIntegrationService;

    expect($service->status())->toBe([
        'jira' => ['enabled' => false, 'connected' => false],
        'linear' => ['enabled' => true, 'connected' => false],
    ])->and($service->pickerAvailable())->toBeFalse();
});

it('aborts search while provider oauth is not connected', function () {
    $service = new TicketIntegrationService;

    expect(fn () => $service->search('PROJ'))
        ->toThrow(HttpResponseException::class);
});
