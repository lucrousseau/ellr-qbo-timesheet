<?php

/**
 * Lists local expenses owned by an authenticated employee.
 */

namespace App\Services;

use App\Models\Expense;
use App\Models\User;

/**
 * Paginates the signed-in user's expense write model.
 */
class ExpenseListService
{
    /**
     * Injects presentation helpers for API row mapping.
     *
     * @param  ExpensePresentationService  $presentation  Read-time label resolution for API rows.
     */
    public function __construct(
        private readonly ExpensePresentationService $presentation,
    ) {}

    /**
     * Lists expenses for the authenticated employee.
     *
     * @param  User  $user  Employee whose expenses are listed.
     * @param  int  $startPosition  One-based pagination offset.
     * @param  int  $maxResults  Maximum rows per page.
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|bool>}
     */
    public function listForUser(User $user, int $startPosition, int $maxResults): array
    {
        $startPosition = max(1, $startPosition);
        $maxResults = max(1, $maxResults);
        $offset = max(0, $startPosition - 1);

        $query = Expense::query()
            ->with(['user', 'reviewedBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('txn_date')
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $expenses = $query->offset($offset)->limit($maxResults)->get();
        $count = $expenses->count();

        return [
            'data' => $this->presentation->collection($expenses, $user),
            'meta' => [
                'count' => $count,
                'max_results' => $maxResults,
                'start_position' => $startPosition,
                'truncated' => $offset + $count < $total,
            ],
        ];
    }
}
