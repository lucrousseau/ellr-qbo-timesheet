<?php

/**
 * REST endpoints for external ticket integrations used by the timer.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchTicketsRequest;
use App\Services\TicketIntegrationService;
use Illuminate\Http\JsonResponse;

/**
 * Exposes ticket integration status and issue search for time capture.
 */
class TicketIntegrationController extends Controller
{
    /**
     * @param  TicketIntegrationService  $tickets  Ticket provider status and search.
     */
    public function __construct(
        private readonly TicketIntegrationService $tickets,
    ) {}

    /**
     * Returns whether Jira/Linear pickers are available for the organization.
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $status = $this->tickets->status();

        return response()->json([
            'data' => [
                ...$status,
                'picker_available' => $this->tickets->pickerAvailable(),
            ],
        ]);
    }

    /**
     * Searches connected ticket providers for the timer picker.
     *
     * @param  SearchTicketsRequest  $request  Validated search query.
     * @return JsonResponse
     */
    public function search(SearchTicketsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'data' => $this->tickets->search(
                $validated['q'],
                $validated['provider'] ?? null,
            ),
        ]);
    }
}
