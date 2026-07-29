<?php

/**
 * QuickBooks OAuth callback exchange and admin redirect helpers.
 */

namespace App\Services;

use App\Exceptions\QuickBooksOAuthException;
use App\Jobs\ReconcileRealmTimeActivitiesJob;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Completes the Intuit OAuth callback and builds admin redirect URLs.
 */
class QuickBooksOAuthCallbackService
{
    /**
     * Injects the QuickBooks service.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QuickBooksConnectionValidationService  $connectionValidation  Post-OAuth validation.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QuickBooksConnectionValidationService $connectionValidation,
    ) {}

    /**
     * Exchanges the OAuth code for tokens after validating state and session user.
     *
     * @param  string  $code  OAuth authorization code.
     * @param  string  $realmId  Intuit company realm identifier.
     * @param  string  $state  OAuth CSRF state.
     * @param  User|null  $sessionUser  Optional signed-in user during callback.
     * @return void
     */
    public function exchangeFromCallback(string $code, string $realmId, string $state, ?User $sessionUser): void
    {
        $statePayload = $this->quickBooks->consumeAuthorizationState($state);
        $user = User::query()->findOrFail($statePayload['user_id']);

        if ($sessionUser !== null && $sessionUser->id !== $user->id) {
            throw new QuickBooksOAuthException('OAuth session mismatch.');
        }

        $token = $this->quickBooks->exchangeCode($code, $realmId, $user);
        $this->connectionValidation->validateAdministratorConnection($user, $token);

        if (config('quickbooks.time_activities_reconcile_enabled', true)) {
            ReconcileRealmTimeActivitiesJob::dispatch($token->id);
        }
    }

    /**
     * Redirects to the admin app with an OAuth error reason.
     *
     * @param  string  $adminUrl  Admin frontend base URL.
     * @param  string  $reason  OAuth error reason code.
     * @return RedirectResponse
     */
    public function redirectError(string $adminUrl, string $reason): RedirectResponse
    {
        return redirect("{$adminUrl}?quickbooks=error&reason=".urlencode($reason));
    }
}
