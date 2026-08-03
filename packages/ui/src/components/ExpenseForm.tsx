/**
 * @file Form to submit a local expense for supervisor approval.
 */

import type { ExpensePayload } from '@ellr/api-client'
import { useExpenseForm } from '../hooks/useExpenseForm'
import { useLocale } from '../i18n/LocaleProvider'
import { cardClass } from '../styles/tokens'
import { Button } from './Button'
import { CheckboxField } from './CheckboxField'
import { ExpenseFormPickers } from './expenseForm/ExpenseFormPickers'
import { StaticSelect } from './StaticSelect'
import { TextAreaField } from './TextAreaField'
import { TextField } from './TextField'

type ExpenseFormProps = {
  onCreated?: () => void
  onError?: (message: string) => void
  onSuccess?: (message: string) => void
  /** When provided, used instead of calling createExpense directly. */
  onSubmit?: (payload: ExpensePayload) => Promise<void>
}

/**
 * Expense capture form with QBO account and party pickers.
 * @param props Optional submit override and flash callbacks.
 * @returns Expense submission card.
 */
export function ExpenseForm({ onCreated, onError, onSuccess, onSubmit }: ExpenseFormProps) {
  const { t } = useLocale()
  const form = useExpenseForm({ onCreated, onError, onSuccess, onSubmit })

  return (
    <section className={`space-y-4 ${cardClass}`}>
      <header>
        <h2 className="text-lg font-medium text-slate-900">{t('expense.title')}</h2>
        <p className="mt-1 text-sm text-slate-600">{t('expense.help')}</p>
      </header>

      <form className="space-y-4" onSubmit={(event) => void form.handleSubmit(event)}>
        <TextField
          label={t('expense.amount')}
          type="number"
          inputMode="decimal"
          step="0.01"
          min="0.01"
          required
          value={form.amount}
          onChange={(event) => form.setAmount(event.target.value)}
        />
        <TextField
          label={t('expense.txnDate')}
          type="date"
          required
          value={form.txnDate}
          onChange={(event) => form.setTxnDate(event.target.value)}
        />
        <StaticSelect
          label={t('expense.paymentType')}
          placeholder={t('expense.paymentTypeCash')}
          value={form.paymentType}
          options={form.paymentTypes}
          onChange={form.setPaymentType}
          getOptionValue={(option) => option.value}
          getOptionLabel={(option) => option.label}
        />
        <ExpenseFormPickers
          paymentAccount={form.paymentAccount}
          expenseAccount={form.expenseAccount}
          vendor={form.vendor}
          customer={form.customer}
          project={form.project}
          paymentAccountsSelect={form.paymentAccountsSelect}
          expenseAccountsSelect={form.expenseAccountsSelect}
          vendorsSelect={form.vendorsSelect}
          customersSelect={form.customersSelect}
          projectsSelect={form.projectsSelect}
          onPaymentAccountChange={form.setPaymentAccount}
          onExpenseAccountChange={form.setExpenseAccount}
          onVendorChange={form.setVendor}
          onCustomerChange={form.onCustomerChange}
          onProjectChange={form.setProject}
        />
        <TextAreaField
          label={t('expense.description')}
          rows={3}
          value={form.description}
          onChange={(event) => form.setDescription(event.target.value)}
        />
        <CheckboxField
          label={t('expense.billable')}
          checked={form.isBillable}
          onChange={form.setIsBillable}
        />
        <Button type="submit" disabled={form.submitting || !form.canSubmit}>
          {form.submitting ? t('expense.submitting') : t('expense.submit')}
        </Button>
      </form>
    </section>
  )
}
