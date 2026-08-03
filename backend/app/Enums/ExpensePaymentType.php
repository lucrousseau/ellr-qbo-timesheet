<?php

/**
 * QuickBooks Purchase payment types for recorded expenses.
 */

namespace App\Enums;

/**
 * Backed enum of QBO Purchase PaymentType values.
 */
enum ExpensePaymentType: string
{
    case Cash = 'Cash';
    case Check = 'Check';
    case CreditCard = 'CreditCard';

    /**
     * Returns the allowed payment type values for validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
