<?php

/**
 * Behat context that boots Laravel in memory and drives API HTTP scenarios.
 */

namespace Features\Bootstrap;

use App\Models\Expense;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\QboCustomerListService;
use Behat\Behat\Context\Context;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;

/**
 * Behat context for API scenarios (in-memory Laravel app + HTTP assertions).
 */
class FeatureContext implements Context
{
    private static ?Application $app = null;

    private ?TestResponse $response = null;

    /** @var array<string, string> */
    private array $cookies = [];

    private bool $statefulClient = false;

    private ?int $timesheetUserId = null;

    private ?int $foreignTimesheetUserId = null;

    private ?int $pendingTimeEntryId = null;

    private ?int $foreignPendingTimeEntryId = null;

    private ?int $pendingExpenseId = null;

    private ?int $foreignPendingExpenseId = null;

    private ?string $webhookVerifier = null;

    /**
     * @BeforeScenario
     * @return void
     */
    public function setUpBehat(): void
    {
        $this->bootApplication();
        $this->migrateDatabase();
        $this->resetAuthenticationState();
        $this->response = null;
        $this->cookies = [];
        $this->statefulClient = false;
        $this->timesheetUserId = null;
        $this->foreignTimesheetUserId = null;
        $this->pendingTimeEntryId = null;
        $this->foreignPendingTimeEntryId = null;
        $this->pendingExpenseId = null;
        $this->foreignPendingExpenseId = null;
        $this->webhookVerifier = null;
    }

    /**
     * @AfterScenario
     * @return void
     */
    public function tearDownBehat(): void
    {
        $this->response = null;
        $this->cookies = [];
        $this->statefulClient = false;
        $this->timesheetUserId = null;
        $this->foreignTimesheetUserId = null;
        $this->pendingTimeEntryId = null;
        $this->foreignPendingTimeEntryId = null;
        $this->pendingExpenseId = null;
        $this->foreignPendingExpenseId = null;
        $this->webhookVerifier = null;
        $this->resetAuthenticationState();
        self::$app = null;
    }

    /**
     * @Given an authenticated API user
     * @return void
     */
    public function anAuthenticatedApiUser(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    /**
     * @Given quickbooks is connected for the authenticated user
     * @return void
     */
    public function quickbooksIsConnectedForTheAuthenticatedUser(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new RuntimeException('No authenticated user.');
        }

        QuickBooksToken::factory()->forUser($user)->create();
    }

    /**
     * @Given quickbooks customer :ref is named :name in the active customer list
     * @param  string  $ref  QuickBooks customer reference.
     * @param  string  $name  QuickBooks customer display name.
     * @return void
     */
    public function quickbooksCustomerIsNamedInTheActiveCustomerList(string $ref, string $name): void
    {
        $mock = Mockery::mock(QboCustomerListService::class);
        $row = ['id' => $ref, 'display_name' => $name];
        $mock->shouldReceive('listForUser')->andReturn([$row]);
        $mock->shouldReceive('listAllActive')->andReturn([$row]);

        self::$app?->instance(QboCustomerListService::class, $mock);
    }

