<?php

/**
 * Tests for queued QuickBooks Purchase sync after expense approval.
 */

use App\Enums\ExpenseStatus;
use App\Jobs\SyncApprovedExpenseToQuickBooksJob;
use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

covers(SyncApprovedExpenseToQuickBooksJob::class);

uses(RefreshDatabase::class);

it('stores the quickbooks identifier for an approved expense', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $expense = Expense::factory()->forUser($employee)->create([
        'status' => ExpenseStatus::Approved,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
        'amount' => 12.34,
        'description' => 'Approved expense',
    ]);

    $purchases = Mockery::mock(PurchaseService::class);
    $purchases->shouldReceive('createForUser')
        ->once()
        ->with(
            Mockery::type(User::class),
            Mockery::type(QuickBooksToken::class),
            Mockery::type('array'),
        )
        ->andReturn((object) ['Id' => '501']);

    $job = new SyncApprovedExpenseToQuickBooksJob(
        $expense->id,
        $employee->id,
        $token->id,
        [
            'amount' => 12.34,
            'txn_date' => $expense->txn_date?->toDateString(),
            'payment_type' => 'Cash',
            'payment_account_ref' => '35',
            'expense_account_ref' => '7',
            'description' => 'Approved expense',
            'is_billable' => false,
        ],
    );

    $job->handle($purchases);

    $expense->refresh();

    expect($expense->status)->toBe(ExpenseStatus::Approved)
        ->and($expense->qbo_id)->toBe('501');
});

it('keeps the approval decision when quickbooks synchronization fails', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $expense = Expense::factory()->forUser($employee)->create([
        'status' => ExpenseStatus::Approved,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
    ]);

    $purchases = Mockery::mock(PurchaseService::class);
    $purchases->shouldReceive('createForUser')
        ->once()
        ->andThrow(new RuntimeException('QBO unavailable'));

    $job = new SyncApprovedExpenseToQuickBooksJob(
        $expense->id,
        $employee->id,
        $token->id,
        ['amount' => 10, 'txn_date' => '2026-08-03', 'payment_type' => 'Cash', 'payment_account_ref' => '35', 'expense_account_ref' => '7', 'is_billable' => false],
    );

    expect(fn () => $job->handle($purchases))
        ->toThrow(RuntimeException::class);

    $expense->refresh();

    expect($expense->status)->toBe(ExpenseStatus::Approved)
        ->and($expense->reviewed_by_id)->toBe($admin->id)
        ->and($expense->qbo_id)->toBeNull();
});

it('skips sync when prerequisites are missing or the expense is already linked', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $approved = Expense::factory()->forUser($employee)->create([
        'status' => ExpenseStatus::Approved,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
    ]);
    $linked = Expense::factory()->forUser($employee)->approved('55')->create([
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
    ]);
    $pending = Expense::factory()->forUser($employee)->create();

    $purchases = Mockery::mock(PurchaseService::class);
    $purchases->shouldNotReceive('createForUser');

    $payload = [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Cash',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'is_billable' => false,
    ];

    (new SyncApprovedExpenseToQuickBooksJob(999_999, $employee->id, $token->id, $payload))
        ->handle($purchases);
    (new SyncApprovedExpenseToQuickBooksJob($approved->id, 999_999, $token->id, $payload))
        ->handle($purchases);
    (new SyncApprovedExpenseToQuickBooksJob($approved->id, $employee->id, 999_999, $payload))
        ->handle($purchases);
    (new SyncApprovedExpenseToQuickBooksJob($pending->id, $employee->id, $token->id, $payload))
        ->handle($purchases);
    (new SyncApprovedExpenseToQuickBooksJob($linked->id, $employee->id, $token->id, $payload))
        ->handle($purchases);

    expect($linked->refresh()->qbo_id)->toBe('55');
});

it('logs permanent queue failures without reverting approval', function () {
    Log::spy();

    $job = new SyncApprovedExpenseToQuickBooksJob(12, 34, 56, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Cash',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'is_billable' => false,
    ]);

    $job->failed(new RuntimeException('queue worker died'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'failed permanently')
                && ($context['expense_id'] ?? null) === 12
                && ($context['employee_id'] ?? null) === 34;
        });
});
