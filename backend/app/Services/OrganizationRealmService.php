<?php

/**
 * Validates and binds QuickBooks company realms to tenant organizations.
 */

namespace App\Services;

use App\Exceptions\QuickBooksOAuthException;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\User;

/**
 * Ensures each organization connects at most one QuickBooks company realm.
 */
class OrganizationRealmService
{
    /**
     * Validates realm ownership and links the organization on first QuickBooks connect.
     *
     * @param  User  $user  Administrator who completed OAuth.
     * @param  QuickBooksToken  $token  Persisted OAuth token.
     * @return void
     */
    public function validateAndBindRealm(User $user, QuickBooksToken $token): void
    {
        $organization = $user->organization;

        if ($organization === null) {
            throw new QuickBooksOAuthException(
                'Organization is required before connecting QuickBooks.',
                null,
                'organization_missing',
            );
        }

        $realmClaimedByAnotherOrganization = Organization::query()
            ->where('realm_id', $token->realm_id)
            ->whereKeyNot($organization->id)
            ->exists();

        if ($realmClaimedByAnotherOrganization) {
            throw new QuickBooksOAuthException(
                'This QuickBooks company is already connected to another organization.',
                null,
                'realm_claimed',
            );
        }

        if (
            $organization->hasQuickBooksRealm()
            && $organization->realm_id !== $token->realm_id
        ) {
            throw new QuickBooksOAuthException(
                'This organization is already connected to a different QuickBooks company.',
                null,
                'realm_conflict',
            );
        }

        if (! $organization->hasQuickBooksRealm()) {
            $organization->update(['realm_id' => $token->realm_id]);
        }
    }

    /**
     * Clears the organization realm binding when no OAuth tokens remain for that realm.
     *
     * @param  User  $user  Administrator who disconnected QuickBooks.
     * @param  string|null  $realmId  Realm identifier from the disconnected token.
     * @return void
     */
    public function releaseRealmWhenDisconnected(User $user, ?string $realmId): void
    {
        if (! is_string($realmId) || $realmId === '') {
            return;
        }

        $organization = $user->organization;

        if ($organization === null || $organization->realm_id !== $realmId) {
            return;
        }

        $tokensRemain = QuickBooksToken::query()
            ->where('realm_id', $realmId)
            ->whereHas('user', function ($query) use ($organization): void {
                $query->where('organization_id', $organization->id);
            })
            ->exists();

        if (! $tokensRemain) {
            $organization->update(['realm_id' => null]);
        }
    }
}
