<?php

use App\Http\Controllers\Api\QboEmployeeController;
use App\Models\User;

covers(QboEmployeeController::class);

it('updates the authenticated users qbo employee mapping', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/user/qbo-employee', [
            'qbo_employee_ref' => '42',
            'qbo_employee_name' => 'Jane Doe',
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.qbo_employee_ref', '42')
        ->assertJsonPath('user.qbo_employee_name', 'Jane Doe');

    expect($user->fresh()->qbo_employee_ref)->toBe('42');
});

it('validates qbo employee payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/user/qbo-employee', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qbo_employee_ref']);
});
