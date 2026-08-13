<?php

/**
 * Local grouping of approved time entries pushed as one QuickBooks TimeActivity.
 */

namespace App\Models;

use Database\Factories\TimeEntrySyncGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for aggregated QuickBooks sync groups.
 *
 * @property Carbon $txn_date
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 */
class TimeEntrySyncGroup extends Model
{
    /** @use HasFactory<TimeEntrySyncGroupFactory> */
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return TimeEntrySyncGroupFactory
     */
    protected static function newFactory(): TimeEntrySyncGroupFactory
    {
        return TimeEntrySyncGroupFactory::new();
    }

    /**
     * Mass-assignable attributes for sync group records.
     *
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'organization_id',
        'user_id',
        'txn_date',
        'customer_ref',
        'project_ref',
        'item_ref',
        'is_billable',
        'group_key',
        'member_count',
        'total_duration_seconds',
        'qbo_id',
        'qbo_description',
        'synced_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'is_billable' => 'boolean',
            'member_count' => 'integer',
            'total_duration_seconds' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Relationship to the employee whose time was grouped.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to the tenant organization that owns the group.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Local time entries that were aggregated into this QuickBooks activity.
     *
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'sync_group_id');
    }
}
