<?php

/**
 * Helpers for splitting and joining person first and last names.
 */

namespace App\Support;

/**
 * Normalizes full names into first/last parts for persistence and display.
 */
final class PersonName
{
    /**
     * Splits a full name into first and last name parts.
     *
     * @param  string  $fullName  Display name to split.
     * @return array{0: string, 1: string}
     */
    public static function split(string $fullName): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');

        if ($normalized === '') {
            return ['', ''];
        }

        $parts = explode(' ', $normalized, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Joins first and last name parts into a single display name.
     *
     * @param  string  $firstName  Given name.
     * @param  string  $lastName  Family name.
     * @return string
     */
    public static function join(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }

    /**
     * Builds first/last attributes from a QuickBooks employee payload.
     *
     * Prefers GivenName/FamilyName when present; otherwise splits DisplayName.
     *
     * @param  object  $employee  QuickBooks Employee entity.
     * @return array{first_name: string, last_name: string, display_name: string}
     */
    public static function fromQuickBooksEmployee(object $employee): array
    {
        $firstName = trim((string) ($employee->GivenName ?? ''));
        $lastName = trim((string) ($employee->FamilyName ?? ''));
        $displayName = trim((string) ($employee->DisplayName ?? ''));

        if ($firstName === '' && $lastName === '') {
            [$firstName, $lastName] = self::split($displayName);
        }

        $joined = self::join($firstName, $lastName);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $joined !== '' ? $joined : $displayName,
        ];
    }
}
