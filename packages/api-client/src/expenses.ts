/**
 * @file Local expense API helpers and approval workflow types.
 */

import { apiFetch } from './api'

/** Approval lifecycle status for a local expense. */
export type ExpenseStatus = 'pending' | 'approved' | 'rejected'

/** QuickBooks Purchase payment type for an expense. */
export type ExpensePaymentType = 'Cash' | 'Check' | 'CreditCard'

/**
 * Local expense returned by the API before or after supervisor review.
 */
export type Expense = {
  id: number
  user_id: number | null
  employee_name?: string | null
  amount: string
  txn_date: string
  payment_type: ExpensePaymentType
  payment_account_ref: string
  payment_account_name?: string | null
  expense_account_ref: string
  expense_account_name?: string | null
  vendor_ref?: string | null
  vendor_name?: string | null
  customer_ref?: string | null
  customer_name?: string | null
  project_ref?: string | null
  project_name?: string | null
  description?: string | null
  is_billable: boolean
  status: ExpenseStatus
  reviewed_by_id?: number | null
  reviewed_by_name?: string | null
  reviewed_at?: string | null
  rejection_reason?: string | null
  qbo_id?: string | null
  created_at?: string | null
}

/**
 * Request body to create a local expense.
 */
export type ExpensePayload = {
  amount: number | string
  txn_date: string
  payment_type?: ExpensePaymentType
  payment_account_ref: string
  expense_account_ref: string
  vendor_ref?: string | null
  customer_ref?: string | null
  project_ref?: string | null
  description?: string | null
  is_billable?: boolean
}

/**
 * Partial update payload for a pending local expense.
 */
export type ExpenseUpdatePayload = Partial<ExpensePayload>

/**
 * Optional query parameters for listing expenses.
 */
export type ListExpensesParams = {
  start_position?: number
  max_results?: number
}

/**
 * Pagination metadata for expense list responses.
 */
export type ExpenseListMeta = {
  count: number
  max_results: number
  start_position: number
  truncated: boolean
}

/**
 * Paginated expense list returned by the API.
 */
export type ExpenseListResponse = {
  data: Expense[]
  meta: ExpenseListMeta
}

/**
 * Builds a query string for expense list endpoints.
 * @param params Optional pagination parameters.
 * @returns Query string including a leading `?`, or an empty string.
 */
function expenseListQuery(params: ListExpensesParams = {}): string {
  const search = new URLSearchParams()

  if (params.start_position !== undefined) {
    search.set('start_position', String(params.start_position))
  }

  if (params.max_results !== undefined) {
    search.set('max_results', String(params.max_results))
  }

  const query = search.toString()

  return query ? `?${query}` : ''
}

/**
 * Lists local expenses for the signed-in employee.
 * @param params Optional pagination parameters.
 * @returns Expense rows and pagination metadata.
 */
export async function listExpenses(
  params: ListExpensesParams = {},
): Promise<ExpenseListResponse> {
  return apiFetch<ExpenseListResponse>(`/expenses${expenseListQuery(params)}`)
}

/**
 * Creates a pending local expense for supervisor approval.
 * @param payload Expense fields to persist locally.
 * @returns Created expense row.
 */
export async function createExpense(payload: ExpensePayload): Promise<Expense> {
  const response = await apiFetch<{ data: Expense }>('/expenses', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  return response.data
}

/**
 * Updates a pending local expense owned by the signed-in employee.
 * @param id Local expense identifier.
 * @param payload Fields to update.
 * @returns Updated expense row.
 */
export async function updateExpense(
  id: number,
  payload: ExpenseUpdatePayload,
): Promise<Expense> {
  const response = await apiFetch<{ data: Expense }>(`/expenses/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })

  return response.data
}

/**
 * Deletes a pending or rejected local expense.
 * @param id Local expense identifier.
 */
export async function deleteExpense(id: number): Promise<void> {
  await apiFetch(`/expenses/${id}`, { method: 'DELETE' })
}

/**
 * Lists pending expenses the signed-in reviewer may approve.
 * @param params Optional pagination parameters.
 * @param admin When true, uses the administrator review route.
 * @returns Pending expense rows and pagination metadata.
 */
export async function listPendingExpenseApprovals(
  params: ListExpensesParams = {},
  admin = false,
): Promise<ExpenseListResponse> {
  const base = admin ? '/admin/expense-approvals' : '/expense-approvals'

  return apiFetch<ExpenseListResponse>(`${base}${expenseListQuery(params)}`)
}

/**
 * Approves a pending expense and queues QuickBooks Purchase sync.
 * @param id Local expense identifier.
 * @param admin When true, uses the administrator review route.
 * @returns Approved expense row.
 */
export async function approveExpense(id: number, admin = false): Promise<Expense> {
  const base = admin ? '/admin/expense-approvals' : '/expense-approvals'
  const response = await apiFetch<{ data: Expense }>(`${base}/${id}/approve`, {
    method: 'POST',
    body: JSON.stringify({}),
  })

  return response.data
}

/**
 * Rejects a pending expense without synchronizing it to QuickBooks.
 * @param id Local expense identifier.
 * @param reason Optional rejection reason shown to the employee.
 * @param admin When true, uses the administrator review route.
 * @returns Rejected expense row.
 */
export async function rejectExpense(
  id: number,
  reason?: string | null,
  admin = false,
): Promise<Expense> {
  const base = admin ? '/admin/expense-approvals' : '/expense-approvals'
  const response = await apiFetch<{ data: Expense }>(`${base}/${id}/reject`, {
    method: 'POST',
    body: JSON.stringify({ reason: reason ?? null }),
  })

  return response.data
}
