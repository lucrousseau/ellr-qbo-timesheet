<?php

/**
 * QuickBooks employee mapping for authenticated user accounts.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateQboEmployeeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Updates the QBO employee reference stored on the user profile.
 */
class QboEmployeeController extends Controller
{
    /**
     * Associates a QuickBooks employee with the authenticated user.
     *
     * @param  UpdateQboEmployeeRequest  $request  Validated employee mapping payload.
     * @return JsonResponse
     */
    public function update(UpdateQboEmployeeRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json(['user' => $user->fresh()]);
    }
}
