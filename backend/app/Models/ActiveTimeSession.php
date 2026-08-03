<?php

/**
 * Persisted timer state for an authenticated timesheet user.
 */

namespace App\Models;

use Database\Factories\ActiveTimeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'customer_ref',
    'project_ref',
    'service_ref',
    'description',
    'ticket_key',
    'ticket_source',
    'ticket_url',
    'ticket_title',
    'is_billable',
    'accumulated_seconds',
    'running_since',
])]
/**
 * Stores running or paused timer metadata for cross-session persistence.
 *
 * @property int $accumulated_seconds
 * @property bool $is_billable
 * @property Carbon|null $running_since
 */
class ActiveTimeSession extends Model
{
    /** @use HasFactory<ActiveTimeSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accumulated_seconds' => 'integer',
            'is_billable' => 'boolean',
            'running_since' => 'datetime',
        ];
    }

    /**
     * Relationship to the owning application user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Indicates whether the timer is currently running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running_since !== null;
    }

    /**
     * Indicates whether the session stores any QuickBooks picker reference.
     *
     * @return bool
     */
    public function hasPickerSelections(): bool
    {
        foreach (['customer_ref', 'project_ref', 'service_ref'] as $field) {
            $value = $this->{$field};

            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
