<?php

/**
 * Reads the shared monorepo password policy and builds Laravel validation rules.
 */

namespace App\Support;

use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Single source of truth for password strength (packages/password-policy/password-policy.json).
 */
class PasswordPolicy
{
    /**
     * Returns the primary strong password used in tests and factories.
     *
     * @return string
     */
    public static function validTestPassword(): string
    {
        $passwords = self::config()['testPasswords'] ?? [];

        return (string) ($passwords['primary'] ?? 'EllrT3st!2026');
    }

    /**
     * Returns an alternate strong password for change-password scenarios in tests.
     *
     * @return string
     */
    public static function validTestPasswordAlt(): string
    {
        $passwords = self::config()['testPasswords'] ?? [];

        return (string) ($passwords['alternate'] ?? 'EllrNew!2026');
    }

    /**
     * Returns the decoded password policy configuration.
     *
     * @return array{
     *     minLength: int,
     *     requireUppercase: bool,
     *     requireLowercase: bool,
     *     requireNumbers: bool,
     *     requireSymbols: bool,
     *     uncompromised: bool,
     *     testPasswords?: array{primary?: string, alternate?: string}
     * }
     */
    public static function config(): array
    {
        $path = self::configPath();

        if (! is_readable($path)) {
            throw new RuntimeException("Password policy file not found at [{$path}].");
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return [
            'minLength' => (int) ($decoded['minLength'] ?? 12),
            'requireUppercase' => (bool) ($decoded['requireUppercase'] ?? true),
            'requireLowercase' => (bool) ($decoded['requireLowercase'] ?? true),
            'requireNumbers' => (bool) ($decoded['requireNumbers'] ?? true),
            'requireSymbols' => (bool) ($decoded['requireSymbols'] ?? true),
            'uncompromised' => (bool) ($decoded['uncompromised'] ?? true),
            'testPasswords' => is_array($decoded['testPasswords'] ?? null)
                ? $decoded['testPasswords']
                : [],
        ];
    }

    /**
     * Builds the Laravel Password rule from the shared JSON policy.
     *
     * @return Password
     */
    public static function rule(): Password
    {
        $config = self::config();

        $rule = Password::min($config['minLength']);

        if ($config['requireUppercase'] || $config['requireLowercase']) {
            $rule = $rule->mixedCase();
        }

        if ($config['requireNumbers']) {
            $rule = $rule->numbers();
        }

        if ($config['requireSymbols']) {
            $rule = $rule->symbols();
        }

        if ($config['uncompromised']) {
            $rule = $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * Resolves the absolute path to password-policy.json.
     *
     * @return string
     */
    public static function configPath(): string
    {
        $candidates = [
            base_path('config/password-policy.json'),
            base_path('../packages/password-policy/password-policy.json'),
        ];

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate) ?: $candidate;

            if (is_readable($resolved)) {
                return $resolved;
            }
        }

        throw new RuntimeException(
            'Password policy file not found. Run scripts/sync-password-policy.sh or mount packages/password-policy.',
        );
    }
}
