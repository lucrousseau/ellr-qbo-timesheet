<?php

namespace App\Http\Concerns;

use App\Enums\ApiErrorCode;
use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Services\QuickBooksService;
use Illuminate\Contracts\Cache\LockTimeoutException;

trait ResolvesQuickBooksToken
{
    abstract protected function quickBooksService(): QuickBooksService;

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

    protected function denyQuickBooks(ApiErrorCode $code, string $message): never
    {
        abort(response()->json([
            'message' => $message,
            'error' => $code->value,
        ], 403));
    }
}
