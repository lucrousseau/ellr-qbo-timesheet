<?php

/**
 * Maps active timer sessions to QuickBooks time activity payloads.
 */

namespace App\Support;

use App\Models\ActiveTimeSession;
use Carbon\CarbonImmutable;

/**
 * Builds validated time activity payloads from persisted timer sessions.
 */
final class TimeTrackerLogPayload
{
    /**
     * Maps a timer session and elapsed seconds to a time activity create payload.
     *
     * @param  ActiveTimeSession  $session  Active timer session.
     * @param  int  $elapsedSeconds  Elapsed seconds to log.
     * @return array<string, mixed>
     */
    public static function forSession(ActiveTimeSession $session, int $elapsedSeconds): array
    {
        $endTime = CarbonImmutable::now();
        $startTime = $endTime->subSeconds($elapsedSeconds);

        $payload = [
            'start_time' => $startTime->toIso8601String(),
            'end_time' => $endTime->toIso8601String(),
            'is_billable' => $session->is_billable,
        ];

        if ($session->description !== null && $session->description !== '') {
            $payload['description'] = $session->description;
        }

        $ticket = TicketAttributes::fromValidated([
            'ticket_key' => $session->ticket_key,
            'ticket_source' => $session->ticket_source,
            'ticket_url' => $session->ticket_url,
            'ticket_title' => $session->ticket_title,
        ]);

        if ($ticket['ticket_key'] !== null) {
            $payload = [...$payload, ...$ticket];
        }

        if ($session->customer_ref) {
            $payload['customer_ref'] = $session->customer_ref;
        }

        if ($session->project_ref) {
            $payload['project_ref'] = $session->project_ref;
        }

        if ($session->service_ref) {
            $payload['item_ref'] = $session->service_ref;
        }

        return $payload;
    }
}
