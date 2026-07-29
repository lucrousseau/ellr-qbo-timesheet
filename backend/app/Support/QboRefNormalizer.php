<?php

/**
 * Normalizes QuickBooks entity identifiers for stable comparisons.
 */

namespace App\Support;

/**
 * Shared QuickBooks reference normalization for validation and assignment flows.
 */
class QboRefNormalizer
{
    /**
     * Normalizes a QuickBooks identifier to its numeric string form.
     *
     * @param  string|null  $ref  Raw QuickBooks identifier.
     * @return string|null
     */
    public static function normalize(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $ref) ?? '';

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Returns whether two QuickBooks identifiers refer to the same entity.
     *
     * @param  string|null  $left  First identifier.
     * @param  string|null  $right  Second identifier.
     * @return bool
     */
    public static function refsMatch(?string $left, ?string $right): bool
    {
        return self::normalize($left) === self::normalize($right);
    }

    /**
     * Returns whether a picker option list contains the given QuickBooks identifier.
     *
     * @param  array<int, array{id: string, display_name: string}>  $options  Picker rows.
     * @param  string|null  $ref  QuickBooks entity identifier.
     * @return bool
     */
    public static function optionExists(array $options, ?string $ref): bool
    {
        $normalized = self::normalize($ref);

        if ($normalized === null) {
            return false;
        }

        foreach ($options as $option) {
            if (self::normalize((string) $option['id']) === $normalized) {
                return true;
            }
        }

        return false;
    }
}
