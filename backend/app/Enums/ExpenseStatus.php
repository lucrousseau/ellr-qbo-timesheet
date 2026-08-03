<?php

/**
 * Approval lifecycle states for local expenses before QuickBooks Purchase sync.
 */

namespace App\Enums;

/**
 * Backed enum of expense approval statuses.
 */
enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Returns whether the expense may still be edited by the employee.
     *
     * @return bool
     */
    public function isEditable(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Returns whether the expense is eligible for QuickBooks synchronization.
     *
     * @return bool
     */
    public function isSyncable(): bool
    {
        return $this === self::Approved;
    }
}
