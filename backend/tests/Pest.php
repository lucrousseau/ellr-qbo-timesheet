<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->startSession();
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Arch');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function actingAsWithQboEmployee(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ], $attributes));

    Sanctum::actingAs($user);

    return $user;
}

function actingAsAdmin(array $attributes = []): User
{
    $user = User::factory()->admin()->create($attributes);

    Sanctum::actingAs($user);

    return $user;
}

function frontendHeaders(): array
{
    return [
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173',
    ];
}
