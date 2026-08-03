<?php

/**
 * Normalizes ticket metadata from validated time capture payloads.
 */

namespace App\Support;

use App\Enums\TicketSource;

/**
 * Maps request ticket fields into persisted attribute values.
 */
final class TicketAttributes
{
    /**
     * Ticket columns persisted on timer sessions and local time entries.
     *
     * @var list<string>
     */
    public const FIELDS = [
        'ticket_key',
        'ticket_source',
        'ticket_url',
        'ticket_title',
    ];

    /**
     * Builds ticket attributes for create or full upsert payloads.
     *
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return array{
     *     ticket_key: string|null,
     *     ticket_source: string|null,
     *     ticket_url: string|null,
     *     ticket_title: string|null
     * }
     */
    public static function fromValidated(array $validated): array
    {
        $key = self::normalizeKey($validated['ticket_key'] ?? null);

        if ($key === null) {
            return [
                'ticket_key' => null,
                'ticket_source' => null,
                'ticket_url' => null,
                'ticket_title' => null,
            ];
        }

        return [
            'ticket_key' => $key,
            'ticket_source' => self::normalizeSource($validated['ticket_source'] ?? null) ?? TicketSource::Manual->value,
            'ticket_url' => self::normalizeOptionalString($validated['ticket_url'] ?? null),
            'ticket_title' => self::normalizeOptionalString($validated['ticket_title'] ?? null),
        ];
    }

    /**
     * Builds ticket attributes for partial updates (only present keys).
     *
     * @param  array<string, mixed>  $validated  Validated update payload.
     * @return array<string, string|null>
     */
    public static function fromPartialValidated(array $validated): array
    {
        $hasTicketField = false;

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $hasTicketField = true;
                break;
            }
        }

        if (! $hasTicketField) {
            return [];
        }

        if (array_key_exists('ticket_key', $validated)) {
            return self::fromValidated($validated);
        }

        $attributes = [];

        if (array_key_exists('ticket_source', $validated)) {
            $attributes['ticket_source'] = self::normalizeSource($validated['ticket_source']);
        }

        if (array_key_exists('ticket_url', $validated)) {
            $attributes['ticket_url'] = self::normalizeOptionalString($validated['ticket_url']);
        }

        if (array_key_exists('ticket_title', $validated)) {
            $attributes['ticket_title'] = self::normalizeOptionalString($validated['ticket_title']);
        }

        return $attributes;
    }

    /**
     * Trims and nullifies empty ticket keys.
     *
     * @param  mixed  $value  Raw ticket key.
     * @return string|null
     */
    private static function normalizeKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Validates and returns a ticket source value.
     *
     * @param  mixed  $value  Raw ticket source.
     * @return string|null
     */
    private static function normalizeSource(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return TicketSource::tryFrom($value)?->value;
    }

    /**
     * Trims and nullifies optional string ticket fields.
     *
     * @param  mixed  $value  Raw optional string.
     * @return string|null
     */
    private static function normalizeOptionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
