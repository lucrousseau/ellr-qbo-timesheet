<?php

/**
 * Resolves QuickBooks picker display labels from cached list endpoints.
 */

namespace App\Services;

use App\Models\ActiveTimeSession;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserQboCustomer;
use App\Support\QboRefNormalizer;
use Illuminate\Support\Collection;

/**
 * Looks up human-readable QuickBooks labels for stored entity references.
 */
class QboPickerDisplayNameService
{
    /**
     * @param  QboEmployeeListService  $employees  Cached employee list service.
     * @param  QboCustomerListService  $customers  Cached customer list service.
     * @param  QboProjectListService  $projects  Cached project list service.
     * @param  QboServiceListService  $services  Cached service item list service.
     * @param  QboEmployeeService  $employeeLookup  Single-employee QuickBooks lookup fallback.
     */
    public function __construct(
        private readonly QboEmployeeListService $employees,
        private readonly QboCustomerListService $customers,
        private readonly QboProjectListService $projects,
        private readonly QboServiceListService $services,
        private readonly QboEmployeeService $employeeLookup,
    ) {}

    /**
     * Resolves the QuickBooks employee display name for a stored reference.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string|null  $employeeRef  QuickBooks employee identifier.
     * @return string|null
     */
    public function employeeDisplayName(QuickBooksToken $token, ?string $employeeRef): ?string
    {
        $normalized = QboRefNormalizer::normalize($employeeRef);

        if ($normalized === null) {
            return null;
        }

        $name = QboRefNormalizer::displayNameForRef($this->employees->listActive($token), $normalized);

        if ($name !== null) {
            return $name;
        }

        $employee = $this->employeeLookup->findEmployee($token, $normalized);

        return $employee !== null && $employee['display_name'] !== ''
            ? $employee['display_name']
            : null;
    }

    /**
     * Builds picker rows for assigned customer references.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  User  $user  Timesheet user whose assignments are listed.
     * @param  Collection<int, UserQboCustomer>|iterable<int, UserQboCustomer>  $assignments  Stored assignments.
     * @return list<array{id: string, display_name: string}>
     */
    public function assignedCustomerRows(QuickBooksToken $token, User $user, iterable $assignments): array
    {
        $options = $user->qbo_all_customers_access
            ? $this->customers->listAllActive($token)
            : $this->customers->listForUser($user, $token);

        $rows = [];

        foreach ($assignments as $assignment) {
            $ref = QboRefNormalizer::normalize($assignment->qbo_customer_ref);

            if ($ref === null) {
                continue;
            }

            $rows[] = [
                'id' => $ref,
                'display_name' => QboRefNormalizer::displayNameForRef($options, $ref) ?? $ref,
            ];
        }

        usort(
            $rows,
            fn (array $left, array $right): int => strcasecmp($left['display_name'], $right['display_name']),
        );

        return $rows;
    }

    /**
     * Resolves display labels for an active timer session.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  User  $user  Session owner.
     * @param  ActiveTimeSession  $session  Persisted timer session.
     * @return array{customer_name: string|null, project_name: string|null, service_name: string|null}
     */
    public function sessionDisplayNames(QuickBooksToken $token, User $user, ActiveTimeSession $session): array
    {
        $customerRef = QboRefNormalizer::normalize($session->customer_ref);
        $projectRef = QboRefNormalizer::normalize($session->project_ref);
        $serviceRef = QboRefNormalizer::normalize($session->service_ref);

        $customerOptions = $customerRef !== null
            ? $this->customers->listForUser($user, $token)
            : [];

        $projectOptions = $projectRef !== null && $customerRef !== null
            ? $this->projects->listForCustomer($token, $customerRef)
            : [];

        $serviceOptions = $serviceRef !== null
            ? $this->services->listActive($token)
            : [];

        return [
            'customer_name' => QboRefNormalizer::displayNameForRef($customerOptions, $customerRef),
            'project_name' => QboRefNormalizer::displayNameForRef($projectOptions, $projectRef),
            'service_name' => QboRefNormalizer::displayNameForRef($serviceOptions, $serviceRef),
        ];
    }

    /**
     * Resolves display labels for a local time entry.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  User  $user  Entry owner.
     * @param  TimeEntry  $entry  Persisted local time entry.
     * @return array{customer_name: string|null, project_name: string|null, item_name: string|null}
     */
    public function entryDisplayNames(QuickBooksToken $token, User $user, TimeEntry $entry): array
    {
        $customerRef = QboRefNormalizer::normalize($entry->customer_ref);
        $projectRef = QboRefNormalizer::normalize($entry->project_ref);
        $itemRef = QboRefNormalizer::normalize($entry->item_ref);

        $customerOptions = $customerRef !== null
            ? $this->customers->listForUser($user, $token)
            : [];

        $projectOptions = $projectRef !== null && $customerRef !== null
            ? $this->projects->listForCustomer($token, $customerRef)
            : [];

        $serviceOptions = $itemRef !== null
            ? $this->services->listActive($token)
            : [];

        return [
            'customer_name' => QboRefNormalizer::displayNameForRef($customerOptions, $customerRef),
            'project_name' => QboRefNormalizer::displayNameForRef($projectOptions, $projectRef),
            'item_name' => QboRefNormalizer::displayNameForRef($serviceOptions, $itemRef),
        ];
    }
}
