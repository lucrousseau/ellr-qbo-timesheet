<?php

/**
 * Domain exception for QuickBooks OAuth flow failures.
 */

namespace App\Exceptions;

/**
 * Thrown when OAuth state, redirect, or token exchange fails.
 */
class QuickBooksOAuthException extends QuickBooksException {}
