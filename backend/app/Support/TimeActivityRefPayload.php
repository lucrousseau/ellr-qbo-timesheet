<?php

/**
 * Builds QuickBooks entity reference payloads for time activity writes.
 */

namespace App\Support;

/**
 * Maps validated picker fields to QuickBooks CustomerRef and ItemRef shapes.
 */
final class TimeActivityRefPayload
{
    /**
     * Builds the QuickBooks customer reference payload from validated picker fields.
     *
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return array<string, string>|null
     */
    public static function customerRef(array $validated): ?array
    {
        if (empty($validated['customer_ref']) && empty($validated['project_ref'])) {
            return null;
        }

        $customerRefValue = ! empty($validated['project_ref'])
            ? $validated['project_ref']
            : $validated['customer_ref'];
        $customerNameValue = ! empty($validated['project_ref'])
            ? ($validated['project_name'] ?? null)
            : ($validated['customer_name'] ?? null);
        $customerRef = ['value' => (string) $customerRefValue];

        if (! empty($customerNameValue)) {
            $customerRef['name'] = (string) $customerNameValue;
        }

        return $customerRef;
    }

    /**
     * Builds the QuickBooks item reference payload from validated picker fields.
     *
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return array<string, string>|null
     */
    public static function itemRef(array $validated): ?array
    {
        if (empty($validated['item_ref'])) {
            return null;
        }

        $itemRef = ['value' => (string) $validated['item_ref']];

        if (! empty($validated['item_name'])) {
            $itemRef['name'] = (string) $validated['item_name'];
        }

        return $itemRef;
    }
}
