<?php

/**
 * REST endpoints for local expenses awaiting supervisor approval.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Services\ExpenseListService;
use App\Services\ExpensePresentationService;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authorizes requests and delegates local expense CRUD to ExpenseService.
 */
class ExpenseController extends Controller
{
    /**
     * @param  ExpenseListService  $expenseList  Owned expense list service.
     * @param  ExpenseService  $expenses  Local expense writer.
     * @param  ExpensePresentationService  $presentation  Read-time QBO label resolver.
     */
    public function __construct(
        private readonly ExpenseListService $expenseList,
        private readonly ExpenseService $expenses,
        private readonly ExpensePresentationService $presentation,
    ) {}

    /**
     * Lists expenses for the authenticated employee.
     *
     * @param  ListExpenseRequest  $request  Validated list query parameters.
     * @return JsonResponse
     */
    public function index(ListExpenseRequest $request): JsonResponse
    {
        return response()->json($this->expenseList->listForUser(
            $request->user(),
            $request->listStartPosition(),
            $request->listMaxResults(),
        ));
    }

    /**
     * Creates a pending expense for supervisor approval.
     *
     * @param  StoreExpenseRequest  $request  Validated create request.
     * @return JsonResponse
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->createForUser($request->user(), $request->validated());

        return response()->json([
            'data' => $this->presentation->resource($expense->load('user'), $request->user()),
        ], 201);
    }

    /**
     * Updates a pending expense owned by the authenticated employee.
     *
     * @param  UpdateExpenseRequest  $request  Validated update request.
     * @param  int  $expense  Local expense identifier.
     * @return JsonResponse
     */
    public function update(UpdateExpenseRequest $request, int $expense): JsonResponse
    {
        $updated = $this->expenses->updateForUser(
            $request->user(),
            $expense,
            $request->validated(),
        );

        return response()->json([
            'data' => $this->presentation->resource($updated->load('user'), $request->user()),
        ]);
    }

    /**
     * Deletes a pending or rejected expense owned by the authenticated employee.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  int  $expense  Local expense identifier.
     * @return JsonResponse
     */
    public function destroy(Request $request, int $expense): JsonResponse
    {
        $this->expenses->deleteForUser($request->user(), $expense);

        return response()->json(null, 204);
    }
}