    /**
     * @Given an authenticated platform super administrator
     * @return void
     */
    public function anAuthenticatedPlatformSuperAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
    }

    /**
     * @Given I am logged out
     * @return void
     */
    public function iAmLoggedOut(): void
    {
        auth()->forgetGuards();
        $this->cookies = [];

        if (self::$app->bound('session.store')) {
            self::$app->make('session.store')->flush();
        }
    }

    /**
     * @Given I use the stateful SPA client
     * @return void
     */
    public function iUseTheStatefulSpaClient(): void
    {
        $this->statefulClient = true;
        $this->cookies = [];
    }

    /**
     * @Given a verified user exists with email :email and password :password
     * @param  string  $email  Account email.
     * @param  string  $password  Plain-text password.
     * @return void
     */
    public function aVerifiedUserExistsWithEmailAndPassword(string $email, string $password): void
    {
        User::factory()->create([
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @Given a verified user exists with email :email, password :password, and locale :locale
     * @param  string  $email  Account email.
     * @param  string  $password  Plain-text password.
     * @param  string  $locale  Preferred locale code.
     * @return void
     */
    public function aVerifiedUserExistsWithEmailPasswordAndLocale(
        string $email,
        string $password,
        string $locale,
    ): void {
        User::factory()->create([
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'locale' => $locale,
        ]);
    }

    /**
     * @Given another user is mapped to quickbooks employee :ref
     * @param  string  $ref  QuickBooks employee reference.
     * @return void
     */
    public function anotherUserIsMappedToQuickbooksEmployee(string $ref): void
    {
        $attributes = ['qbo_employee_ref' => $ref];
        $actor = auth()->user();

        if ($actor instanceof User) {
            $attributes['organization_id'] = $actor->organization_id;
        }

        User::factory()->create($attributes);
    }

    /**
     * @Given a timesheet user exists in another organization mapped to quickbooks employee :ref
     * @param  string  $ref  QuickBooks employee reference.
     * @return void
     */
    public function aTimesheetUserExistsInAnotherOrganizationMappedToQuickbooksEmployee(string $ref): void
    {
        $foreignOrganization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $foreignOrganization->id,
            'qbo_employee_ref' => $ref,
        ]);

        $this->foreignTimesheetUserId = $user->id;
    }

    /**
     * @Given a timesheet user is mapped to quickbooks employee :ref
     * @param  string  $ref  QuickBooks employee reference.
     * @return void
     */
    public function aTimesheetUserIsMappedToQuickbooksEmployee(string $ref): void
    {
        $attributes = [
            'qbo_employee_ref' => $ref,
        ];
        $actor = auth()->user();

        if ($actor instanceof User) {
            $attributes['organization_id'] = $actor->organization_id;
        }

        $user = User::factory()->create($attributes);

        $this->timesheetUserId = $user->id;
    }

    /**
     * @Given the timesheet user has a pending time entry
     * @return void
     */
    public function theTimesheetUserHasAPendingTimeEntry(): void
    {
        if ($this->timesheetUserId === null) {
            throw new RuntimeException('No timesheet user id was captured.');
        }

        $user = User::query()->findOrFail($this->timesheetUserId);
        $entry = TimeEntry::factory()->forUser($user)->create();
        $this->pendingTimeEntryId = $entry->id;
    }

    /**
     * @Given the foreign timesheet user has a pending time entry
     * @return void
     */
    public function theForeignTimesheetUserHasAPendingTimeEntry(): void
    {
        if ($this->foreignTimesheetUserId === null) {
            throw new RuntimeException('No foreign timesheet user id was captured.');
        }

        $user = User::query()->findOrFail($this->foreignTimesheetUserId);
        $entry = TimeEntry::factory()->forUser($user)->create();
        $this->foreignPendingTimeEntryId = $entry->id;
    }

    /**
     * @Given the timesheet user has a pending expense
     * @return void
     */
    public function theTimesheetUserHasAPendingExpense(): void
    {
        if ($this->timesheetUserId === null) {
            throw new RuntimeException('No timesheet user id was captured.');
        }

        $user = User::query()->findOrFail($this->timesheetUserId);
        $expense = Expense::factory()->forUser($user)->create();
        $this->pendingExpenseId = $expense->id;
    }

    /**
     * @Given the foreign timesheet user has a pending expense
     * @return void
     */
    public function theForeignTimesheetUserHasAPendingExpense(): void
    {
        if ($this->foreignTimesheetUserId === null) {
            throw new RuntimeException('No foreign timesheet user id was captured.');
        }

        $user = User::query()->findOrFail($this->foreignTimesheetUserId);
        $expense = Expense::factory()->forUser($user)->create();
        $this->foreignPendingExpenseId = $expense->id;
    }

    /**
     * @Given the timesheet user is assigned quickbooks customer :ref named :name
     * @param  string  $ref  QuickBooks customer reference.
     * @param  string  $name  QuickBooks customer display name.
     * @return void
     */
    public function theTimesheetUserIsAssignedQuickbooksCustomer(string $ref, string $name): void
    {
        if ($this->timesheetUserId === null) {
            throw new RuntimeException('No timesheet user id was captured.');
        }

        $user = User::query()->findOrFail($this->timesheetUserId);
        $user->forceFill(['qbo_all_customers_access' => false])->save();
        $user->qboCustomers()->create([
            'qbo_customer_ref' => $ref,
        ]);
    }

    /**
     * @Then the timesheet user should no longer exist
     * @return void
     */
    public function theTimesheetUserShouldNoLongerExist(): void
    {
        if ($this->timesheetUserId === null) {
            throw new RuntimeException('No timesheet user id was captured.');
        }

        if (User::query()->find($this->timesheetUserId) !== null) {
            throw new RuntimeException('Expected the timesheet user to be deleted.');
        }
    }

    /**
     * @When I fetch the sanctum csrf cookie
     * @return void
     */
    public function iFetchTheSanctumCsrfCookie(): void
    {
        $this->dispatchRequest('GET', '/sanctum/csrf-cookie');
    }

    /**
     * @When I request :method :path with JSON:
     * @param  string  $method  HTTP method.
     * @param  string  $path  Request path.
     * @param  string  $body  JSON request body.
     * @return void
     */
    public function iRequestWithJson(string $method, string $path, string $body): void
    {
        $this->dispatchRequest(strtoupper($method), $path, $body);
    }

    /**
     * @When I request :method :path
     * @param  string  $method  HTTP method.
     * @param  string  $path  Request path.
     * @return void
     */
    public function iRequest(string $method, string $path): void
    {
        $this->dispatchRequest(strtoupper($method), $path);
    }

    /**
     * @Given the quickbooks webhook verifier is :token
     * @param  string  $token  Intuit webhook verifier token.
     * @return void
     */
    public function theQuickbooksWebhookVerifierIs(string $token): void
    {
        config(['quickbooks.webhook_verifier' => $token]);
        $this->webhookVerifier = $token;
    }

    /**
     * @Given the quickbooks webhook max payload bytes is :bytes
     * @param  int  $bytes  Maximum raw webhook body size.
     * @return void
     */
    public function theQuickbooksWebhookMaxPayloadBytesIs(int $bytes): void
    {
        config(['quickbooks.webhook_max_payload_bytes' => $bytes]);
    }

    /**
     * @When I post a signed quickbooks webhook with JSON:
     * @param  string  $body  JSON request body.
     * @return void
     */
    public function iPostASignedQuickbooksWebhookWithJson(string $body): void
    {
        $verifier = $this->webhookVerifier ?? (string) config('quickbooks.webhook_verifier');

        if ($verifier === '') {
            throw new RuntimeException('QuickBooks webhook verifier is not configured.');
        }

        $signature = base64_encode(hash_hmac('sha256', $body, $verifier, true));

        $this->dispatchRequest('POST', '/api/quickbooks/webhook', $body, [
            'HTTP_INTUIT_SIGNATURE' => $signature,
        ]);
    }

    /**
     * @When I post an unsigned quickbooks webhook with JSON:
     * @param  string  $body  JSON request body.
     * @return void
     */
    public function iPostAnUnsignedQuickbooksWebhookWithJson(string $body): void
    {
        $this->dispatchRequest('POST', '/api/quickbooks/webhook', $body);
    }

    /**
     * @Then the response status should be :status
     * @param  int  $status  Expected HTTP status code.
     * @return void
     */
    public function theResponseStatusShouldBe(int $status): void
    {
        if ($this->response === null) {
            throw new RuntimeException('No HTTP response was captured.');
        }

        $actual = $this->response->getStatusCode();

        if ($actual !== $status) {
            throw new RuntimeException(
                "Expected HTTP status {$status}, received {$actual}. Body: ".$this->response->getContent(),
            );
        }
    }

    /**
     * @Then the JSON field :field should be :value
     * @param  string  $field  Dot-notated JSON field path.
     * @param  string  $value  Expected field value.
     * @return void
     */
    public function theJsonFieldShouldBe(string $field, string $value): void
    {
        if ($this->response === null) {
            throw new RuntimeException('No HTTP response was captured.');
        }

        $expected = match ($value) {
            'true' => true,
            'false' => false,
            '[]' => [],
            default => $value,
        };

        $actual = data_get($this->response->json(), $field);

        if ($actual !== $expected) {
            throw new RuntimeException(
                'Expected JSON field '.$field.' to be '.json_encode($expected).', received '.json_encode($actual).'.',
            );
        }
    }

    /**
     * @Then the JSON field :field should be the english api message :messageKey
     * @param  string  $field  Dot-notated JSON field path.
     * @param  string  $messageKey  Key under lang/{locale}/api.php.
     * @return void
     */
    public function theJsonFieldShouldBeTheEnglishApiMessage(string $field, string $messageKey): void
    {
        $this->assertJsonFieldMatchesApiMessage($field, $messageKey, 'en');
    }

    /**
     * @Then the JSON field :field should be the french api message :messageKey
     * @param  string  $field  Dot-notated JSON field path.
     * @param  string  $messageKey  Key under lang/{locale}/api.php.
     * @return void
     */
    public function theJsonFieldShouldBeTheFrenchApiMessage(string $field, string $messageKey): void
    {
        $this->assertJsonFieldMatchesApiMessage($field, $messageKey, 'fr');
    }

    /**
     * Asserts a JSON field equals a translated API message for the given locale.
     *
     * @param  string  $field  Dot-notated JSON field path.
     * @param  string  $messageKey  Key under lang/{locale}/api.php.
     * @param  string  $locale  Locale code.
     * @return void
     */
    private function assertJsonFieldMatchesApiMessage(string $field, string $messageKey, string $locale): void
    {
        if ($this->response === null) {
            throw new RuntimeException('No HTTP response was captured.');
        }

        $expected = __("api.{$messageKey}", [], $locale);
        $actual = data_get($this->response->json(), $field);

        if ($actual !== $expected) {
            throw new RuntimeException(
                'Expected JSON field '.$field.' to be '.json_encode($expected).', received '.json_encode($actual).'.',
            );
        }
    }

    /**
     * Resets guards and session state between scenarios so Sanctum does not leak.
     *
     * @return void
     */
    private function resetAuthenticationState(): void
    {
        App::setLocale((string) config('app.locale'));

        if (self::$app?->bound('session.store')) {
            self::$app->forgetInstance('session.store');
        }

        if (self::$app?->bound('auth')) {
            self::$app->forgetInstance('auth');
        }

        if (self::$app?->bound(HttpKernel::class)) {
            self::$app->forgetInstance(HttpKernel::class);
        }

        if (self::$app?->bound(QboCustomerListService::class)) {
            self::$app->forgetInstance(QboCustomerListService::class);
        }

        auth()->shouldUse((string) config('auth.defaults.guard'));
        auth()->forgetGuards();
    }

    /**
     * Dispatches an HTTP request through the kernel and keeps cookies for stateful flows.
     *
     * @param  string  $method  HTTP method.
     * @param  string  $path  Request path.
     * @param  string|null  $body  Optional JSON request body.
     * @param  array<string, string>  $extraHeaders  Additional server headers.
     * @return void
     */
    private function dispatchRequest(string $method, string $path, ?string $body = null, array $extraHeaders = []): void
    {
        if ($this->timesheetUserId !== null) {
            $path = str_replace('{timesheet_user_id}', (string) $this->timesheetUserId, $path);
        }

        if ($this->foreignTimesheetUserId !== null) {
            $path = str_replace('{foreign_timesheet_user_id}', (string) $this->foreignTimesheetUserId, $path);
        }

        if ($this->pendingTimeEntryId !== null) {
            $path = str_replace('{pending_time_entry_id}', (string) $this->pendingTimeEntryId, $path);
        }

        if ($this->foreignPendingTimeEntryId !== null) {
            $path = str_replace('{foreign_pending_time_entry_id}', (string) $this->foreignPendingTimeEntryId, $path);
        }

        if ($this->pendingExpenseId !== null) {
            $path = str_replace('{pending_expense_id}', (string) $this->pendingExpenseId, $path);
        }

        if ($this->foreignPendingExpenseId !== null) {
            $path = str_replace('{foreign_pending_expense_id}', (string) $this->foreignPendingExpenseId, $path);
        }

        if (str_contains($path, '{admin_user_id}')) {
            $user = auth()->user();

            if ($user === null) {
                throw new RuntimeException('No authenticated user is available for admin_user_id substitution.');
            }

            $path = str_replace('{admin_user_id}', (string) $user->id, $path);
        }

        $server = array_merge(
            $this->requestServerHeaders($method, $body !== null),
            $extraHeaders,
        );

        $request = Request::create($path, $method, [], $this->cookies, [], $server, $body);

        $kernel = self::$app->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $this->response = TestResponse::fromBaseResponse($response);

        foreach ($this->response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = $cookie->getValue();
        }
    }

    /**
     * Builds server headers for the next HTTP request.
     *
     * @param  string  $method  HTTP method.
     * @param  bool  $hasJsonBody  Whether the request carries a JSON body.
     * @return array<string, string>
     */
    private function requestServerHeaders(string $method, bool $hasJsonBody): array
    {
        $headers = [
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($hasJsonBody) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }

        if ($this->statefulClient) {
            $headers['HTTP_ORIGIN'] = 'http://localhost:5173';
            $headers['HTTP_REFERER'] = 'http://localhost:5173/';
        }

        if ($this->statefulClient && $this->isMutatingMethod($method) && isset($this->cookies['XSRF-TOKEN'])) {
            $headers['HTTP_X_XSRF_TOKEN'] = urldecode($this->cookies['XSRF-TOKEN']);
        }

        return $headers;
    }

    /**
     * @param  string  $method  HTTP method.
     * @return bool
     */
    private function isMutatingMethod(string $method): bool
    {
        return ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * Boots the Laravel application once per Behat process.
     *
     * @return void
     */
    private function bootApplication(): void
    {
        if (self::$app !== null) {
            return;
        }

        self::$app = require __DIR__.'/../../bootstrap/app.php';
        self::$app->make(ConsoleKernel::class)->bootstrap();
    }

    /**
     * Runs migrations against an in-memory SQLite database for isolation.
     *
     * @return void
     */
    private function migrateDatabase(): void
    {
        $this->bootApplication();

        self::$app['config']->set('database.default', 'sqlite');
        self::$app['config']->set('database.connections.sqlite.database', ':memory:');
        self::$app['config']->set('cache.default', 'array');
        self::$app['config']->set('session.driver', 'array');
        self::$app['config']->set('sanctum.stateful', [
            'localhost:5173',
            'localhost:5174',
            'localhost',
            '127.0.0.1',
        ]);

        self::$app->make('db')->purge('sqlite');
        self::$app->make('db')->reconnect('sqlite');

        self::$app->make(ConsoleKernel::class)->call('migrate:fresh', ['--force' => true]);
    }
}
