<?php

/**
 * Lookback depth for realm-wide time activity reconcile imports.
 */

namespace App\Enums;

/**
 * Controls how many TxnDate windows a reconcile scan imports from QuickBooks.
 */
enum TimeActivityReconcileScope
{
    case Recent;

    case Full;
}
