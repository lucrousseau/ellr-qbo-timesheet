<?php

namespace App\Http\Concerns;

use App\Enums\ApiErrorCode;
use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Services\QuickBooksService;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * Resolves and refreshes the authenticated user's QuickBooks token.
 */
trait ResolvesQuickBooksToken
{
    /**
     * Provides the QuickBooks service to the trait (implemented by the host controller).
     *
     * @return QuickBooksService
     */
    abstract protected function quickBooksService(): QuickBooksService;

    /**
     * Returns a valid QuickBooks token or aborts with a JSON 403/503 response.
     *
     * @return QuickBooksToken
     */
    protected function resolveQuickBooksToken(): QuickBooksToken
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
                $token = $this->quickBooksService()->refreshToken($token);
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
    protected function denyQuickBooks(ApiErrorCode $code, string $message): never
    {
        abort(response()->json([
            'message' => $message,
            'error' => $code->value,
        ], 403));
    }
}
