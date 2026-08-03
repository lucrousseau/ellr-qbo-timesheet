<?php

use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\QboAccountListService;
use App\Services\QboCustomerListService;
use App\Services\QboPickerDisplayNameService;
use App\Services\QboProjectListService;
use App\Services\QboVendorListService;
use App\Services\QuickBooksWebhookIdempotencyService;
use App\Services\QuickBooksWebhookProcessorService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
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
    if (! array_key_exists('organization_id', $attributes)) {
        $attributes['organization_id'] = Organization::factory()->create()->id;
    }

    $user = User::factory()->create(array_merge([
        'qbo_employee_ref' => '7',
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

/**
 * Creates a timesheet user in the same organization as the given administrator.
 *
 * @param  array<string, mixed>  $attributes
 */
function timesheetUserFor(User $admin, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ], $attributes));
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

function validTestPasswordAlt(): string
{
    return PasswordPolicy::validTestPasswordAlt();
}

/**
 * Mocks QuickBooks customer list responses in feature tests.
 *
 * @param  array<int, array{id: string, display_name: string}>  $customers
 */
function mockQboCustomerList(array $customers = [['id' => '11', 'display_name' => 'Acme Corp']]): void
{
    test()->mock(QboCustomerListService::class, function ($mock) use ($customers) {
        $mock->shouldReceive('listAllActive')->andReturn($customers);
        $mock->shouldReceive('listForUser')->andReturnUsing(
            function ($user, $token, bool $refresh = false) use ($customers): array {
                if ($user->qbo_all_customers_access) {
                    return $customers;
                }

                $assigned = [];

                foreach ($user->qboCustomers as $assignment) {
                    $assigned[(string) $assignment->qbo_customer_ref] = true;
                }

                if ($assigned === []) {
                    foreach ($user->qboCustomers()->pluck('qbo_customer_ref') as $ref) {
                        $assigned[(string) $ref] = true;
                    }
                }

                return array_values(array_filter(
                    $customers,
                    fn (array $row): bool => isset($assigned[$row['id']]),
                ));
            },
        );
    });
}

/**
 * Mocks assigned customer picker rows for admin assignment endpoints.
 *
 * @param  array<int, array{id: string, display_name: string}>  $rows
 */
function mockAssignedCustomerRows(array $rows = [['id' => '11', 'display_name' => 'Acme Corp']]): void
{
    test()->mock(QboPickerDisplayNameService::class, function ($mock) use ($rows) {
        $mock->shouldReceive('assignedCustomerRows')->andReturn($rows);
        $mock->shouldReceive('sessionDisplayNames')->andReturn([
            'customer_name' => null,
            'project_name' => null,
            'service_name' => null,
        ]);
    });
}

/**
 * Mocks active timer session display labels resolved from QuickBooks lists.
 *
 * @param  array{customer_name?: string|null, project_name?: string|null, service_name?: string|null}  $labels
 */
function mockQboSessionDisplayNames(array $labels = [
    'customer_name' => null,
    'project_name' => null,
    'service_name' => null,
]): void
{
    test()->mock(QboPickerDisplayNameService::class, function ($mock) use ($labels) {
        $mock->shouldReceive('sessionDisplayNames')->andReturn([
            'customer_name' => $labels['customer_name'] ?? null,
            'project_name' => $labels['project_name'] ?? null,
            'service_name' => $labels['service_name'] ?? null,
        ]);
    });
}

/**
 * Mocks local time entry display labels and employee names for approval and API tests.
 *
 * @param  array{customer_name?: string|null, project_name?: string|null, item_name?: string|null}  $labels
 */
function mockQboEntryDisplayNames(array $labels = [
    'customer_name' => null,
    'project_name' => null,
    'item_name' => null,
]): void
{
    test()->mock(QboPickerDisplayNameService::class, function ($mock) use ($labels) {
        $mock->shouldReceive('entryDisplayNames')->andReturn([
            'customer_name' => $labels['customer_name'] ?? null,
            'project_name' => $labels['project_name'] ?? null,
            'item_name' => $labels['item_name'] ?? null,
        ]);
        $mock->shouldReceive('employeeDisplayName')->andReturn('Jane Doe');
        $mock->shouldReceive('assignedCustomerRows')->zeroOrMoreTimes()->andReturn([]);
        $mock->shouldReceive('sessionDisplayNames')->zeroOrMoreTimes()->andReturn([
            'customer_name' => null,
            'project_name' => null,
            'service_name' => null,
        ]);
    });
}

/**
 * Mocks Chart of Accounts and vendor picker lists for expense feature tests.
 *
 * @param  array{
 *     payment?: array<int, array{id: string, display_name: string, account_type?: string}>,
 *     expense?: array<int, array{id: string, display_name: string, account_type?: string}>,
 *     vendors?: array<int, array{id: string, display_name: string}>,
 *     customers?: array<int, array{id: string, display_name: string}>,
 *     projects?: array<int, array{id: string, display_name: string}>
 * }  $options
 */
function mockQboExpensePickers(array $options = []): void
{
    $payment = $options['payment'] ?? [[
        'id' => '35',
        'display_name' => 'Checking',
        'account_type' => 'Bank',
    ]];
    $expense = $options['expense'] ?? [[
        'id' => '7',
        'display_name' => 'Office Expenses',
        'account_type' => 'Expense',
    ]];
    $vendors = $options['vendors'] ?? [[
        'id' => '56',
        'display_name' => 'Office Depot',
    ]];
    $customers = $options['customers'] ?? [[
        'id' => '11',
        'display_name' => 'Acme Corp',
    ]];
    $projects = $options['projects'] ?? [];

    test()->mock(QboAccountListService::class, function ($mock) use ($payment, $expense): void {
        $all = array_merge($payment, $expense);
        $mock->shouldReceive('listPaymentAccounts')->andReturn($payment);
        $mock->shouldReceive('listExpenseAccounts')->andReturn($expense);
        $mock->shouldReceive('listAll')->andReturn($all);
    });

    test()->mock(QboVendorListService::class, function ($mock) use ($vendors): void {
        $mock->shouldReceive('listActive')->andReturn($vendors);
    });

    test()->mock(QboCustomerListService::class, function ($mock) use ($customers): void {
        $mock->shouldReceive('listForUser')->andReturn($customers);
        $mock->shouldReceive('listAllActive')->andReturn($customers);
    });

    test()->mock(QboProjectListService::class, function ($mock) use ($projects): void {
        $mock->shouldReceive('listForCustomer')->andReturn($projects);
    });
}

/**
 * Builds a webhook processor with optional mocked collaborators.
 *
 * @param  TimeActivitySyncService  $sync  QuickBooks sync collaborator.
 * @param  TimeActivitySnapshotService|null  $snapshots  Snapshot persistence collaborator.
 * @return QuickBooksWebhookProcessorService
 */
function makeQuickBooksWebhookProcessor(
    TimeActivitySyncService $sync,
    ?TimeActivitySnapshotService $snapshots = null,
): QuickBooksWebhookProcessorService {
    return new QuickBooksWebhookProcessorService(
        $sync,
        $snapshots ?? app(TimeActivitySnapshotService::class),
        app(QuickBooksWebhookIdempotencyService::class),
    );
}

/**
 * Builds a QuickBooks-shaped time activity entity for mocks and sync stubs.
 *
 * @param  array<string, mixed>  $overrides
 */
function timeActivityQboEntity(string $id, string $employeeRef = '7', array $overrides = []): object
{
    return (object) array_merge([
        'Id' => $id,
        'SyncToken' => '0',
        'EmployeeRef' => (object) ['value' => $employeeRef],
        'StartTime' => '2026-07-29T09:00:00',
        'EndTime' => '2026-07-29T10:00:00',
        'TxnDate' => '2026-07-29',
    ], $overrides);
}

/**
 * Seeds snapshot rows for list endpoint tests without calling QuickBooks.
 */
function seedListedTimeActivities(QuickBooksToken $token, string $employeeRef, int $count, int $startId = 1): void
{
    for ($index = 0; $index < $count; $index++) {
        $id = (string) ($startId + $index);
        $start = now()->subHours($count - $index);

        TimeActivitySnapshot::factory()
            ->forRealm($token->realm_id)
            ->forEmployee($employeeRef)
            ->create([
                'qbo_id' => $id,
                'start_time' => $start,
                'end_time' => $start->copy()->addHour(),
                'txn_date' => $start->toDateString(),
            ]);
    }
}

/**
 * Returns a token with pre-seeded snapshots so list reads skip reconcile.
 */
function quickBooksTokenWithListedActivities(User $user, string $employeeRef = '7', int $count = 1): QuickBooksToken
{
    $token = QuickBooksToken::factory()->forUser($user)->create();
    seedListedTimeActivities($token, $employeeRef, $count);

    return $token;
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
