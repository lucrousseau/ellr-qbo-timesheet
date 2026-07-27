<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QuickBooksOAuthException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuickBooksAuthController extends Controller
{
    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    public function connect(Request $request): JsonResponse
    {
        $state = $this->quickBooks->createAuthorizationState($request->user());

        return response()->json([
            'authorization_url' => $this->quickBooks->authorizationUrl($state),
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $adminUrl = config('quickbooks.frontend_admin_url');

        if (! $request->filled(['code', 'realmId', 'state'])) {
            return $this->redirectOAuthError($adminUrl, 'missing_params');
        }

        try {
            $statePayload = $this->quickBooks->consumeAuthorizationState(
                $request->string('state')->toString(),
            );

            $user = User::query()->findOrFail($statePayload['user_id']);

            if ($request->user() !== null && $request->user()->id !== $user->id) {
                throw new QuickBooksOAuthException('OAuth session mismatch.');
            }

            $this->quickBooks->exchangeCode(
                $request->string('code')->toString(),
                $request->string('realmId')->toString(),
                $user,
            );

            return redirect("{$adminUrl}?quickbooks=connected");
        } catch (QuickBooksOAuthException) {
            return $this->redirectOAuthError($adminUrl, 'oauth');
        } catch (\Throwable) {
            return $this->redirectOAuthError($adminUrl, 'connection');
        }
    }

    public function status(Request $request): JsonResponse
    {
        $token = $request->user()->quickBooksToken;

        return response()->json([
            'connected' => $token !== null,
            'realm_id' => $token?->realm_id,
            'access_token_expires_at' => $token?->access_token_expires_at,
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $this->quickBooks->disconnect($request->user());

        return response()->json(['connected' => false]);
    }

    private function redirectOAuthError(string $adminUrl, string $reason): RedirectResponse
    {
        return redirect("{$adminUrl}?quickbooks=error&reason=".urlencode($reason));
    }
}
