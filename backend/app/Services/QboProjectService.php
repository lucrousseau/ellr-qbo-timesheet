<?php

/**
 * Creates QuickBooks projects (job customers) for administrator workflows.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Support\QboRefNormalizer;
use QuickBooksOnline\API\Facades\Customer;

/**
 * Writes project (sub-customer) records to QuickBooks and refreshes list caches.
 */
class QboProjectService
{
    /**
     * Injects QuickBooks SDK access, customer validation, list caching, and API error formatting.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboCustomerListService  $customers  Active customer list for parent validation.
     * @param  QboListCacheService  $listCache  Shared QBO list cache policy.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboCustomerListService $customers,
        private readonly QboListCacheService $listCache,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
    ) {}

    /**
     * Creates a QuickBooks project under an active parent customer.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  array{customer_ref: string, display_name: string}  $validated  Validated create payload.
     * @return array{id: string, display_name: string}
     */
    public function createForCustomer(QuickBooksToken $token, array $validated): array
    {
        $parentRef = QboRefNormalizer::normalize($validated['customer_ref']);
        $displayName = trim($validated['display_name']);

        if ($parentRef === null || $displayName === '') {
            abort(response()->json(['message' => __('api.admin_invalid_project_parent')], 422));
        }

        $parentName = QboRefNormalizer::displayNameForRef(
            $this->customers->listAllActive($token, true),
            $parentRef,
        );

        if ($parentName === null) {
            abort(response()->json(['message' => __('api.admin_invalid_project_parent')], 422));
        }

        $dataService = $this->quickBooks->dataService($token);
        $project = Customer::create([
            'DisplayName' => $displayName,
            'Job' => true,
            'BillWithParent' => true,
            'ParentRef' => [
                'value' => $parentRef,
                'name' => $parentName,
            ],
        ]);
        $result = $dataService->Add($project);

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        $this->listCache->forget(QboListCacheService::RESOURCE_PROJECTS, $token->realm_id);

        $created = (object) (array) $result; // @pest-mutate-ignore normalize sparse QuickBooks Add response shapes
        $createdId = trim((string) ($created->Id ?? '')); // @pest-mutate-ignore normalize optional QBO project id
        $createdName = trim((string) ($created->DisplayName ?? '')); // @pest-mutate-ignore normalize optional QBO project label

        if ($createdId === '') {
            abort(response()->json(['message' => __('api.quickbooks_api_error')], 422));
        }

        return [
            'id' => $createdId,
            'display_name' => $createdName !== '' ? $createdName : $displayName,
        ];
    }
}
