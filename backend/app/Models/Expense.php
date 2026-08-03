<?php

/**
 * Local expense awaiting supervisor approval before QuickBooks Purchase sync.
 */

namespace App\Models;

use App\Enums\ExpensePaymentType;
use App\Enums\ExpenseStatus;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for employee expenses stored locally until approved.
 *
 * @property Carbon $txn_date
 * @property ExpensePaymentType $payment_type
 * @property ExpenseStatus $status
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * Mass-assignable attributes for expense records.
     *
     * @var list<string>
     */
    protected $fillable = [
        'amount',
        'txn_date',
        'payment_type',
        'payment_account_ref',
        'expense_account_ref',
        'vendor_ref',
        'customer_ref',
        'project_ref',
        'description',
        'is_billable',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'txn_date' => 'date',
            'payment_type' => ExpensePaymentType::class,
            'is_billable' => 'boolean',
            'status' => ExpenseStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Relationship to the employee who logged the expense.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to the tenant organization that owns the expense.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Relationship to the supervisor or administrator who reviewed the expense.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Indicates whether the expense has been synchronized to QuickBooks.
     *
     * @return bool
     */
    public function isSyncedToQbo(): bool
    {
        return $this->qbo_id !== null && $this->qbo_id !== '';
    }
}
