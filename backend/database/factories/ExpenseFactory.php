<?php

/**
 * Model factory for generating Expense records in tests.
 */

namespace Database\Factories;

use App\Enums\ExpensePaymentType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 5, 500),
            'txn_date' => now()->toDateString(),
            'payment_type' => ExpensePaymentType::Cash,
            'payment_account_ref' => '35',
            'expense_account_ref' => '7',
            'description' => fake()->optional()->sentence(),
            'is_billable' => false,
        ];
    }

    /**
     * Configures default privileged attributes after the model is created.
     *
     * @return static
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Expense $expense): void {
            $expense->forceFill([
                'status' => $expense->status ?? ExpenseStatus::Pending,
            ]);
        });
    }

    /**
     * Associates the expense with a timesheet user and their organization.
     *
     * @param  User  $user  Employee who logged the expense.
     * @return static
     */
    public function forUser(User $user): static
    {
        return $this->afterMaking(function (Expense $expense) use ($user): void {
            $expense->forceFill([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
            ]);
        });
    }

    /**
     * Marks the expense as approved and optionally linked to QuickBooks.
     *
     * @param  string|null  $qboId  QuickBooks Purchase identifier.
     * @return static
     */
    public function approved(?string $qboId = '200'): static
    {
        return $this->state(fn (): array => [])
            ->afterCreating(function (Expense $expense) use ($qboId): void {
                $expense->forceFill([
                    'status' => ExpenseStatus::Approved,
                    'qbo_id' => $qboId,
                    'reviewed_at' => now(),
                ])->save();
            });
    }

    /**
     * Marks the expense as rejected by a supervisor.
     *
     * @param  string|null  $reason  Optional rejection reason.
     * @return static
     */
    public function rejected(?string $reason = 'Incorrect category'): static
    {
        return $this->state(fn (): array => [])
            ->afterCreating(function (Expense $expense) use ($reason): void {
                $expense->forceFill([
                    'status' => ExpenseStatus::Rejected,
                    'rejection_reason' => $reason,
                    'reviewed_at' => now(),
                ])->save();
            });
    }
}
