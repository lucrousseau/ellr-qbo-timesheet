<?php

use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\ExpensePickerValidationService;
use App\Services\QboAccountListService;
use App\Services\QboPickerValidationService;
use App\Services\QboVendorListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(ExpensePickerValidationService::class);

uses(RefreshDatabase::class);

it('accepts valid payment and expense account selections', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });
    $this->mock(QboVendorListService::class, function ($mock) {
        $mock->shouldNotReceive('listActive');
    });
    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldNotReceive('assertValidTimeEntrySelections');
    });

    app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
    ]);

    expect(true)->toBeTrue();
});

it('rejects missing payment account references', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) {
        $mock->shouldNotReceive('listPaymentAccounts');
        $mock->shouldNotReceive('listExpenseAccounts');
    });

    try {
        app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
            'payment_account_ref' => null,
            'expense_account_ref' => '7',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('expense_invalid_payment_account');
    }
});

it('rejects invalid payment account references', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldNotReceive('listExpenseAccounts');
    });

    try {
        app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
            'payment_account_ref' => '99',
            'expense_account_ref' => '7',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('expense_invalid_payment_account');
    }
});

it('rejects missing expense account references', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldNotReceive('listExpenseAccounts');
    });

    try {
        app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
            'payment_account_ref' => '35',
            'expense_account_ref' => null,
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('expense_invalid_expense_account');
    }
});

it('rejects invalid expense account references', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });

    try {
        app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
            'payment_account_ref' => '35',
            'expense_account_ref' => '99',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('expense_invalid_expense_account');
    }
});

it('accepts valid vendor references when provided', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });
    $this->mock(QboVendorListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listActive')->once()->with($token)->andReturn([
            ['id' => '56', 'display_name' => 'Office Depot'],
        ]);
    });

    app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
    ]);

    expect(true)->toBeTrue();
});

it('rejects invalid vendor references', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });
    $this->mock(QboVendorListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listActive')->once()->with($token)->andReturn([
            ['id' => '56', 'display_name' => 'Office Depot'],
        ]);
    });

    try {
        app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
            'payment_account_ref' => '35',
            'expense_account_ref' => '7',
            'vendor_ref' => '99',
        ]);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('expense_invalid_vendor');
    }
});

it('skips vendor validation when vendor ref is omitted', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });
    $this->mock(QboVendorListService::class, function ($mock) {
        $mock->shouldNotReceive('listActive');
    });

    app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => null,
    ]);

    expect(true)->toBeTrue();
});

it('delegates customer and project validation to the shared picker service', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });
    $this->mock(QboPickerValidationService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('assertValidTimeEntrySelections')
            ->once()
            ->with($user, $token, [
                'customer_ref' => '11',
                'project_ref' => '22',
                'item_ref' => null,
            ]);
    });

    app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'customer_ref' => '11',
        'project_ref' => '22',
    ]);
});

it('skips customer validation when only whitespace refs are provided', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });
    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldNotReceive('assertValidTimeEntrySelections');
    });

    app(ExpensePickerValidationService::class)->assertValidSelections($user, $token, [
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'customer_ref' => '   ',
        'project_ref' => '',
    ]);

    expect(true)->toBeTrue();
});

it('validates stored expense fields via assertValidExpense', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->create();
    $expense = Expense::factory()->forUser($user)->make([
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'project_ref' => '22',
    ]);

    $this->mock(QboAccountListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listPaymentAccounts')->once()->with($token)->andReturn([
            ['id' => '35', 'display_name' => 'Checking'],
        ]);
        $mock->shouldReceive('listExpenseAccounts')->once()->with($token)->andReturn([
            ['id' => '7', 'display_name' => 'Office Expenses'],
        ]);
    });
    $this->mock(QboVendorListService::class, function ($mock) use ($token) {
        $mock->shouldReceive('listActive')->once()->with($token)->andReturn([
            ['id' => '56', 'display_name' => 'Office Depot'],
        ]);
    });
    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidTimeEntrySelections')->once();
    });

    app(ExpensePickerValidationService::class)->assertValidExpense($user, $token, $expense);

    expect(true)->toBeTrue();
});
