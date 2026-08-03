<?php

/**
 * Resolves filesystem paths for shared password policy JSON files.
 */

namespace App\Support;

use RuntimeException;

/**
 * Locates password-policy.json and test-passwords.json with test-only env overrides.
 */
class PasswordPolicyPaths
{
    /**
     * Resolves the absolute path to password-policy.json.
     *
     * @return string
     */
    public static function policyConfig(): string
    {
        $override = self::configuredPath('PASSWORD_POLICY_CONFIG_PATH');
        if ($override !== null) {
            return $override;
        }

        return self::resolveFirstReadablePath(
            self::configuredPathCandidates(
                'PASSWORD_POLICY_CONFIG_CANDIDATES',
                [
                    base_path('config/password-policy.json'),
                    base_path('../packages/password-policy/password-policy.json'), // @pest-mutate-ignore monorepo package fallback path
                ],
            ),
            'Password policy file not found. Run scripts/sync-password-policy.sh or mount packages/password-policy.',
        );
    }

    /**
     * Resolves the absolute path to test-passwords.json.
     *
     * @return string
     */
    public static function testPasswords(): string
    {
        $override = self::configuredPath('PASSWORD_TEST_PASSWORDS_PATH');
        if ($override !== null) {
            return $override;
        }

        return self::resolveFirstReadablePath(
            self::configuredPathCandidates(
                'PASSWORD_TEST_PASSWORDS_CANDIDATES',
                [
                    base_path('config/test-passwords.json'),
                    base_path('../packages/password-policy/test-passwords.json'), // @pest-mutate-ignore monorepo package fallback path
                ],
            ),
            'Test passwords file not found. Run scripts/sync-password-policy.sh or mount packages/password-policy.',
        );
    }

    /**
     * Returns the first readable path from the candidate list.
     *
     * @param  list<string>  $candidates  Paths to try in order.
     * @param  string  $notFoundMessage  Error message when no candidate is readable.
     * @return string
     */
    private static function resolveFirstReadablePath(array $candidates, string $notFoundMessage): string
    {
        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate) ?: $candidate;

            if (is_readable($resolved)) {
                return $resolved;
            }
        }

        throw new RuntimeException($notFoundMessage);
    }

    /**
     * @param  string  $envKey  Environment variable holding a JSON array of candidate paths.
     * @param  list<string>  $defaults  Fallback paths when the env override is absent or invalid.
     * @return list<string>
     */
    private static function configuredPathCandidates(string $envKey, array $defaults): array
    {
        if (! self::allowsTestOverrides()) {
            return $defaults;
        }

        $value = self::configuredEnv($envKey);
        if ($value === null) {
            return $defaults;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        return array_values(array_map(strval(...), $decoded));
    }

    /**
     * Reads a test-only config path override from the environment.
     *
     * @param  string  $envKey  Environment variable name.
     * @return string|null
     */
    private static function configuredPath(string $envKey): ?string
    {
        if (! self::allowsTestOverrides()) {
            return null;
        }

        $value = self::configuredEnv($envKey);
        if ($value === null) {
            return null;
        }

        if (! is_readable($value)) {
            throw new RuntimeException("Password policy override file not readable at [{$value}].");
        }

        return $value;
    }

    /**
     * @param  string  $envKey  Environment variable name.
     * @return string|null
     */
    private static function configuredEnv(string $envKey): ?string
    {
        if (! self::allowsTestOverrides()) {
            return null;
        }

        $value = $_ENV[$envKey] ?? getenv($envKey);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Whether PASSWORD_* env overrides are honored (testing environment only).
     *
     * @return bool
     */
    private static function allowsTestOverrides(): bool
    {
        if (! function_exists('app')) {
            return false;
        }

        return app()->environment('testing');
    }
}
