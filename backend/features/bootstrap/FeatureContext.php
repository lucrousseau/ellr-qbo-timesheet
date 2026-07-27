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
    }

    /**
     * @AfterScenario
     * @return void
     */
    public function tearDownBehat(): void
    {
        $this->response = null;
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
        $kernel = self::$app->make(HttpKernel::class);

        $request = Request::create($path, strtoupper($method), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $this->response = TestResponse::fromBaseResponse($kernel->handle($request));
    }

    /**
     * @When I request :method :path
     * @param  string  $method  HTTP method.
     * @param  string  $path  Request path.
     * @return void
     */
    public function iRequest(string $method, string $path): void
    {
        $kernel = self::$app->make(HttpKernel::class);

        $request = Request::create($path, strtoupper($method), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->response = TestResponse::fromBaseResponse($kernel->handle($request));
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

        self::$app->make('db')->purge('sqlite');
        self::$app->make('db')->reconnect('sqlite');

        self::$app->make(ConsoleKernel::class)->call('migrate:fresh', ['--force' => true]);
    }
}
