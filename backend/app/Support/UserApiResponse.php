<?php

/**
 * Serializes authenticated users for JSON API responses.
 */

namespace App\Support;

use App\Enums\UserLevelCode;
use App\Models\User;
use LogicException;

/**
 * Builds user payloads exposed to admin and timesheet clients.
 *
 * Exposes {@see User::$is_admin} so the admin app can show administrator-only tabs.
 */
class UserApiResponse
{
    /**
     * Returns the user model with API-visible attributes for JSON encoding.
     *
     * @param  User  $user  Authenticated user instance.
     * @return User
     */
    public static function resource(User $user): User
    {
        $user->loadMissing(['organization', 'userLevel']);
        $organization = $user->organization;

        $resource = new User;
        $resource->forceFill($user->getAttributes());
        $resource->exists = $user->exists;

        $resource->makeVisible(['is_admin', 'is_super_admin']);
        $resource->setAttribute('is_admin', $user->isAdmin());
        $resource->setAttribute('is_super_admin', $user->isSuperAdmin());
        $resource->setAttribute('level', self::resolveLevelCode($user));
        $resource->setAttribute(
            'organization',
            $organization !== null ? OrganizationApiResponse::resource($organization) : null,
        );
        $resource->setAttribute('supervisor_id', $user->supervisor_id); // @pest-mutate-ignore API resource field mapping
        $directReportsCount = (int) ($user->direct_reports_count ?? $user->directReports()->count()); // @pest-mutate-ignore direct report count lookup
        $resource->setAttribute(
            'can_review_time_entries',
            $user->isAdmin() || $directReportsCount > 0, // @pest-mutate-ignore reviewer capability flag
        );
        $resource->setAttribute('timezone', $user->timezone); // @pest-mutate-ignore API resource field mapping
        $resource->setAttribute('effective_display_timezone', UserTimezoneResolver::effectiveDisplayTimezone($user));
        $resource->makeHidden(['organization_id', 'user_level_id']);

        return $resource;
    }

    /**
     * Resolves the stable permission code exposed in API payloads.
     *
     * @param  User  $user  Authenticated user instance.
     * @return string
     */
    private static function resolveLevelCode(User $user): string
    {
        if (! $user->exists) {
            return UserLevelCode::default()->value;
        }

        if ($user->userLevel === null) {
            throw new LogicException('Persisted user is missing a user level relation.');
        }

        return $user->userLevel->code;
    }
}
