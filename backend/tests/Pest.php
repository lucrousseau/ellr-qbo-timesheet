<?php

use App\Models\User;
use App\Support\PasswordPolicy;
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

function validTestPassword(): string
{
    return PasswordPolicy::validTestPassword();
}

/**
 * Writes JSON to a temp file for isolated password-policy tests.
 *
 * @param  array<string, mixed>  $payload
 * @return string Absolute path to the temp file.
 */
function writeTempPasswordPolicyJson(array $payload): string
{
    $path = tempnam(sys_get_temp_dir(), 'ellr-password-policy-');
    if ($path === false) {
        throw new RuntimeException('Unable to create a temp password policy file.');
    }

    $jsonPath = $path.'.json';
    rename($path, $jsonPath);
    file_put_contents($jsonPath, json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    return $jsonPath;
}

/**
 * Runs a callback with a password-policy env override, then restores the previous value.
 *
 * @param  string  $envKey  Environment variable to override.
 * @param  string  $path  Readable JSON file path.
 */
function withPasswordPolicyEnvOverride(string $envKey, string $path, callable $callback): void
{
    $previous = $_ENV[$envKey] ?? getenv($envKey);
    putenv($envKey.'='.$path);
    $_ENV[$envKey] = $path;

    try {
        $callback();
    } finally {
        if ($previous === false || $previous === null || $previous === '') {
            putenv($envKey);
            unset($_ENV[$envKey]);
        } else {
            putenv($envKey.'='.$previous);
            $_ENV[$envKey] = $previous;
        }
    }
}

/**
 * Uses a temp password-policy.json override instead of mutating backend/config.
 *
 * @param  array<string, mixed>  $policy
 */
function withPasswordPolicy(array $policy, callable $callback): void
{
    $path = writeTempPasswordPolicyJson($policy);

    withPasswordPolicyEnvOverride('PASSWORD_POLICY_CONFIG_PATH', $path, function () use ($callback, $path) {
        try {
            $callback();
        } finally {
            @unlink($path);
        }
    });
}

/**
 * Uses a temp test-passwords.json override instead of mutating backend/config.
 *
 * @param  array<string, mixed>  $passwords
 */
function withTestPasswords(array $passwords, callable $callback): void
{
    $path = writeTempPasswordPolicyJson($passwords);

    withPasswordPolicyEnvOverride('PASSWORD_TEST_PASSWORDS_PATH', $path, function () use ($callback, $path) {
        try {
            $callback();
        } finally {
            @unlink($path);
        }
    });
}

/**
 * Uses a temp test-passwords.json file with raw JSON contents.
 */
function withTestPasswordsContents(string $contents, callable $callback): void
{
    $path = tempnam(sys_get_temp_dir(), 'ellr-test-passwords-');
    if ($path === false) {
        throw new RuntimeException('Unable to create a temp test passwords file.');
    }

    $jsonPath = $path.'.json';
    rename($path, $jsonPath);
    file_put_contents($jsonPath, $contents);

    withPasswordPolicyEnvOverride('PASSWORD_TEST_PASSWORDS_PATH', $jsonPath, function () use ($callback, $jsonPath) {
        try {
            $callback();
        } finally {
            @unlink($jsonPath);
        }
    });
}

/**
 * Overrides password-policy.json search candidates for isolated path resolution tests.
 *
 * @param  list<string>  $candidates
 */
function withPasswordPolicyCandidates(array $candidates, callable $callback): void
{
    withPasswordPolicyEnvOverride(
        'PASSWORD_POLICY_CONFIG_CANDIDATES',
        json_encode(array_values($candidates), JSON_THROW_ON_ERROR),
        $callback,
    );
}

/**
 * Overrides test-passwords.json search candidates for isolated path resolution tests.
 *
 * @param  list<string>  $candidates
 */
function withTestPasswordsCandidates(array $candidates, callable $callback): void
{
    withPasswordPolicyEnvOverride(
        'PASSWORD_TEST_PASSWORDS_CANDIDATES',
        json_encode(array_values($candidates), JSON_THROW_ON_ERROR),
        $callback,
    );
}
