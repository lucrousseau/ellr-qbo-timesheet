<?php

use App\Models\Expense;
use App\Models\User;
use App\Services\ExpenseAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

covers(ExpenseAuthorizationService::class);

uses(RefreshDatabase::class);

it('allows administrators to review expenses in their organization', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $expense = Expense::factory()->forUser($employee)->create();

    app(ExpenseAuthorizationService::class)->assertCanReview($admin, $expense);

    expect(true)->toBeTrue();
});

it('allows supervisors to review direct report expenses', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'supervisor_id' => $supervisor->id,
    ]);
    $expense = Expense::factory()->forUser($employee)->create();

    app(ExpenseAuthorizationService::class)->assertCanReview($supervisor, $expense);

    expect(true)->toBeTrue();
});

it('forbids employees from reviewing their own expenses', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $expense = Expense::factory()->forUser($employee)->create();

    app(ExpenseAuthorizationService::class)->assertCanReview($employee, $expense);
})->throws(HttpResponseException::class);

it('forbids unrelated employees from reviewing expenses', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $otherEmployee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '9',
    ]);
    $expense = Expense::factory()->forUser($employee)->create();

    app(ExpenseAuthorizationService::class)->assertCanReview($otherEmployee, $expense);
})->throws(HttpResponseException::class);

it('forbids supervisors from reviewing entries outside their direct reports', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $expense = Expense::factory()->forUser($employee)->create();

    app(ExpenseAuthorizationService::class)->assertCanReview($supervisor, $expense);
})->throws(HttpResponseException::class);

it('forbids reviewers from expenses in another organization', function () {
    $admin = User::factory()->admin()->create();
    $foreignEmployee = User::factory()->create();
    $expense = Expense::factory()->forUser($foreignEmployee)->create();

    app(ExpenseAuthorizationService::class)->assertCanReview($admin, $expense);
})->throws(NotFoundHttpException::class);
