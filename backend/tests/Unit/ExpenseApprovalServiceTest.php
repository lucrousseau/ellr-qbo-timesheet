<?php

use App\Enums\ExpenseStatus;
use App\Jobs\SyncApprovedExpenseToQuickBooksJob;
use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\ExpenseApprovalService;
use App\Services\ExpensePickerValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Queue;

covers(ExpenseApprovalService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    mockQboExpensePickers();
});

it('lists only pending expenses for administrators', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    Expense::factory()->forUser($employee)->create(['description' => 'Needs review']);
    Expense::factory()->forUser($employee)->approved()->create();

    $response = app(ExpenseApprovalService::class)->listPendingForReviewer($admin, 1, 10);

    expect($response['data'])->toHaveCount(1)
        ->and($response['data'][0]['description'])->toBe('Needs review')
        ->and($response['meta']['truncated'])->toBeFalse();
});

it('limits pending expenses to direct reports for supervisors', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $directReport = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'supervisor_id' => $supervisor->id,
    ]);
    $otherEmployee = User::factory()->create(['organization_id' => $admin->organization_id]);
    Expense::factory()->forUser($directReport)->create(['description' => 'Direct report']);
    Expense::factory()->forUser($otherEmployee)->create(['description' => 'Other employee']);

    $response = app(ExpenseApprovalService::class)->listPendingForReviewer($supervisor, 1, 10);

    expect($response['data'])->toHaveCount(1)
        ->and($response['data'][0]['description'])->toBe('Direct report');
});

it('marks pending approval lists as truncated when more rows exist', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    foreach (range(1, 3) as $index) {
        Expense::factory()->forUser($employee)->create([
            'txn_date' => now()->subDays($index)->toDateString(),
        ]);
    }

    $response = app(ExpenseApprovalService::class)->listPendingForReviewer($admin, 1, 2);

    expect($response['data'])->toHaveCount(2)
        ->and($response['meta']['truncated'])->toBeTrue()
        ->and($response['meta']['count'])->toBe(2);
});

it('rejects a pending expense without syncing to quickbooks', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $expense = Expense::factory()->forUser($employee)->create();

    $rejected = app(ExpenseApprovalService::class)->reject($admin, $expense->id, 'Wrong category');

    expect($rejected->status)->toBe(ExpenseStatus::Rejected)
        ->and($rejected->rejection_reason)->toBe('Wrong category')
        ->and($rejected->reviewed_by_id)->toBe($admin->id)
        ->and($rejected->qbo_id)->toBeNull();
});

it('approves a pending expense and queues quickbooks synchronization', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $expense = Expense::factory()->forUser($employee)->create([
        'amount' => 12.34,
        'description' => 'Ready for approval',
    ]);

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidExpense')->once();
    });

    $approved = app(ExpenseApprovalService::class)->approve($admin, $expense->id);

    expect($approved->status)->toBe(ExpenseStatus::Approved)
        ->and($approved->reviewed_by_id)->toBe($admin->id)
        ->and($approved->rejection_reason)->toBeNull()
        ->and($approved->qbo_id)->toBeNull();

    Queue::assertPushed(SyncApprovedExpenseToQuickBooksJob::class, function (SyncApprovedExpenseToQuickBooksJob $job) use ($expense, $employee): bool {
        $token = QuickBooksToken::query()->where('realm_id', 'realm-42')->first();

        return $job->expenseId === $expense->id
            && $job->employeeId === $employee->id
            && $token !== null
            && $job->tokenId === $token->id
            && $job->payload['amount'] === (float) $expense->amount
            && $job->payload['description'] === 'Ready for approval';
    });
});

it('validates picker references before approving expenses', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $expense = Expense::factory()->forUser($employee)->create([
        'customer_ref' => '11',
    ]);

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidExpense')
            ->once()
            ->andThrow(new RuntimeException('stop after validation'));
    });

    expect(fn () => app(ExpenseApprovalService::class)->approve($admin, $expense->id))
        ->toThrow(RuntimeException::class);

    Queue::assertNothingPushed();
});

it('returns not found when approving a non pending expense', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $expense = Expense::factory()->forUser($employee)->approved()->create();

    app(ExpenseApprovalService::class)->approve($admin, $expense->id);
})->throws(HttpResponseException::class);

it('returns not found when rejecting a non pending expense', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $expense = Expense::factory()->forUser($employee)->approved()->create();

    app(ExpenseApprovalService::class)->reject($admin, $expense->id, 'Too late');
})->throws(HttpResponseException::class);

it('clears rejection reason when approving a pending expense', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $expense = Expense::factory()->forUser($employee)->create();

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidExpense')->once();
    });

    $approved = app(ExpenseApprovalService::class)->approve($admin, $expense->id);

    expect($approved->rejection_reason)->toBeNull()
        ->and($approved->reviewed_at)->not->toBeNull();
});
