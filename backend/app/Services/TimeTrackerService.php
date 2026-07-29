<?php

/**
 * Persists and logs active timer sessions for timesheet users.
 */

namespace App\Services;

use App\Models\ActiveTimeSession;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\TimerElapsed;
use App\Support\TimeTrackerLogPayload;
use Illuminate\Support\Facades\DB;

/**
 * Manages server-side timer state and QuickBooks time activity creation from a session.
 */
class TimeTrackerService
{
    /**
     * Injects time activity creation and picker validation for logging completed sessions.
     *
     * @param  TimeActivityService  $timeActivities  QuickBooks time activity service.
     * @param  QboPickerValidationService  $pickerValidation  QuickBooks picker reference validator.
     */
    public function __construct(
        private readonly TimeActivityService $timeActivities,
        private readonly QboPickerValidationService $pickerValidation,
    ) {}

    /**
     * Returns the active session for a user, if any.
     *
     * @param  User  $user  Authenticated application user.
     * @return ActiveTimeSession|null
     */
    public function findForUser(User $user): ?ActiveTimeSession
    {
        return ActiveTimeSession::query()->where('user_id', $user->id)->first();
    }

    /**
     * Removes picker references that are no longer allowed for the user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  ActiveTimeSession  $session  Active timer session.
     * @return ActiveTimeSession
     */
    public function sanitizeForUser(User $user, QuickBooksToken $token, ActiveTimeSession $session): ActiveTimeSession
    {
        $current = [
            'customer_ref' => $session->customer_ref,
            'customer_name' => $session->customer_name,
            'project_ref' => $session->project_ref,
            'project_name' => $session->project_name,
            'service_ref' => $session->service_ref,
            'service_name' => $session->service_name,
        ];

        $sanitized = $this->pickerValidation->sanitizeSessionSelections($user, $token, $current);

        if ($sanitized === $current) {
            return $session;
        }

        $session->fill($sanitized)->save();

        return $session->refresh();
    }

    /**
     * Sanitizes the active timer session when picker selections are no longer allowed.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return void
     */
    public function sanitizeActiveSessionIfExists(User $user, QuickBooksToken $token): void
    {
        $session = $this->findForUser($user);

        if ($session === null) {
            return;
        }

        $this->sanitizeForUser($user, $token, $session);
    }

    /**
     * Creates or updates the active timer session for a user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken|null  $token  Valid QuickBooks OAuth token when selections require validation.
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return ActiveTimeSession
     */
    public function upsertForUser(User $user, ?QuickBooksToken $token, array $validated): ActiveTimeSession
    {
        if ($token !== null) {
            $this->pickerValidation->assertValidSelections($user, $token, $validated);
        }

        $existing = $this->findForUser($user);
        $wasRunning = $existing?->isRunning() ?? false;
        $wantsRunning = (bool) $validated['is_running'];
        $accumulated = $existing !== null ? $existing->accumulated_seconds : 0;
        $runningSince = TimerElapsed::asCarbon($existing?->running_since);

        if ($wasRunning && ! $wantsRunning) {
            if ($runningSince !== null) {
                $accumulated = TimerElapsed::cap(
                    $accumulated + max(0, $runningSince->diffInSeconds(now())),
                );
            }
            $runningSince = null;
        } elseif (! $wasRunning && $wantsRunning) {
            $runningSince = now();
        } elseif ($wasRunning && $wantsRunning) {
            // Keep the server-owned running segment; ignore client clock values.
        } else {
            $runningSince = null;
        }

        if (array_key_exists('accumulated_seconds', $validated)) {
            $accumulated = TimerElapsed::cap((int) $validated['accumulated_seconds']);

            if ($wantsRunning) {
                $runningSince = now();
            }
        }

        $isBillable = array_key_exists('is_billable', $validated)
            ? (bool) $validated['is_billable']
            : ($existing !== null ? $existing->is_billable : false);

        return ActiveTimeSession::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'customer_ref' => $validated['customer_ref'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'project_ref' => $validated['project_ref'] ?? null,
                'project_name' => $validated['project_name'] ?? null,
                'service_ref' => $validated['service_ref'] ?? null,
                'service_name' => $validated['service_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_billable' => $isBillable,
                'accumulated_seconds' => $accumulated,
                'running_since' => $runningSince,
            ],
        );
    }

    /**
     * Removes the active timer session for a user.
     *
     * @param  User  $user  Authenticated application user.
     * @return void
     */
    public function discardForUser(User $user): void
    {
        ActiveTimeSession::query()->where('user_id', $user->id)->delete();
    }

    /**
     * Logs elapsed time to QuickBooks and clears the active session.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return object
     */
    public function logForUser(User $user, QuickBooksToken $token): object
    {
        return DB::transaction(function () use ($user, $token): object {
            $session = ActiveTimeSession::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                abort(response()->json(['message' => __('api.time_tracker_empty')], 422));
            }

            $this->pickerValidation->assertValidSelections($user, $token, [
                'customer_ref' => $session->customer_ref,
                'project_ref' => $session->project_ref,
                'service_ref' => $session->service_ref,
            ]);

            $elapsedSeconds = TimerElapsed::forSession($session);

            if ($elapsedSeconds <= 0) {
                abort(response()->json(['message' => __('api.time_tracker_no_elapsed_time')], 422));
            }

            $payload = TimeTrackerLogPayload::forSession($session, $elapsedSeconds);
            $activity = $this->timeActivities->createForUser($user, $token, $payload);
            $this->discardForUser($user);

            return $activity;
        });
    }

    /**
     * Maps an active session to a JSON-friendly API shape.
     *
     * @param  ActiveTimeSession|null  $session  Active session or null when absent.
     * @return array<string, mixed>|null
     */
    public function toApi(?ActiveTimeSession $session): ?array
    {
        if ($session === null) {
            return null;
        }

        return [
            'customer_ref' => $session->customer_ref,
            'customer_name' => $session->customer_name,
            'project_ref' => $session->project_ref,
            'project_name' => $session->project_name,
            'service_ref' => $session->service_ref,
            'service_name' => $session->service_name,
            'description' => $session->description,
            'is_billable' => $session->is_billable,
            'accumulated_seconds' => $session->accumulated_seconds,
            'running_since' => TimerElapsed::asCarbon($session->running_since)?->toIso8601String(),
            'elapsed_seconds' => TimerElapsed::forSession($session),
            'is_running' => $session->isRunning(),
        ];
    }
}
