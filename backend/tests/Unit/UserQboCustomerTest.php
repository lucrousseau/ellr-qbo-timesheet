<?php

use App\Models\User;
use App\Models\UserQboCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(UserQboCustomer::class);

it('belongs to the assigned timesheet user', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $assignment = $user->qboCustomers()->create([
        'qbo_customer_ref' => '11',
    ]);

    expect($assignment)->toBeInstanceOf(UserQboCustomer::class)
        ->and($assignment->user->is($user))->toBeTrue()
        ->and($assignment->qbo_customer_ref)->toBe('11');
});
