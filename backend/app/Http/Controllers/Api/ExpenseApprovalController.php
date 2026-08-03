<?php

/**
 * REST endpoints for supervisor expense approval before QuickBooks Purchase sync.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListExpenseRequest;
use App\Http\Requests\RejectExpenseRequest;
use App\Services\ExpenseApprovalService;
use App\Services\ExpensePresentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lists pending expenses and approves or rejects them for QBO synchronization.
 */
class ExpenseApprovalController extends Controller
{
    /**
     * Injects the approval workflow service.
     *
     * @param  ExpenseApprovalService  $approvals  Expense approval service instance.
     * @param  ExpensePresentationService  $presentation  Read-time label resolution for API rows.
     */
    public function __construct(
        private readonly ExpenseApprovalService $approvals,
        private readonly ExpensePresentationService $presentation,
    ) {}

    /**
     * Lists pending expenses the actor may review.
     *
     * @param  ListExpenseRequest  $request  Validated list query parameters.
     * @return JsonResponse
     */
    public function index(ListExpenseRequest $request): JsonResponse
    {
        return response()->json($this->approvals->listPendingForReviewer(
            $request->user(),
            $request->listStartPosition(),
            $request->listMaxResults(),
        ));
    }

    /**
     * Approves a pending expense and synchronizes it to QuickBooks.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  int  $expense  Local expense identifier.
     * @return JsonResponse
     */
    public function approve(Request $request, int $expense): JsonResponse
    {
        $approved = $this->approvals->approve($request->user(), $expense);

        return response()->json([
            'data' => $this->presentation->resource($approved, $request->user()),
        ]);
    }

    /**
     * Rejects a pending expense without synchronizing it to QuickBooks.
     *
     * @param  RejectExpenseRequest  $request  Validated rejection payload.
     * @param  int  $expense  Local expense identifier.
     * @return JsonResponse
     */
    public function reject(RejectExpenseRequest $request, int $expense): JsonResponse
    {
        $rejected = $this->approvals->reject(
            $request->user(),
            $expense,
            $request->validated('reason'),
        );

        return response()->json([
            'data' => $this->presentation->resource($rejected, $request->user()),
        ]);
    }
}
