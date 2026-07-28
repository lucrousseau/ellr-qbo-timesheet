<?php

/**
 * QuickBooks reference data for timesheet pickers (customers, projects, services).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QboCustomerListService;
use App\Services\QboProjectListService;
use App\Services\QboServiceListService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns cached QuickBooks lists for authenticated timesheet users.
 */
class QuickBooksPickerController extends Controller
{
    /**
     * Injects QuickBooks token resolution and list services.
     *
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  QboCustomerListService  $customers  Customer list queries.
     * @param  QboProjectListService  $projects  Project list queries.
     * @param  QboServiceListService  $services  Service item list queries.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly QboCustomerListService $customers,
        private readonly QboProjectListService $projects,
        private readonly QboServiceListService $services,
    ) {}

    /**
     * Lists customers linked to the signed-in user's QuickBooks employee.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function customers(Request $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());
        $customers = $this->customers->listForUser($request->user(), $token, $request->boolean('refresh'));

        return response()->json(['data' => $customers]);
    }

    /**
     * Lists active projects for a parent customer.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function projects(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_ref' => ['required', 'string', 'max:255'],
        ]);

        $token = $this->tokenResolver->resolve($request->user());
        $projects = $this->projects->listForCustomer(
            $token,
            $validated['customer_ref'],
            $request->boolean('refresh'),
        );

        return response()->json(['data' => $projects]);
    }

    /**
     * Lists active service items from the connected QuickBooks company.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function services(Request $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());
        $services = $this->services->listActive($token, $request->boolean('refresh'));

        return response()->json(['data' => $services]);
    }
}
