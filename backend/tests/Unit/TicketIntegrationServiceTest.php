<?php

use App\Services\TicketIntegrationService;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TicketIntegrationService::class);

it('reports disabled providers as disconnected by default', function () {
    config([
        'integrations.jira.enabled' => false,
        'integrations.jira.connected' => false,
        'integrations.linear.enabled' => true,
        'integrations.linear.connected' => false,
    ]);

    $service = new TicketIntegrationService;

    expect($service->status())->toBe([
        'jira' => ['enabled' => false, 'connected' => false],
        'linear' => ['enabled' => true, 'connected' => false],
    ])->and($service->pickerAvailable())->toBeFalse();
});

it('marks the picker available when an enabled provider is connected', function () {
    config([
        'integrations.jira.enabled' => true,
        'integrations.jira.connected' => true,
        'integrations.linear.enabled' => false,
        'integrations.linear.connected' => false,
    ]);

    $service = new TicketIntegrationService;

    expect($service->pickerAvailable())->toBeTrue();

    config([
        'integrations.jira.enabled' => false,
        'integrations.jira.connected' => false,
        'integrations.linear.enabled' => true,
        'integrations.linear.connected' => true,
    ]);

    expect($service->pickerAvailable())->toBeTrue();
});

it('requires both enabled and connected for picker availability', function () {
    config([
        'integrations.jira.enabled' => true,
        'integrations.jira.connected' => false,
        'integrations.linear.enabled' => false,
        'integrations.linear.connected' => true,
    ]);

    expect((new TicketIntegrationService)->pickerAvailable())->toBeFalse();
});

it('aborts search while provider oauth is not connected', function () {
    $service = new TicketIntegrationService;

    try {
        $service->search('PROJ', 'jira');
        expect(false)->toBeTrue();
    } catch (HttpResponseException $exception) {
        $response = $exception->getResponse();

        expect($response->getStatusCode())->toBe(503)
            ->and($response->getData(true))->toMatchArray([
                'error' => 'integration_disabled',
            ])
            ->and($response->getData(true))->toHaveKey('message')
            ->and($response->getData(true)['message'])->not->toBe('');
    }
});
