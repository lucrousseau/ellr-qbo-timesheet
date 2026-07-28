<?php

use App\Support\PasswordPolicy;
use App\Support\PasswordRules;
use Illuminate\Support\Facades\Validator;

covers(PasswordPolicy::class);
covers(PasswordRules::class);

it('loads the shared password policy configuration', function () {
    $config = PasswordPolicy::config();

    expect($config['minLength'])->toBe(12)
        ->and($config['requireUppercase'])->toBeTrue()
        ->and($config['requireLowercase'])->toBeTrue()
        ->and($config['requireNumbers'])->toBeTrue()
        ->and($config['requireSymbols'])->toBeTrue()
        ->and($config['uncompromised'])->toBeTrue();
});

it('resolves a readable test passwords path', function () {
    expect(PasswordPolicy::testPasswordsPath())->toContain('test-passwords.json')
        ->and(is_readable(PasswordPolicy::testPasswordsPath()))->toBeTrue();
});

it('resolves a readable password policy path', function () {
    expect(PasswordPolicy::configPath())->toContain('password-policy.json')
        ->and(is_readable(PasswordPolicy::configPath()))->toBeTrue();
});

it('falls back to the monorepo package policy file when backend config is missing', function () {
    withPasswordPolicyCandidates([
        base_path('config/password-policy-missing.json'),
        base_path('../packages/password-policy/password-policy.json'),
    ], function () {
        expect(PasswordPolicy::configPath())->toContain('packages/password-policy')
            ->and(PasswordPolicy::config()['minLength'])->toBe(12);
    });
});

it('throws when no password policy file is available', function () {
    withPasswordPolicyCandidates([
        base_path('config/password-policy-missing.json'),
        base_path('../packages/password-policy/password-policy-missing.json'),
    ], function () {
        expect(fn () => PasswordPolicy::configPath())
            ->toThrow(RuntimeException::class, 'Password policy file not found');
    });
});

it('throws when a test policy override path is not readable', function () {
    withPasswordPolicyEnvOverride('PASSWORD_POLICY_CONFIG_PATH', '/tmp/ellr-missing-password-policy.json', function () {
        expect(fn () => PasswordPolicy::configPath())
            ->toThrow(RuntimeException::class, 'not readable');
    });
});

it('exposes test passwords from the dedicated json file', function () {
    expect(PasswordPolicy::validTestPassword())->toBe('EllrT3st!2026')
        ->and(PasswordPolicy::validTestPasswordAlt())->toBe('EllrNew!2026');
});

it('uses default test passwords when the test passwords file is missing keys', function () {
    withTestPasswords([], function () {
        expect(PasswordPolicy::validTestPassword())->toBe('EllrT3st!2026')
            ->and(PasswordPolicy::validTestPasswordAlt())->toBe('EllrNew!2026');
    });
});

it('casts numeric test password values to strings', function () {
    withTestPasswords([
        'primary' => 123456789,
        'alternate' => 987654321,
    ], function () {
        expect(PasswordPolicy::validTestPassword())->toBe('123456789')
            ->and(PasswordPolicy::validTestPasswordAlt())->toBe('987654321');
    });
});

it('falls back to the monorepo test passwords file when backend config is missing', function () {
    withTestPasswordsCandidates([
        base_path('config/test-passwords-missing.json'),
        base_path('../packages/password-policy/test-passwords.json'),
    ], function () {
        expect(PasswordPolicy::testPasswordsPath())->toContain('packages/password-policy')
            ->and(PasswordPolicy::validTestPassword())->toBe('EllrT3st!2026');
    });
});

it('throws when no test passwords file is available', function () {
    withTestPasswordsCandidates([
        base_path('config/test-passwords-missing.json'),
        base_path('../packages/password-policy/test-passwords-missing.json'),
    ], function () {
        expect(fn () => PasswordPolicy::testPasswordsPath())
            ->toThrow(RuntimeException::class, 'Test passwords file not found');
    });
});

it('applies json defaults when policy keys are omitted', function () {
    withPasswordPolicy([], function () {
        $config = PasswordPolicy::config();

        expect($config['minLength'])->toBe(12)
            ->and($config['requireUppercase'])->toBeTrue()
            ->and($config['requireLowercase'])->toBeTrue()
            ->and($config['requireNumbers'])->toBeTrue()
            ->and($config['requireSymbols'])->toBeTrue()
            ->and($config['uncompromised'])->toBeTrue();
    });
});

it('returns defaults when test passwords json root is not an object', function () {
    withTestPasswordsContents('"invalid"', function () {
        expect(PasswordPolicy::validTestPassword())->toBe('EllrT3st!2026');
    });
});

