<?php

/**
 * User locale preference for multilingual UI copy.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserLocaleRequest;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates the locale stored on the authenticated user profile.
 */
class UserLocaleController extends Controller
{
    /**
     * Persists the signed-in user locale preference.
     *
     * @param  UpdateUserLocaleRequest  $request  Validated locale payload.
     * @return JsonResponse
     */
    public function update(UpdateUserLocaleRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json(['user' => UserApiResponse::resource($user->fresh())]);
    }
}
