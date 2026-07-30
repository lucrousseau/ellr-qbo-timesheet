<?php

/**
 * User timezone preference for timestamp display.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserTimezoneRequest;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates the timezone stored on the authenticated user profile.
 */
class UserTimezoneController extends Controller
{
    /**
     * Persists the signed-in user timezone preference.
     *
     * @param  UpdateUserTimezoneRequest  $request  Validated timezone payload.
     * @return JsonResponse
     */
    public function update(UpdateUserTimezoneRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return response()->json(['user' => UserApiResponse::resource($user->fresh())]);
    }
}
