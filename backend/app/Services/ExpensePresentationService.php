<?php

/**
 * Resolves QuickBooks picker labels for local expense API responses.
 */

namespace App\Services;

use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\ExpenseApiResponse;
use App\Support\QboRefNormalizer;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Presents local expenses with read-time QuickBooks display labels.
 */
class ExpensePresentationService
{
    /**
     * Injects display name resolution and organization token lookup.
     *
     * @param  QboAccountListService  $accounts  Cached account list service.
     * @param  QboVendorListService  $vendors  Cached vendor list service.
     * @param  QboCustomerListService  $customers  Cached customer list service.
     * @param  QboProjectListService  $projects  Cached project list service.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves organization QBO token.
     */
    public function __construct(
        private readonly QboAccountListService $accounts,
        private readonly QboVendorListService $vendors,
        private readonly QboCustomerListService $customers,
        private readonly QboProjectListService $projects,
        private readonly QuickBooksTokenResolverService $tokenResolver,
    ) {}

    /**
     * Maps one expense to an API payload with resolved picker labels.
     *
     * @param  Expense  $expense  Local expense row.
     * @param  User|null  $viewer  Authenticated user used to resolve organization token.
     * @param  QuickBooksToken|null  $token  Pre-resolved token when already available.
     * @return array<string, mixed>
     */
    public function resource(Expense $expense, ?User $viewer = null, ?QuickBooksToken $token = null): array
    {
        $expense->loadMissing(['user', 'reviewedBy']); // @pest-mutate-ignore API resource relation preload

        return ExpenseApiResponse::resource($expense, $this->resolveLabels($expense, $viewer, $token));
    }

    /**
     * Maps a collection of expenses to API payloads.
     *
     * @param  iterable<int, Expense>  $expenses  Expense rows.
     * @param  User  $viewer  Authenticated user used to resolve organization token.
     * @param  QuickBooksToken|null  $token  Pre-resolved token when already available.
     * @return list<array<string, mixed>>
     */
    public function collection(iterable $expenses, User $viewer, ?QuickBooksToken $token = null): array
    {
        $resolvedToken = $token ?? $this->tryResolveToken($viewer);
        $rows = [];

        foreach ($expenses as $expense) {
            $rows[] = $this->resource($expense, $viewer, $resolvedToken);
        }

        return $rows;
    }

    /**
     * Resolves picker labels for one expense when a token is available.
     *
     * @param  Expense  $expense  Local expense row.
     * @param  User|null  $viewer  Authenticated user used to resolve organization token.
     * @param  QuickBooksToken|null  $token  Pre-resolved token when already available.
     * @return array{
     *     payment_account_name: string|null,
     *     expense_account_name: string|null,
     *     vendor_name: string|null,
     *     customer_name: string|null,
     *     project_name: string|null
     * }|null
     */
    private function resolveLabels(Expense $expense, ?User $viewer, ?QuickBooksToken $token = null): ?array
    {
        $owner = $expense->user;

        if ($owner === null) {
            return null;
        }

        $resolvedToken = $token ?? $this->tryResolveToken($viewer ?? $owner);

        if ($resolvedToken === null) {
            return null;
        }

        $allAccounts = $this->accounts->listAll($resolvedToken);
        $customerRef = QboRefNormalizer::normalize($expense->customer_ref);
        $projectRef = QboRefNormalizer::normalize($expense->project_ref);

        $customerOptions = $customerRef !== null
            ? $this->customers->listForUser($owner, $resolvedToken)
            : [];
        $projectOptions = $projectRef !== null && $customerRef !== null
            ? $this->projects->listForCustomer($resolvedToken, $customerRef)
            : [];

        return [
            'payment_account_name' => QboRefNormalizer::displayNameForRef($allAccounts, $expense->payment_account_ref),
            'expense_account_name' => QboRefNormalizer::displayNameForRef($allAccounts, $expense->expense_account_ref),
            'vendor_name' => QboRefNormalizer::displayNameForRef($this->vendors->listActive($resolvedToken), $expense->vendor_ref),
            'customer_name' => QboRefNormalizer::displayNameForRef($customerOptions, $customerRef),
            'project_name' => QboRefNormalizer::displayNameForRef($projectOptions, $projectRef),
        ];
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
