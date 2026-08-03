<?php

/**
 * Lists QuickBooks Chart of Accounts rows for expense pickers.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Support\QboExpenseQuery;
use App\Support\QboQueryResult;

/**
 * Queries QuickBooks accounts with realm-scoped caching for expense forms.
 */
class QboAccountListService
{
    /** @var list<string> */
    private const EXPENSE_ACCOUNT_TYPES = [
        'Expense',
        'Cost of Goods Sold',
        'Other Expense',
    ];

    /** @var list<string> */
    private const PAYMENT_ACCOUNT_TYPES = [
        'Bank',
        'Credit Card',
        'Other Current Asset',
    ];

    /**
     * Injects QuickBooks SDK access, list caching, and API error formatting.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboListCacheService  $listCache  Shared QBO list cache policy.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QboListCacheService $listCache,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
    ) {}

    /**
     * Returns expense-category accounts for the connected QuickBooks company.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached account list.
     * @return array<int, array{id: string, display_name: string, account_type: string}>
     */
    public function listExpenseAccounts(QuickBooksToken $token, bool $refresh = false): array
    {
        return array_values(array_filter(
            $this->listAll($token, $refresh),
            fn (array $account): bool => in_array($account['account_type'], self::EXPENSE_ACCOUNT_TYPES, true),
        ));
    }

    /**
     * Returns payment (bank / credit card) accounts for the connected company.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached account list.
     * @return array<int, array{id: string, display_name: string, account_type: string}>
     */
    public function listPaymentAccounts(QuickBooksToken $token, bool $refresh = false): array
    {
        return array_values(array_filter(
            $this->listAll($token, $refresh),
            fn (array $account): bool => in_array($account['account_type'], self::PAYMENT_ACCOUNT_TYPES, true),
        ));
    }

    /**
     * Returns all cached active accounts for the connected QuickBooks company.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  bool  $refresh  When true, bypasses and replaces the cached account list.
     * @return array<int, array{id: string, display_name: string, account_type: string}>
     */
    public function listAll(QuickBooksToken $token, bool $refresh = false): array
    {
        return $this->listCache->remember(
            QboListCacheService::RESOURCE_ACCOUNTS,
            $token->realm_id,
            $refresh,
            fn (): array => $this->queryActive($token),
        );
    }

    /**
     * Queries QuickBooks for active accounts without using the cache.
     *
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @return array<int, array{id: string, display_name: string, account_type: string}>
     */
    private function queryActive(QuickBooksToken $token): array
    {
        $dataService = $this->quickBooks->dataService($token);
        $maxResults = $this->listCache->maxResults();
        $accounts = $dataService->Query(QboExpenseQuery::listAccounts($maxResults));

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return array_values(array_map(
            fn (object $account): array => [
                'id' => (string) ($account->Id ?? ''),
                'display_name' => (string) ($account->Name ?? ''),
                'account_type' => (string) ($account->AccountType ?? ''),
            ],
            QboQueryResult::entities($accounts),
        ));
    }
}
