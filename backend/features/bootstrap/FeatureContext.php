<?php

namespace Features\Bootstrap;

use App\Models\User;
use Behat\Behat\Context\Context;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Assert;

class FeatureContext implements Context
{
    private static ?Application $app = null;

    private ?TestResponse $response = null;

    /** @BeforeScenario */
    public function setUpBehat(): void
    {
        $this->bootApplication();
        $this->migrateDatabase();
        $this->response = null;
    }

    /** @AfterScenario */
    public function tearDownBehat(): void
    {
        $this->response = null;
    }

    /** @Given an authenticated API user */
    public function anAuthenticatedApiUser(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    /** @Given I am logged out */
    public function iAmLoggedOut(): void
    {
        auth()->forgetGuards();
    }

    /** @When I request :method :path with JSON: */
    public function iRequestWithJson(string $method, string $path, string $body): void
    {
        $kernel = self::$app->make(HttpKernel::class);

        $request = Request::create($path, strtoupper($method), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $this->response = TestResponse::fromBaseResponse($kernel->handle($request));
    }

    /** @When I request :method :path */
    public function iRequest(string $method, string $path): void
    {
        $kernel = self::$app->make(HttpKernel::class);

        $request = Request::create($path, strtoupper($method), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->response = TestResponse::fromBaseResponse($kernel->handle($request));
    }

    /** @Then the response status should be :status */
    public function theResponseStatusShouldBe(int $status): void
    {
        Assert::assertNotNull($this->response);
        $this->response->assertStatus($status);
    }

    /** @Then the JSON field :field should be :value */
    public function theJsonFieldShouldBe(string $field, string $value): void
    {
        Assert::assertNotNull($this->response);

        $decoded = match ($value) {
            'true' => true,
            'false' => false,
            default => $value,
        };

        $this->response->assertJsonPath($field, $decoded);
    }

    private function bootApplication(): void
    {
        if (self::$app !== null) {
            return;
        }

        self::$app = require __DIR__.'/../../bootstrap/app.php';
        self::$app->make(ConsoleKernel::class)->bootstrap();
    }

    private function migrateDatabase(): void
    {
        $this->bootApplication();

        self::$app['config']->set('database.default', 'sqlite');
        self::$app['config']->set('database.connections.sqlite.database', ':memory:');

        self::$app->make('db')->purge('sqlite');
        self::$app->make('db')->reconnect('sqlite');

        self::$app->make(ConsoleKernel::class)->call('migrate:fresh', ['--force' => true]);
    }
}
