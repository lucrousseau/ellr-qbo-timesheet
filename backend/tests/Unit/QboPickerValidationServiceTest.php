<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboCustomerListService;
use App\Services\QboPickerValidationService;
use App\Services\QboProjectListService;
use App\Services\QboServiceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(QboPickerValidationService::class);

uses(RefreshDatabase::class);

it('rejects customer refs that are not available for the employee', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listForUser')->once()->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    });

    $service = app(QboPickerValidationService::class);

    expect(fn () => $service->assertValidSelections($user, $token, [
        'customer_ref' => '99',
        'project_ref' => null,
        'service_ref' => null,
    ]))->toThrow(HttpResponseException::class);
});

it('rejects projects that are not available for the selected client', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listForUser')->once()->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    });
    $this->mock(QboProjectListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listForCustomer')->once()->with($token, '11')->andReturn([
            ['id' => '22', 'display_name' => 'Website'],
        ]);
    });

    $service = app(QboPickerValidationService::class);

    expect(fn () => $service->assertValidSelections($user, $token, [
        'customer_ref' => '11',
        'project_ref' => '99',
        'service_ref' => null,
    ]))->toThrow(HttpResponseException::class);
});

it('rejects projects without a selected client', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldNotReceive('listForUser');
    });

    $service = app(QboPickerValidationService::class);

    expect(fn () => $service->assertValidSelections($user, $token, [
        'customer_ref' => null,
        'project_ref' => '22',
        'service_ref' => null,
    ]))->toThrow(HttpResponseException::class);
});

it('rejects service refs that are not available in quickbooks', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboServiceListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listActive')->once()->with($token)->andReturn([
            ['id' => '33', 'display_name' => 'Consulting'],
        ]);
    });

    $service = app(QboPickerValidationService::class);

    expect(fn () => $service->assertValidSelections($user, $token, [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => '99',
    ]))->toThrow(HttpResponseException::class);
});

it('accepts valid picker selections', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('listForUser')->once()->with($user, $token)->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    });
    $this->mock(QboProjectListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listForCustomer')->once()->with($token, '11')->andReturn([
            ['id' => '22', 'display_name' => 'Website'],
        ]);
    });
    $this->mock(QboServiceListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listActive')->once()->with($token)->andReturn([
            ['id' => '33', 'display_name' => 'Consulting'],
        ]);
    });

    $service = app(QboPickerValidationService::class);
    $service->assertValidSelections($user, $token, [
        'customer_ref' => '11',
        'project_ref' => '22',
        'service_ref' => '33',
    ]);

    expect(true)->toBeTrue();
});

it('skips validation for blank picker references', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldNotReceive('listForUser');
    });
    $this->mock(QboProjectListService::class, function ($mock) {
        $mock->shouldNotReceive('listForCustomer');
    });
    $this->mock(QboServiceListService::class, function ($mock) {
        $mock->shouldNotReceive('listActive');
    });

    app(QboPickerValidationService::class)->assertValidSelections($user, $token, [
        'customer_ref' => '   ',
        'project_ref' => null,
        'service_ref' => '',
    ]);

    expect(true)->toBeTrue();
});

it('accepts a valid customer without project or service selections', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('listForUser')->once()->with($user, $token)->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    });
    $this->mock(QboProjectListService::class, function ($mock) {
        $mock->shouldNotReceive('listForCustomer');
    });
    $this->mock(QboServiceListService::class, function ($mock) {
        $mock->shouldNotReceive('listActive');
    });

    app(QboPickerValidationService::class)->assertValidSelections($user, $token, [
        'customer_ref' => '11',
        'project_ref' => null,
        'service_ref' => null,
    ]);

    expect(true)->toBeTrue();
});

it('returns 422 for invalid customer selections', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listForUser')->once()->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    });

    try {
        app(QboPickerValidationService::class)->assertValidSelections($user, $token, [
            'customer_ref' => '99',
            'project_ref' => null,
            'service_ref' => null,
        ]);
        expect(false)->toBeTrue('Expected HttpResponseException');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['message'])
            ->toBe(__('api.time_tracker_invalid_customer'));
    }
});

it('normalizes numeric picker references before validation', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboServiceListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listActive')->once()->with($token)->andReturn([
            ['id' => '33', 'display_name' => 'Consulting'],
        ]);
    });

    app(QboPickerValidationService::class)->assertValidSelections($user, $token, [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => 33,
    ]);

    expect(true)->toBeTrue();
});

it('returns 422 for invalid project selections', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listForUser')->once()->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    });
    $this->mock(QboProjectListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listForCustomer')->once()->with($token, '11')->andReturn([
            ['id' => '22', 'display_name' => 'Website'],
        ]);
    });

    try {
        app(QboPickerValidationService::class)->assertValidSelections($user, $token, [
            'customer_ref' => '11',
            'project_ref' => '99',
            'service_ref' => null,
        ]);
        expect(false)->toBeTrue('Expected HttpResponseException');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['message'])
            ->toBe(__('api.time_tracker_invalid_project'));
    }
});

it('returns 422 for invalid service selections', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboServiceListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listActive')->once()->with($token)->andReturn([
            ['id' => '33', 'display_name' => 'Consulting'],
        ]);
    });

    try {
        app(QboPickerValidationService::class)->assertValidSelections($user, $token, [
            'customer_ref' => null,
            'project_ref' => null,
            'service_ref' => '99',
        ]);
        expect(false)->toBeTrue('Expected HttpResponseException');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['message'])
            ->toBe(__('api.time_tracker_invalid_service'));
    }
});
