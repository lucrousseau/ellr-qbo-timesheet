<?php

/**
 * Queued handler for inbound QuickBooks webhook payloads.
 */

namespace App\Jobs;

use App\Exceptions\QuickBooksException;
use App\Services\QuickBooksWebhookProcessorService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies webhook entity changes asynchronously.
 */
class ProcessQuickBooksWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds to keep an identical webhook payload deduplicated in the queue.
     */
    public int $uniqueFor = 300;

    /**
     * @param  array<string, mixed>  $payload  Decoded Intuit webhook JSON body.
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    /**
     * Deduplicates identical webhook payloads while a prior job is pending.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return 'quickbooks:webhook:'.hash('sha256', json_encode($this->payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Dispatches entity changes to the webhook processor.
     *
     * @param  QuickBooksWebhookProcessorService  $processor  Webhook entity handler.
     * @return void
     * @throws QuickBooksException When QuickBooks returns an API error during sync.
     */
    public function handle(QuickBooksWebhookProcessorService $processor): void
    {
        try {
            $processor->process($this->payload);
        } catch (QuickBooksException $exception) {
            $context = ['message' => $exception->getMessage()];

            if (config('quickbooks.expose_api_errors') && $exception->responseBody !== null) {
                $context['response_body'] = $exception->responseBody;
            }

            Log::warning('QuickBooks webhook sync failed', $context);

            throw $exception;
        }
    }

    /**
     * Logs permanent webhook job failures after retries are exhausted.
     *
     * @param  Throwable|null  $exception  Failure that stopped the job.
     * @return void
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('QuickBooks webhook job failed permanently', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
