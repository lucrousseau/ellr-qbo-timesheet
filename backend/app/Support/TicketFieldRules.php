<?php

/**
 * Shared validation rules for structured ticket metadata on time capture.
 */

namespace App\Support;

use App\Enums\TicketSource;
use Illuminate\Validation\Rule;

/**
 * Builds reusable Form Request rules for ticket key, source, URL, and title.
 */
final class TicketFieldRules
{
    /**
     * Returns ticket field rules for create or full upsert payloads.
     *
     * @return array<string, list<mixed>>
     */
    public static function forCreate(): array
    {
        return self::rules(sometimes: false);
    }

    /**
     * Returns ticket field rules for partial update payloads.
     *
     * @return array<string, list<mixed>>
     */
    public static function forUpdate(): array
    {
        return self::rules(sometimes: true);
    }

    /**
     * Builds ticket validation rules.
     *
     * @param  bool  $sometimes  Whether fields are optional partial-update keys.
     * @return array<string, list<mixed>>
     */
    private static function rules(bool $sometimes): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];

        return [
            'ticket_key' => [...$prefix, 'nullable', 'string', 'max:64'],
            'ticket_source' => [
                ...$prefix,
                'nullable',
                'string',
                Rule::in([
                    TicketSource::Manual->value,
                    TicketSource::Jira->value,
                    TicketSource::Linear->value,
                ]),
            ],
            'ticket_url' => [...$prefix, 'nullable', 'url', 'max:2048'],
            'ticket_title' => [...$prefix, 'nullable', 'string', 'max:512'],
        ];
    }
}
