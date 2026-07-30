<?php

/**
 * Combined user locale and timezone preference updates.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserPreferencesRequest;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Persists locale and timezone preferences in a single atomic request.
 */
class UserPreferencesController extends Controller
{
    /**
     * Updates the signed-in user locale and timezone preferences together.
     *
     * @param  UpdateUserPreferencesRequest  $request  Validated preferences payload.
     * @return JsonResponse
     */
    public function update(UpdateUserPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json(['user' => UserApiResponse::resource($user->fresh())]);
    }
}
