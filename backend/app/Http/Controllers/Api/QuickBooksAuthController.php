<?php

/**
 * QuickBooks OAuth connect, callback, status, and disconnect endpoints.
 */

namespace App\Http\Controllers\Api;

use App\Exceptions\QuickBooksOAuthException;
use App\Http\Controllers\Controller;
use App\Services\OrganizationRealmService;
use App\Services\QboListCacheService;
use App\Services\QuickBooksOAuthCallbackService;
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
     * Injects QuickBooks services.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QuickBooksOAuthCallbackService  $oauthCallback  OAuth callback handler.
     * @param  QboListCacheService  $listCache  Cached QuickBooks list endpoints.
     * @param  OrganizationRealmService  $organizationRealm  Tenant realm binding.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QuickBooksOAuthCallbackService $oauthCallback,
        private readonly QboListCacheService $listCache,
        private readonly OrganizationRealmService $organizationRealm,
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
            return $this->oauthCallback->redirectError($adminUrl, 'missing_params');
        }

        try {
            $this->oauthCallback->exchangeFromCallback(
                $request->string('code')->toString(),
                $request->string('realmId')->toString(),
                $request->string('state')->toString(),
                $request->user(),
            );

            return redirect("{$adminUrl}?quickbooks=connected");
        } catch (QuickBooksOAuthException $exception) {
            return $this->oauthCallback->redirectError($adminUrl, $exception->reason ?? 'oauth');
        } catch (\Throwable) {
            return $this->oauthCallback->redirectError($adminUrl, 'connection');
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

        if ($token === null) {
            return response()->json(['connected' => false]);
        }

        return response()->json($token->toConnectionStatus());
    }

    /**
     * Removes stored QuickBooks tokens for the user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();
        $realmId = $user->quickBooksToken?->realm_id;

        $this->quickBooks->disconnect($user);

        if (is_string($realmId) && $realmId !== '') {
            $this->listCache->forgetRealm($realmId);
        }

        $this->organizationRealm->releaseRealmWhenDisconnected($user, $realmId);

        return response()->json(['connected' => false]);
    }
}
