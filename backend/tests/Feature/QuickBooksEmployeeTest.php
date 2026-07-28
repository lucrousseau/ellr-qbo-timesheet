<?php

use App\Http\Controllers\Api\QuickBooksEmployeeController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeListService;
use App\Services\QboListCacheService;
use App\Services\QuickBooksService;
use QuickBooksOnline\API\DataService\DataService;

covers(QuickBooksEmployeeController::class);
covers(QboEmployeeListService::class);
covers(QboListCacheService::class);

it('lists active quickbooks employees for administrators', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) [
                'Id' => '42',
                'DisplayName' => 'Jane Doe',
                'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->getJson('/api/quickbooks/employees', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '42')
        ->assertJsonPath('data.0.display_name', 'Jane Doe')
        ->assertJsonPath('data.0.email', 'jane@example.com');

    $this->actingAs($admin)
        ->getJson('/api/quickbooks/employees', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '42');
});

it('refreshes cached quickbooks employees when requested', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->getJson('/api/quickbooks/employees', frontendHeaders())
        ->assertOk();

    $this->actingAs($admin)
        ->getJson('/api/quickbooks/employees?refresh=1', frontendHeaders())
        ->assertOk();
});

it('requires administrator access to list quickbooks employees', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/quickbooks/employees', frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});