it('accepts passwords that satisfy the shared policy', function () {
    $validator = Validator::make(
        [
            'password' => PasswordPolicy::validTestPassword(),
            'password_confirmation' => PasswordPolicy::validTestPassword(),
        ],
        ['password' => PasswordRules::newPassword()],
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects weak passwords through the shared policy rule', function () {
    $validator = Validator::make(
        [
            'password' => 'password',
            'password_confirmation' => 'password',
        ],
        ['password' => PasswordRules::newPassword()],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('password'))->toBeTrue();
});

it('rejects passwords missing required character classes', function () {
    $cases = [
        'alllowercase1!xx',
        'ALLUPPERCASE1!XX',
        'NoNumbersHere!X',
        'NoSymbols12345X',
    ];

    foreach ($cases as $password) {
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $password],
            ['password' => PasswordRules::newPassword()],
        );

        expect($validator->fails())->toBeTrue();
    }
});

it('rejects unconfirmed passwords', function () {
    $validator = Validator::make(
        [
            'password' => PasswordPolicy::validTestPassword(),
            'password_confirmation' => 'Different!2026',
        ],
        ['password' => PasswordRules::newPassword()],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('password'))->toBeTrue();
});

it('enforces mixed case when only lowercase is required', function () {
    withPasswordPolicy([
        'minLength' => 12,
        'requireUppercase' => false,
        'requireLowercase' => true,
        'requireNumbers' => true,
        'requireSymbols' => true,
        'uncompromised' => false,
    ], function () {
        $validator = Validator::make(
            [
                'password' => 'alllowercase1!',
                'password_confirmation' => 'alllowercase1!',
            ],
            ['password' => PasswordRules::newPassword()],
        );

        expect($validator->fails())->toBeTrue();
    });
});

it('allows passwords without numbers when numbers are disabled', function () {
    withPasswordPolicy([
        'minLength' => 12,
        'requireUppercase' => true,
        'requireLowercase' => true,
        'requireNumbers' => false,
        'requireSymbols' => true,
        'uncompromised' => false,
    ], function () {
        $validator = Validator::make(
            [
                'password' => 'NoNumbersHere!',
                'password_confirmation' => 'NoNumbersHere!',
            ],
            ['password' => PasswordRules::newPassword()],
        );

        expect($validator->passes())->toBeTrue();
    });
});

it('allows passwords without symbols when symbols are disabled', function () {
    withPasswordPolicy([
        'minLength' => 12,
        'requireUppercase' => true,
        'requireLowercase' => true,
        'requireNumbers' => true,
        'requireSymbols' => false,
        'uncompromised' => false,
    ], function () {
        $validator = Validator::make(
            [
                'password' => 'NoSymbols12345',
                'password_confirmation' => 'NoSymbols12345',
            ],
            ['password' => PasswordRules::newPassword()],
        );

        expect($validator->passes())->toBeTrue();
    });
});

it('skips mixed case when neither uppercase nor lowercase is required', function () {
    withPasswordPolicy([
        'minLength' => 12,
        'requireUppercase' => false,
        'requireLowercase' => false,
        'requireNumbers' => true,
        'requireSymbols' => true,
        'uncompromised' => false,
    ], function () {
        $validator = Validator::make(
            [
                'password' => 'alllowercase1!',
                'password_confirmation' => 'alllowercase1!',
            ],
            ['password' => PasswordRules::newPassword()],
        );

        expect($validator->passes())->toBeTrue();
    });
});

it('skips uncompromised validation when disabled in policy', function () {
    withPasswordPolicy([
        'minLength' => 12,
        'requireUppercase' => true,
        'requireLowercase' => true,
        'requireNumbers' => true,
        'requireSymbols' => true,
        'uncompromised' => false,
    ], function () {
        $validator = Validator::make(
            [
                'password' => 'Password12345!',
                'password_confirmation' => 'Password12345!',
            ],
            ['password' => PasswordRules::newPassword()],
        );

        expect($validator->passes())->toBeTrue();
    });
});

it('honors a custom minimum length from policy json', function () {
    withPasswordPolicy([
        'minLength' => 16,
        'requireUppercase' => false,
        'requireLowercase' => false,
        'requireNumbers' => false,
        'requireSymbols' => false,
        'uncompromised' => false,
    ], function () {
        $short = Validator::make(
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => PasswordRules::newPassword()],
        );
        $long = Validator::make(
            ['password' => 'sixteencharslong', 'password_confirmation' => 'sixteencharslong'],
            ['password' => PasswordRules::newPassword()],
        );

        expect($short->fails())->toBeTrue()
            ->and($long->passes())->toBeTrue();
    });
});
