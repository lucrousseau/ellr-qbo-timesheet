<?php

/**
 * Local time entry awaiting supervisor approval before QuickBooks sync.
 */

namespace App\Models;

use App\Enums\TimeEntryStatus;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for employee time entries stored locally until approved.
 *
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property TimeEntryStatus $status
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 */
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    /**
     * Mass-assignable attributes for time entry records.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_ref',
        'project_ref',
        'item_ref',
        'start_time',
        'end_time',
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
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'is_billable' => 'boolean',
            'status' => TimeEntryStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Relationship to the employee who logged the time entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to the tenant organization that owns the entry.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Relationship to the supervisor or administrator who reviewed the entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Relationship to the QuickBooks sync group that aggregated this entry.
     *
     * @return BelongsTo<TimeEntrySyncGroup, $this>
     */
    public function syncGroup(): BelongsTo
    {
        return $this->belongsTo(TimeEntrySyncGroup::class, 'sync_group_id');
    }

    /**
     * Indicates whether the entry has been synchronized to QuickBooks.
     *
     * @return bool
     */
    public function isSyncedToQbo(): bool
    {
        return $this->qbo_id !== null && $this->qbo_id !== '';
    }
}
