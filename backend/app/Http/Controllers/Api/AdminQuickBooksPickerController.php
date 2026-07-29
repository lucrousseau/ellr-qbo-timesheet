<?php

/**
 * Administrator QuickBooks picker endpoints.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QboCustomerListService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns QuickBooks reference lists for administrator configuration screens.
 */
class AdminQuickBooksPickerController extends Controller
{
    /**
     * Injects QuickBooks token resolution and customer list services.
     *
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  QboCustomerListService  $customers  Customer list queries.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly QboCustomerListService $customers,
    ) {}

    /**
     * Lists all active QuickBooks customers for administrator assignment pickers.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function customers(Request $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());
        $customers = $this->customers->listAllActive($token, $request->boolean('refresh'));

        return response()->json(['data' => $customers]);
    }
}
