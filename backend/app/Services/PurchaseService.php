<?php

/**
 * Creates QuickBooks Purchase (expense) entities from approved Ellr expenses.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\QboRefNormalizer;
use QuickBooksOnline\API\Facades\Purchase;

/**
 * Maps validated expense payloads to QBO Purchase Add operations.
 */
class PurchaseService
{
    /**
     * Injects QuickBooks SDK access and API error formatting.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly QuickBooksApiErrorFormatterService $apiErrors,
    ) {}

    /**
     * Creates a Purchase expense in QuickBooks for the organization.
     *
     * @param  User  $user  Expense owner (used for audit context only).
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  array<string, mixed>  $validated  Validated expense create payload.
     * @return object
     */
    public function createForUser(User $user, QuickBooksToken $token, array $validated): object
    {
        unset($user);

        $paymentAccountRef = QboRefNormalizer::normalize($validated['payment_account_ref'] ?? null);
        $expenseAccountRef = QboRefNormalizer::normalize($validated['expense_account_ref'] ?? null);
        $amount = (float) ($validated['amount'] ?? 0); // @pest-mutate-ignore QBO Purchase payload mapping

        $lineDetail = [
            'AccountRef' => ['value' => $expenseAccountRef],
            'BillableStatus' => ! empty($validated['is_billable']) ? 'Billable' : 'NotBillable',
        ];

        $customerRef = QboRefNormalizer::normalize($validated['project_ref'] ?? $validated['customer_ref'] ?? null);

        if ($customerRef !== null) {
            $lineDetail['CustomerRef'] = ['value' => $customerRef];
        }

        $line = [
            'Amount' => $amount,
            'DetailType' => 'AccountBasedExpenseLineDetail',
            'AccountBasedExpenseLineDetail' => $lineDetail,
        ];

        if (array_key_exists('description', $validated) && $validated['description'] !== null) {
            $line['Description'] = $validated['description'];
        }

        $purchasePayload = [
            'PaymentType' => (string) ($validated['payment_type'] ?? 'Cash'), // @pest-mutate-ignore QBO Purchase payload mapping
            'AccountRef' => ['value' => $paymentAccountRef],
            'TxnDate' => (string) ($validated['txn_date'] ?? now()->toDateString()), // @pest-mutate-ignore QBO Purchase payload mapping
            'Line' => [$line],
        ];

        $vendorRef = QboRefNormalizer::normalize($validated['vendor_ref'] ?? null);

        if ($vendorRef !== null) {
            $purchasePayload['EntityRef'] = [
                'value' => $vendorRef,
                'type' => 'Vendor', // @pest-mutate-ignore QBO Purchase payload mapping
            ];
        }

        $dataService = $this->quickBooks->dataService($token);
        $purchase = Purchase::create($purchasePayload);
        $result = $dataService->Add($purchase);

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        return $result;
    }
}
