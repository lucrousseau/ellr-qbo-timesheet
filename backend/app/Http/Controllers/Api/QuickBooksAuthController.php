<?php

/**
 * QuickBooks OAuth connect, callback, status, and disconnect endpoints.
 */

namespace App\Http\Controllers\Api;

use App\Exceptions\QuickBooksOAuthException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Builds OAuth URLs, handles Intuit callbacks, and reports connection state.
 */
class QuickBooksAuthController extends Controller
{
    /**
     * Injects the QuickBooks service.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    /**
     * Starts the OAuth flow and returns the Intuit authorization URL.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function connect(Request $request): JsonResponse
    {
        $state = $this->quickBooks->createAuthorizationState($request->user());

        return response()->json([
            'authorization_url' => $this->quickBooks->authorizationUrl($state),
        ]);
    }

    /**
     * Intuit OAuth callback: exchanges the code and redirects to the admin frontend.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return RedirectResponse
     */
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

    /**
     * Returns the QuickBooks connection status for the authenticated user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $token = $request->user()->quickBooksToken;

        return response()->json([
            'connected' => $token !== null,
            'realm_id' => $token?->realm_id,
            'access_token_expires_at' => $token?->access_token_expires_at,
        ]);
    }

    /**
     * Removes stored QuickBooks tokens for the user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function disconnect(Request $request): JsonResponse
    {
        $this->quickBooks->disconnect($request->user());

        return response()->json(['connected' => false]);
    }

    /**
     * Redirects to admin with an OAuth error code in the query string.
     *
     * @param  string  $adminUrl  Admin frontend base URL.
     * @param  string  $reason  OAuth error reason code.
     * @return RedirectResponse
     */
    private function redirectOAuthError(string $adminUrl, string $reason): RedirectResponse
    {
        return redirect("{$adminUrl}?quickbooks=error&reason=".urlencode($reason));
    }
}
