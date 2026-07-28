<?php

/**
 * Behat context that boots Laravel in memory and drives API HTTP scenarios.
 */

namespace Features\Bootstrap;

use App\Models\User;
use Behat\Behat\Context\Context;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
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

    /**
     * @BeforeScenario
     * @return void
     */
    public function setUpBehat(): void
    {
        $this->bootApplication();
        $this->migrateDatabase();
        auth()->forgetGuards();
        $this->response = null;
        $this->cookies = [];
        $this->statefulClient = false;

        if (self::$app?->bound('session.store')) {
            self::$app->make('session.store')->flush();
        }
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

        if (self::$app?->bound('session.store')) {
            self::$app->make('session.store')->flush();
        }
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
     * @Given another user is mapped to quickbooks employee :ref
     * @param  string  $ref  QuickBooks employee reference.
     * @return void
     */
    public function anotherUserIsMappedToQuickbooksEmployee(string $ref): void
    {
        User::factory()->create(['qbo_employee_ref' => $ref]);
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
     * Dispatches an HTTP request through the kernel and keeps cookies for stateful flows.
     *
     * @param  string  $method  HTTP method.
     * @param  string  $path  Request path.
     * @param  string|null  $body  Optional JSON request body.
     * @return void
     */
    private function dispatchRequest(string $method, string $path, ?string $body = null): void
    {
        $server = $this->requestServerHeaders($method, $body !== null);

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
