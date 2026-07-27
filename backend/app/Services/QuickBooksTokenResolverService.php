<?php

/**
 * Resolves a valid QuickBooks OAuth token for the authenticated user.
 */

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * Loads a valid QuickBooks token for the user or falls back to an administrator token.
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
     * @param  User  $user  Authenticated application user.
     * @return QuickBooksToken
     */
    public function resolve(User $user): QuickBooksToken
    {
        $token = $this->findTokenForUser($user);

        if ($token === null) {
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
     * Returns the user's token or the latest administrator token for the organization.
     *
     * @param  User  $user  Authenticated application user.
     * @return QuickBooksToken|null
     */
    private function findTokenForUser(User $user): ?QuickBooksToken
    {
        $token = $user->quickBooksToken;

        if ($token !== null) {
            return $token;
        }

        return QuickBooksToken::query()
            ->whereRelation('user', 'is_admin', true)
            ->latest('id')
            ->first();
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
