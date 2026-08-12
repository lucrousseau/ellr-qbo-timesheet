<?php

use App\Http\Controllers\Api\AdminQboProjectController;
use App\Models\QuickBooksToken;
use App\Services\QboCustomerListService;
use App\Services\QboListCacheService;
use App\Services\QboProjectService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\DataService\DataService;
use Tests\Support\MockeryCapture;

uses(RefreshDatabase::class);

covers(AdminQboProjectController::class);
covers(QboProjectService::class);

it('creates a quickbooks project for administrators', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $captured = null;

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->with(Mockery::capture($captured))
        ->andReturn((object) ['Id' => '55', 'DisplayName' => 'Website redesign']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listAllActive')
            ->once()
            ->with(Mockery::any(), true)
            ->andReturn([
                ['id' => '11', 'display_name' => 'Acme Corp'],
            ]);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/quickbooks/projects', [
            'customer_ref' => '11',
            'display_name' => 'Website redesign',
        ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('data.id', '55')
        ->assertJsonPath('data.display_name', 'Website redesign');

    $payload = MockeryCapture::unwrap($captured);
    expect((string) $payload->ParentRef->value)->toBe('11');
});

it('validates project create payloads', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $this->actingAs($admin)
        ->postJson('/api/admin/quickbooks/projects', [], frontendHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_ref', 'display_name']);
});

it('rejects project creation when quickbooks is not connected', function () {
    $admin = actingAsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/quickbooks/projects', [
            'customer_ref' => '11',
            'display_name' => 'Website redesign',
        ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'quickbooks_not_connected');
});

it('rejects project creation for non-administrators', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();
    $user = timesheetUserFor($admin);

    $this->actingAs($user)
        ->postJson('/api/admin/quickbooks/projects', [
            'customer_ref' => '11',
            'display_name' => 'Website redesign',
        ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('rejects project creation for an unknown parent customer', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listAllActive')
            ->once()
            ->andReturn([
                ['id' => '11', 'display_name' => 'Acme Corp'],
            ]);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/quickbooks/projects', [
            'customer_ref' => '99',
            'display_name' => 'Orphan project',
        ], frontendHeaders())
        ->assertStatus(422)
        ->assertJsonPath('message', __('api.admin_invalid_project_parent'));
});

it('forgets the project list cache after creating a project', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '55', 'DisplayName' => 'New']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listAllActive')->once()->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    });

    $this->mock(QboListCacheService::class, function ($mock) {
        $mock->shouldReceive('forget')
            ->once()
            ->with(QboListCacheService::RESOURCE_PROJECTS, 'realm-42');
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/quickbooks/projects', [
            'customer_ref' => '11',
            'display_name' => 'New',
        ], frontendHeaders())
        ->assertCreated();
});
