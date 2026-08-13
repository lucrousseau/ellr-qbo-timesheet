<?php

/**
 * Super-administrator endpoints for cross-tenant organization management.
 */

namespace App\Http\Controllers\Api;

use App\Exceptions\OrganizationLifecycleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuperAdminOrganizationRequest;
use App\Http\Requests\UpdateSuperAdminOrganizationRequest;
use App\Models\Organization;
use App\Services\OrganizationLifecycleService;
use App\Support\SuperAdminOrganizationApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lists, creates, updates, and deletes tenant organizations for platform operators.
 */
class SuperAdminOrganizationController extends Controller
{
    /**
     * Injects the organization lifecycle service.
     *
     * @param  OrganizationLifecycleService  $lifecycle  Platform organization management.
     */
    public function __construct(
        private readonly OrganizationLifecycleService $lifecycle,
    ) {}

    /**
     * Lists all tenant organizations with aggregate user counts.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $organizations = $this->lifecycle->listOrganizations()
            ->map(fn (Organization $organization) => SuperAdminOrganizationApiResponse::resource($organization))
            ->values();

        return response()->json(['data' => $organizations]);
    }

    /**
     * Creates a tenant organization and its founding administrator account.
     *
     * @param  StoreSuperAdminOrganizationRequest  $request  Validated provisioning payload.
     * @return JsonResponse
     */
    public function store(StoreSuperAdminOrganizationRequest $request): JsonResponse
    {
        $organization = $this->lifecycle->createOrganization($request->validated());

        return response()->json([
            'data' => SuperAdminOrganizationApiResponse::resource(
                $organization->loadCount('users')->load('administrator'),
            ),
        ], 201);
    }

    /**
     * Updates mutable organization attributes.
     *
     * @param  UpdateSuperAdminOrganizationRequest  $request  Validated update payload.
     * @param  Organization  $organization  Tenant organization to update.
     * @return JsonResponse
     */
    public function update(UpdateSuperAdminOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $organization = $this->lifecycle->updateOrganization($organization, $request->validated());

        return response()->json([
            'data' => SuperAdminOrganizationApiResponse::resource(
                $organization->loadCount('users')->load('administrator'),
            ),
        ]);
    }

    /**
     * Deletes a tenant organization and purges linked QuickBooks realm data.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Organization  $organization  Tenant organization to delete.
     * @return Response
     */
    public function destroy(Request $request, Organization $organization): Response
    {
        try {
            $this->lifecycle->deleteOrganization($request->user(), $organization);
        } catch (OrganizationLifecycleException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->reason,
            ], 409));
        }

        return response()->noContent();
    }
}
