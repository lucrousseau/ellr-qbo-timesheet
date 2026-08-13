<?php

/**
 * Resolves customer, project, and service labels from cached QBO picker lists.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\QboRefNormalizer;

/**
 * Shared label resolution for local time entries and QuickBooks snapshots.
 */
class QboRefDisplayNameService
{
    /**
     * @param  QboCustomerListService  $customers  Cached customer list service.
     * @param  QboProjectListService  $projects  Cached project list service.
     * @param  QboServiceListService  $services  Cached service item list service.
     */
    public function __construct(
        private readonly QboCustomerListService $customers,
        private readonly QboProjectListService $projects,
        private readonly QboServiceListService $services,
    ) {}

    /**
     * Resolves customer, project, and service labels from cached picker lists.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string|null  $customerRef  QuickBooks customer identifier.
     * @param  string|null  $projectRef  QuickBooks project identifier.
     * @param  string|null  $itemRef  QuickBooks service item identifier.
     * @param  User|null  $user  When set, customers are scoped to the user; otherwise the full list is used.
     * @return array{customer_name: string|null, project_name: string|null, item_name: string|null}
     */
    public function resolve(
        QuickBooksToken $token,
        ?string $customerRef,
        ?string $projectRef,
        ?string $itemRef,
        ?User $user = null,
    ): array {
        $normalizedCustomer = QboRefNormalizer::normalize($customerRef);
        $normalizedProject = QboRefNormalizer::normalize($projectRef);
        $normalizedItem = QboRefNormalizer::normalize($itemRef);

        $customerOptions = $normalizedCustomer !== null
            ? ($user !== null
                ? $this->customers->listForUser($user, $token)
                : $this->customers->listAllActive($token))
            : [];

        $projectOptions = $normalizedProject !== null && $normalizedCustomer !== null
            ? $this->projects->listForCustomer($token, $normalizedCustomer)
            : [];

        $serviceOptions = $normalizedItem !== null
            ? $this->services->listActive($token)
            : [];

        return [
            'customer_name' => QboRefNormalizer::displayNameForRef($customerOptions, $normalizedCustomer),
            'project_name' => QboRefNormalizer::displayNameForRef($projectOptions, $normalizedProject),
            'item_name' => QboRefNormalizer::displayNameForRef($serviceOptions, $normalizedItem),
        ];
    }

    /**
     * Resolves display labels for legacy QuickBooks snapshot refs.
     *
     * Uses the full customer list so historical activities keep readable names
     * even when the employee is not assigned that customer in Ellr. When the
     * customer ref is a job (common on native QBO time activities), falls back
     * to the cached project list.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string|null  $customerRef  QuickBooks customer identifier.
     * @param  string|null  $projectRef  QuickBooks project identifier.
     * @param  string|null  $itemRef  QuickBooks service item identifier.
     * @return array{customer_name: string|null, project_name: string|null, item_name: string|null}
     */
    public function forSnapshot(
        QuickBooksToken $token,
        ?string $customerRef,
        ?string $projectRef,
        ?string $itemRef,
    ): array {
        $labels = $this->resolve($token, $customerRef, $projectRef, $itemRef, null);

        if ($labels['customer_name'] !== null && $labels['project_name'] !== null) {
            return $labels;
        }

        return $this->enrichSnapshotLabelsWithJobs($token, $customerRef, $projectRef, $labels);
    }

    /**
     * Fills missing snapshot labels when refs point at QuickBooks jobs.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string|null  $customerRef  QuickBooks customer or job identifier.
     * @param  string|null  $projectRef  QuickBooks project identifier.
     * @param  array{customer_name: string|null, project_name: string|null, item_name: string|null}  $labels  Labels already resolved from picker lists.
     * @return array{customer_name: string|null, project_name: string|null, item_name: string|null}
     */
    private function enrichSnapshotLabelsWithJobs(
        QuickBooksToken $token,
        ?string $customerRef,
        ?string $projectRef,
        array $labels,
    ): array {
        if ($labels['customer_name'] === null && $customerRef !== null) {
            $job = $this->projects->findJob($token, $customerRef);

            if ($job !== null) {
                $labels['customer_name'] = QboRefNormalizer::displayNameForRef(
                    $this->customers->listAllActive($token),
                    $job['parent_ref'],
                );
                $labels['project_name'] ??= $job['display_name'] !== '' ? $job['display_name'] : null;
            }
        }

        if ($labels['project_name'] === null && $projectRef !== null) {
            $job = $this->projects->findJob($token, $projectRef);
            $labels['project_name'] = $job !== null && $job['display_name'] !== ''
                ? $job['display_name']
                : null;
        }

        return $labels;
    }
}
