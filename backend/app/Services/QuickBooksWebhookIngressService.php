<?php

/**
 * Validates inbound QuickBooks webhook payloads before they are queued.
 */

namespace App\Services;

use Illuminate\Http\JsonResponse;

/**
 * Enforces size and shape limits on signed Intuit webhook bodies.
 */
class QuickBooksWebhookIngressService
{
    /**
     * Returns whether a raw webhook body exceeds the configured byte limit.
     *
     * @param  string  $rawPayload  Raw HTTP request body.
     * @return bool
     */
    public function exceedsRawByteLimit(string $rawPayload): bool
    {
        $maxBytes = (int) config('quickbooks.webhook_max_payload_bytes', 262_144);

        return strlen($rawPayload) > $maxBytes;
    }

    /**
     * Builds a 413 response for oversized webhook bodies.
     *
     * @return JsonResponse
     */
    public function payloadTooLargeResponse(): JsonResponse
    {
        return response()->json(['message' => 'Webhook payload too large.'], 413);
    }

    /**
     * Decodes a signed webhook body when it passes configured limits.
     *
     * @param  string  $rawPayload  Raw HTTP request body.
     * @return array<string, mixed>|JsonResponse Decoded payload or an error response.
     */
    public function decodeValidatedPayload(string $rawPayload): array|JsonResponse
    {
        if ($this->exceedsRawByteLimit($rawPayload)) {
            return $this->payloadTooLargeResponse();
        }

        $decoded = json_decode($rawPayload, true);

        if (! is_array($decoded)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 422);
        }

        $limitResponse = $this->structuralLimitResponse($decoded);

        if ($limitResponse !== null) {
            return $limitResponse;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $decoded  Decoded webhook JSON body.
     * @return JsonResponse|null
     */
    private function structuralLimitResponse(array $decoded): ?JsonResponse
    {
        $notifications = $decoded['eventNotifications'] ?? null;

        if ($notifications === null) {
            return null;
        }

        if (! is_array($notifications)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 422);
        }

        $maxNotifications = (int) config('quickbooks.webhook_max_notifications', 50);

        if (count($notifications) > $maxNotifications) {
            return response()->json(['message' => 'Webhook payload exceeds notification limit.'], 422);
        }

        $maxEntities = (int) config('quickbooks.webhook_max_entities_per_notification', 100);

        foreach ($notifications as $notification) {
            if (! is_array($notification)) {
                return response()->json(['message' => 'Invalid webhook payload.'], 422);
            }

            $entities = $notification['dataChangeEvent']['entities'] ?? null;

            if ($entities === null) {
                continue;
            }

            if (! is_array($entities)) {
                return response()->json(['message' => 'Invalid webhook payload.'], 422);
            }

            if (count($entities) > $maxEntities) {
                return response()->json(['message' => 'Webhook payload exceeds entity limit.'], 422);
            }
        }

        return null;
    }
}
