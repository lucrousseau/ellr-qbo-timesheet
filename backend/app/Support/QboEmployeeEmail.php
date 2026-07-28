<?php

/**
 * Extracts employee email values from QuickBooks SDK entities.
 */

namespace App\Support;

/**
 * Normalizes QuickBooks employee email shapes returned by Query and FindById.
 */
class QboEmployeeEmail
{
    /**
     * Reads the primary email address from a QuickBooks employee entity.
     *
     * @param  object  $employee  QuickBooks Employee entity.
     * @return string|null
     */
    public static function fromEmployee(object $employee): ?string
    {
        if (! isset($employee->PrimaryEmailAddr)) {
            return null;
        }

        return self::normalize($employee->PrimaryEmailAddr);
    }

    /**
     * Normalizes a QuickBooks primary email value.
     *
     * @param  mixed  $primaryEmailAddr  QuickBooks PrimaryEmailAddr field.
     * @return string|null
     */
    public static function normalize(mixed $primaryEmailAddr): ?string
    {
        if (is_string($primaryEmailAddr)) {
            $email = trim($primaryEmailAddr);

            return $email !== '' ? $email : null;
        }

        if (is_array($primaryEmailAddr)) {
            return self::normalizeAddress($primaryEmailAddr['Address'] ?? null);
        }

        if (is_object($primaryEmailAddr)) {
            return self::normalizeAddress($primaryEmailAddr->Address ?? null);
        }

        return null;
    }

    /**
     * Trims and validates a QuickBooks email address value.
     *
     * @param  mixed  $address  QuickBooks email address value.
     * @return string|null
     */
    private static function normalizeAddress(mixed $address): ?string
    {
        if (! is_string($address)) {
            return null;
        }

        $email = trim($address);

        return $email !== '' ? $email : null;
    }
}
