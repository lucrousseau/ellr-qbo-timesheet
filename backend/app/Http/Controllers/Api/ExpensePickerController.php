<?php

/**
 * QuickBooks account and vendor pickers for expense forms.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QboAccountListService;
use App\Services\QboVendorListService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns cached QuickBooks accounts and vendors for authenticated expense users.
 */
class ExpensePickerController extends Controller
{
    /**
     * Injects QuickBooks token resolution and list services.
     *
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  QboAccountListService  $accounts  Account list queries.
     * @param  QboVendorListService  $vendors  Vendor list queries.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly QboAccountListService $accounts,
        private readonly QboVendorListService $vendors,
    ) {}

    /**
     * Lists expense-category Chart of Accounts rows.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function expenseAccounts(Request $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());

        return response()->json([
            'data' => $this->accounts->listExpenseAccounts($token, $request->boolean('refresh')),
        ]);
    }

    /**
     * Lists payment (bank / credit card) Chart of Accounts rows.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function paymentAccounts(Request $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());

        return response()->json([
            'data' => $this->accounts->listPaymentAccounts($token, $request->boolean('refresh')),
        ]);
    }

    /**
     * Lists active vendors from the connected QuickBooks company.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function vendors(Request $request): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());

        return response()->json([
            'data' => $this->vendors->listActive($token, $request->boolean('refresh')),
        ]);
    }
}
