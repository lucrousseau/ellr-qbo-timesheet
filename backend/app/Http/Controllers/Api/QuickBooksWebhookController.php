<?php

/**
 * Public endpoint for Intuit QuickBooks webhook notifications.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessQuickBooksWebhookJob;
use App\Services\QuickBooksWebhookIngressService;
use App\Services\QuickBooksWebhookVerifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Accepts and queues QuickBooks change notifications.
 */
class QuickBooksWebhookController extends Controller
{
    /**
     * @param  QuickBooksWebhookVerifierService  $verifier  Webhook signature verifier.
     * @param  QuickBooksWebhookIngressService  $ingress  Payload size and shape validation.
     */
    public function __construct(
        private readonly QuickBooksWebhookVerifierService $verifier,
        private readonly QuickBooksWebhookIngressService $ingress,
    ) {}

    /**
     * Validates the webhook signature and queues entity processing.
     *
     * @param  Request  $request  Incoming webhook request.
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if ($this->ingress->exceedsRawByteLimit($payload)) {
            return $this->ingress->payloadTooLargeResponse();
        }

        $signature = $request->header('intuit-signature');

        if (! $this->verifier->isValid($payload, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $decoded = $this->ingress->decodeValidatedPayload($payload);

        if ($decoded instanceof JsonResponse) {
            return $decoded;
        }

        ProcessQuickBooksWebhookJob::dispatch($decoded);

        return response()->json(['status' => 'accepted']);
    }
}
