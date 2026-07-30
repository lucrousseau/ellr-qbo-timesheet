<?php

/**
 * Business rule violations for platform organization lifecycle operations.
 */

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a super-administrator organization action is rejected.
 */
class OrganizationLifecycleException extends RuntimeException
{
    /**
     * @param  string  $message  Human-readable rejection reason.
     * @param  string  $reason  Stable API error code.
     */
    public function __construct(
        string $message,
        public readonly string $reason,
    ) {
        parent::__construct($message);
    }
}
