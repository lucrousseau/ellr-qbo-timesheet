<?php

/**
 * Resolves a valid QuickBooks OAuth token for the authenticated user.
 */

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * Loads the signed-in user's token and refreshes it when expired.
 */
class QuickBooksTokenResolverService
{
    /**
     * Injects the QuickBooks service for token refresh.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    /**
     * Returns a valid QuickBooks token or aborts with a JSON 403/503 response.
     *
     * @return QuickBooksToken
     */
    public function resolve(): QuickBooksToken
    {
        $user = auth()->user();

        if ($user === null) {
            abort(401);
        }

        $token = $user->quickBooksToken;

        if (! $token) {
            $this->denyQuickBooks(
                ApiErrorCode::QuickBooksNotConnected,
                'QuickBooks is not connected.',
            );
        }

        try {
            if ($token->isAccessTokenExpired()) {
                $token = $this->quickBooks->refreshToken($token);
            }
        } catch (LockTimeoutException) {
            abort(response()->json([
                'message' => 'QuickBooks is busy. Please retry.',
                'error' => ApiErrorCode::QuickBooksBusy->value,
            ], 503));
        } catch (QuickBooksException) {
            $this->denyQuickBooks(
                ApiErrorCode::QuickBooksExpired,
                'QuickBooks connection expired. Please reconnect from the admin app.',
            );
        }

        return $token;
    }

    /**
     * Responds with JSON 403 and a QuickBooks business error code.
     *
     * @param  ApiErrorCode  $code  API error code.
     * @param  string  $message  User-facing error message.
     * @return never
     */
    private function denyQuickBooks(ApiErrorCode $code, string $message): never
    {
        abort(response()->json([
            'message' => $message,
            'error' => $code->value,
        ], 403));
    }
}
