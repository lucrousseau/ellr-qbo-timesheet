<?php

use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use QuickBooksOnline\API\DataService\DataService;

uses(RefreshDatabase::class);

it('registers a founding administrator with a new organization', function () {
    config(['app.allow_registration' => true]);

    $this->postJson('/api/register', [
        'name' => 'Acme Admin',
        'organization_name' => 'Acme Inc',
        'email' => 'admin@acme.test',
        'password' => validTestPassword(),
        'password_confirmation' => validTestPassword(),
    ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('user.email', 'admin@acme.test');

    $user = User::query()->where('email', 'admin@acme.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->organization)->not->toBeNull()
        ->and($user->organization->name)->toBe('Acme Inc');
});

it('isolates admin user listings by organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    $adminA = User::factory()->admin()->create(['organization_id' => $organizationA->id]);
    User::factory()->create([
        'organization_id' => $organizationA->id,
        'qbo_employee_ref' => '7',
    ]);
    User::factory()->create([
        'organization_id' => $organizationB->id,
        'qbo_employee_ref' => '8',
    ]);

    $this->actingAs($adminA)
        ->getJson('/api/admin/users', frontendHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.qbo_employee_ref', '7');
});

it('does not allow an admin to revoke users from another organization', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin)
        ->deleteJson('/api/admin/users/'.$foreignUser->id, [], frontendHeaders())
        ->assertNotFound();

    expect(User::query()->find($foreignUser->id))->not->toBeNull();
});

it('returns not found when an admin requests a foreign organization user by id', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/users/'.$foreignUser->id.'/customers', frontendHeaders())
        ->assertNotFound();
});

it('resolves quickbooks tokens within the same organization only', function () {
    $organizationA = Organization::factory()->withRealm('realm-a')->create();
    $organizationB = Organization::factory()->withRealm('realm-b')->create();

    $adminA = User::factory()->admin()->create(['organization_id' => $organizationA->id]);
    $adminB = User::factory()->admin()->create(['organization_id' => $organizationB->id]);
    QuickBooksToken::factory()->forUser($adminB)->create(['realm_id' => 'realm-b']);

    $timesheetUser = User::factory()->create([
        'organization_id' => $organizationA->id,
        'qbo_employee_ref' => '7',
    ]);

    QuickBooksToken::factory()->forUser($adminA)->create(['realm_id' => 'realm-a']);

    $token = app(QuickBooksTokenResolverService::class)->resolve($timesheetUser);

    expect($token->realm_id)->toBe('realm-a');
});

it('rejects authenticated users without an organization on protected routes', function () {
    $user = User::factory()->admin()->make([
        'organization_id' => null,
        'email_verified_at' => now(),
    ]);
    $user->id = 1;

    $this->actingAs($user)
        ->getJson('/api/user', frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'organization_required');
});

it('keeps logout outside the organization middleware group', function () {
    $logoutRoute = collect(Route::getRoutes()->getRoutes())
        ->first(fn (Illuminate\Routing\Route $route) => $route->uri() === 'api/logout'
            && in_array('POST', $route->methods(), true));

    expect($logoutRoute)->not->toBeNull()
        ->and(collect($logoutRoute->gatherMiddleware())->contains('organization'))->toBeFalse();
});

it('rejects duplicate quickbooks employee refs within the same organization', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '42',
    ]);

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qbo_employee_ref']);
});

it('allows the same quickbooks employee ref when provisioning in another organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    $adminB = User::factory()->admin()->create(['organization_id' => $organizationB->id]);
    User::factory()->create([
        'organization_id' => $organizationA->id,
        'qbo_employee_ref' => '42',
    ]);

    Notification::fake();
    QuickBooksToken::factory()->forUser($adminB)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->twice()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Org B User',
        'PrimaryEmailAddr' => (object) ['Address' => 'org-b@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($adminB)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('user.qbo_employee_ref', '42');
});

it('returns not found when an admin maps a qbo employee for a foreign organization user', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin)
        ->patchJson('/api/admin/users/'.$foreignUser->id.'/qbo-employee', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertNotFound();
});

it('returns not found when an admin lists time activities for a foreign organization user', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/users/'.$foreignUser->id.'/time-activities', frontendHeaders())
        ->assertNotFound();
});

it('returns not found when an admin updates time activities for a foreign organization user', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin)
        ->patchJson('/api/admin/users/'.$foreignUser->id.'/time-activities/12', [
            'billable' => true,
        ], frontendHeaders())
        ->assertNotFound();
});

it('returns not found when an admin syncs customers for a foreign organization user', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $this->actingAs($admin)
        ->putJson('/api/admin/users/'.$foreignUser->id.'/customers', [
            'all_customers_access' => false,
            'customer_refs' => [],
        ], frontendHeaders())
        ->assertNotFound();
});
