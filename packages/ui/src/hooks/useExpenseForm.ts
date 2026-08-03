/**
 * @file Form state and submit logic for local expense capture.
 */

import {
  createExpense,
  fetchQboCustomers,
  fetchQboExpenseAccounts,
  fetchQboPaymentAccounts,
  fetchQboProjects,
  fetchQboVendors,
  type ExpensePayload,
  type ExpensePaymentType,
  type QboPickerOption,
} from '@ellr/api-client'
import { useCallback, useMemo, useState, type FormEvent } from 'react'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from './useGuardedAction'
import { useLazyApiSelect } from './useLazyApiSelect'

/** Static select option for QuickBooks Purchase payment types. */
export type PaymentTypeOption = {
  value: ExpensePaymentType
  label: string
}

type UseExpenseFormOptions = {
  onCreated?: () => void
  onError?: (message: string) => void
  onSuccess?: (message: string) => void
  onSubmit?: (payload: ExpensePayload) => Promise<void>
}

/**
 * Builds today's date in `YYYY-MM-DD` for the expense date field.
 * @returns Local calendar date string.
 */
function todayDateValue(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}

/**
 * Owns expense form fields, QBO pickers, and guarded submit.
 * @param options Optional submit override and flash callbacks.
 * @returns Form state, pickers, and submit handler.
 */
export function useExpenseForm({
  onCreated,
  onError,
  onSuccess,
  onSubmit,
}: UseExpenseFormOptions) {
  const { t, locale } = useLocale()
  const [amount, setAmount] = useState('')
  const [txnDate, setTxnDate] = useState(todayDateValue)
  const [paymentType, setPaymentType] = useState<PaymentTypeOption | null>(null)
  const [paymentAccount, setPaymentAccount] = useState<QboPickerOption | null>(null)
  const [expenseAccount, setExpenseAccount] = useState<QboPickerOption | null>(null)
  const [vendor, setVendor] = useState<QboPickerOption | null>(null)
  const [customer, setCustomer] = useState<QboPickerOption | null>(null)
  const [project, setProject] = useState<QboPickerOption | null>(null)
  const [description, setDescription] = useState('')
  const [isBillable, setIsBillable] = useState(false)

  const paymentTypes = useMemo<PaymentTypeOption[]>(
    () => [
      { value: 'Cash', label: t('expense.paymentTypeCash') },
      { value: 'Check', label: t('expense.paymentTypeCheck') },
      { value: 'CreditCard', label: t('expense.paymentTypeCreditCard') },
    ],
    [t],
  )

  const reportError = useCallback(
    (message: string) => {
      onError?.(message)
    },
    [onError],
  )

  const paymentAccountsSelect = useLazyApiSelect({
    enabled: true,
    fetch: (refresh, signal) => fetchQboPaymentAccounts({ refresh, signal }),
    onError: reportError,
    errorMessage: t('expense.loadPaymentAccountsFailed'),
  })
  const expenseAccountsSelect = useLazyApiSelect({
    enabled: true,
    fetch: (refresh, signal) => fetchQboExpenseAccounts({ refresh, signal }),
    onError: reportError,
    errorMessage: t('expense.loadExpenseAccountsFailed'),
  })
  const vendorsSelect = useLazyApiSelect({
    enabled: true,
    fetch: (refresh, signal) => fetchQboVendors({ refresh, signal }),
    onError: reportError,
    errorMessage: t('expense.loadVendorsFailed'),
  })
  const customersSelect = useLazyApiSelect({
    enabled: true,
    fetch: (refresh, signal) => fetchQboCustomers({ refresh, signal }),
    onError: reportError,
    errorMessage: t('expense.loadCustomersFailed'),
  })
  const fetchProjects = useCallback(
    (refresh: boolean, signal: AbortSignal) => {
      if (!customer?.id) {
        return Promise.resolve([])
      }

      return fetchQboProjects(customer.id, { refresh, signal })
    },
    [customer?.id],
  )
  const projectsSelect = useLazyApiSelect({
    enabled: Boolean(customer?.id),
    fetch: fetchProjects,
    onError: reportError,
    errorMessage: t('expense.loadProjectsFailed'),
  })

  const resetForm = useCallback(() => {
    setAmount('')
    setTxnDate(todayDateValue())
    setPaymentType(null)
    setPaymentAccount(null)
    setExpenseAccount(null)
    setVendor(null)
    setCustomer(null)
    setProject(null)
    setDescription('')
    setIsBillable(false)
  }, [])

  const { run: handleSubmit, pending: submitting } = useGuardedAction(async (event: FormEvent) => {
    event.preventDefault()

    if (!paymentAccount || !expenseAccount || amount.trim() === '' || txnDate.trim() === '') {
      return
    }

    const payload: ExpensePayload = {
      amount: amount.trim(),
      txn_date: txnDate,
      payment_type: paymentType?.value ?? 'Cash',
      payment_account_ref: paymentAccount.id,
      expense_account_ref: expenseAccount.id,
      vendor_ref: vendor?.id ?? null,
      customer_ref: customer?.id ?? null,
      project_ref: project?.id ?? null,
      description: description.trim() === '' ? null : description.trim(),
      is_billable: isBillable,
    }

    try {
      if (onSubmit) {
        await onSubmit(payload)
      } else {
        await createExpense(payload)
        onSuccess?.(t('expense.created'))
      }
      resetForm()
      onCreated?.()
    } catch (caught) {
      if (!onSubmit) {
        reportError(getApiErrorMessage(caught, t('expense.createFailed'), locale))
      }
    }
  })

  const onCustomerChange = useCallback((value: QboPickerOption | null) => {
    setCustomer(value)
    setProject(null)
  }, [])

  return {
    amount,
    setAmount,
    txnDate,
    setTxnDate,
    paymentType,
    setPaymentType,
    paymentTypes,
    paymentAccount,
    setPaymentAccount,
    expenseAccount,
    setExpenseAccount,
    vendor,
    setVendor,
    customer,
    project,
    description,
    setDescription,
    isBillable,
    setIsBillable,
    paymentAccountsSelect,
    expenseAccountsSelect,
    vendorsSelect,
    customersSelect,
    projectsSelect,
    onCustomerChange,
    setProject,
    handleSubmit,
    submitting,
    canSubmit: Boolean(paymentAccount && expenseAccount && amount.trim() && txnDate.trim()),
  }
}
