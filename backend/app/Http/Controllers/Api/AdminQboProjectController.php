<?php

/**
 * Administrator endpoints for creating QuickBooks projects.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminQboProjectRequest;
use App\Services\QboProjectService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Http\JsonResponse;

/**
 * Creates QuickBooks project (job customer) records for the connected company.
 */
class AdminQboProjectController extends Controller
{
    /**
     * Injects QuickBooks token resolution and project write services.
     *
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  QboProjectService  $projects  Project create service.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly QboProjectService $projects,
    ) {}

    /**
     * Creates a QuickBooks project under the selected parent customer.
     *
     * @param  StoreAdminQboProjectRequest  $request  Validated create payload.
     * @return JsonResponse
     */
    public function store(StoreAdminQboProjectRequest $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());
        $project = $this->projects->createForCustomer($token, $request->validated());

        return response()->json(['data' => $project], 201);
    }
}
