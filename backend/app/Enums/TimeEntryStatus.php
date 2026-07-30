<?php

/**
 * Approval lifecycle states for local time entries before QuickBooks sync.
 */

namespace App\Enums;

/**
 * Backed enum of time entry approval statuses.
 */
enum TimeEntryStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Returns whether the entry may still be edited by the employee.
     *
     * @return bool
     */
    public function isEditable(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Returns whether the entry is eligible for QuickBooks synchronization.
     *
     * @return bool
     */
    public function isSyncable(): bool
    {
        return $this === self::Approved;
    }
}
