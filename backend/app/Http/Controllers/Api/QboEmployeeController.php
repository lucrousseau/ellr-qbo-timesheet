<?php

/**
 * QuickBooks employee mapping for authenticated user accounts.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateQboEmployeeRequest;
use App\Services\QboEmployeeService;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates the QBO employee reference stored on the user profile.
 */
class QboEmployeeController extends Controller
{
    /**
     * Injects the QBO employee mapping service.
     *
     * @param  QboEmployeeService  $qboEmployee  Employee mapping service.
     */
    public function __construct(
        private readonly QboEmployeeService $qboEmployee,
    ) {}

    /**
     * Associates a QuickBooks employee with the authenticated administrator.
     *
     * @param  UpdateQboEmployeeRequest  $request  Validated employee mapping payload.
     * @return JsonResponse
     */
    public function update(UpdateQboEmployeeRequest $request): JsonResponse
    {
        $user = $request->user();
        $updated = $this->qboEmployee->updateMapping($user, $user, $request->validated());

        return response()->json(['user' => UserApiResponse::resource($updated)]);
    }
}
