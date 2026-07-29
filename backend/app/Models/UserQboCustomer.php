<?php

/**
 * Administrator-assigned QuickBooks customer for a timesheet user.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a timesheet user to one allowed QuickBooks customer.
 */
class UserQboCustomer extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'qbo_customer_ref',
        'qbo_customer_name',
    ];

    /**
     * Returns the timesheet user that owns this customer assignment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
