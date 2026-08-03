<?php

/**
 * Resolves QuickBooks picker labels for local time entry API responses.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntryApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

/**
 * Presents local time entries with read-time QuickBooks display labels.
 */
class TimeEntryPresentationService
{
    /**
     * Injects display name resolution and organization token lookup.
     *
     * @param  QboPickerDisplayNameService  $displayNames  Cached QuickBooks label resolver.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves organization QBO token.
     */
    public function __construct(
        private readonly QboPickerDisplayNameService $displayNames,
        private readonly QuickBooksTokenResolverService $tokenResolver,
    ) {}

    /**
     * Maps one time entry to an API payload with resolved picker labels.
     *
     * @param  TimeEntry  $entry  Local time entry row.
     * @param  User|null  $viewer  Authenticated user used to resolve organization token.
     * @param  QuickBooksToken|null  $token  Pre-resolved token when already available.
     * @return array<string, mixed>
     */
    public function resource(TimeEntry $entry, ?User $viewer = null, ?QuickBooksToken $token = null): array
    {
        $entry->loadMissing(['user', 'reviewedBy']); // @pest-mutate-ignore API resource relation preload

        return TimeEntryApiResponse::resource($entry, $this->resolveLabels($entry, $viewer, $token));
    }

    /**
     * Maps a collection of time entries to API payloads.
     *
     * @param  Collection<int, TimeEntry>|iterable<int, TimeEntry>  $entries  Entry rows.
     * @param  User  $viewer  Authenticated user used to resolve organization token.
     * @param  QuickBooksToken|null  $token  Pre-resolved token when already available.
     * @return list<array<string, mixed>>
     */
    public function collection(iterable $entries, User $viewer, ?QuickBooksToken $token = null): array
    {
        $resolvedToken = $token ?? $this->tryResolveToken($viewer);
        $rows = [];

        foreach ($entries as $entry) {
            $entry->loadMissing(['user', 'reviewedBy']); // @pest-mutate-ignore API resource relation preload
            $owner = $entry->user;
            $labels = $resolvedToken !== null && $owner !== null
                ? $this->displayNames->entryDisplayNames($resolvedToken, $owner, $entry)
                : null;
            $rows[] = TimeEntryApiResponse::resource($entry, $labels);
        }

        return $rows;
    }

    /**
     * Resolves picker labels for one entry when a token is available.
     *
     * @param  TimeEntry  $entry  Local time entry row.
     * @param  User|null  $viewer  Authenticated user used to resolve organization token.
     * @param  QuickBooksToken|null  $token  Pre-resolved token when already available.
     * @return array{customer_name: string|null, project_name: string|null, item_name: string|null}|null
     */
    private function resolveLabels(TimeEntry $entry, ?User $viewer, ?QuickBooksToken $token = null): ?array
    {
        $owner = $entry->user;

        if ($owner === null) {
            return null;
        }

        $resolvedToken = $token ?? $this->tryResolveToken($viewer ?? $owner);

        return $resolvedToken !== null
            ? $this->displayNames->entryDisplayNames($resolvedToken, $owner, $entry)
            : null;
    }

    /**
     * Resolves a QuickBooks token without aborting when disconnected.
     *
     * @param  User  $user  Authenticated application user.
     * @return QuickBooksToken|null
     */
    private function tryResolveToken(User $user): ?QuickBooksToken
    {
        try {
            return $this->tokenResolver->resolve($user);
        } catch (HttpResponseException) {
            return null;
        }
    }
}
