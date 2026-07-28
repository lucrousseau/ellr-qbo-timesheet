<?php

use App\Support\PasswordPolicy;
use App\Support\PasswordRules;
use Illuminate\Support\Facades\Validator;

covers(PasswordPolicy::class);

it('loads the shared password policy configuration', function () {
    $config = PasswordPolicy::config();

    expect($config['minLength'])->toBe(12)
        ->and($config['requireUppercase'])->toBeTrue()
        ->and($config['requireLowercase'])->toBeTrue()
        ->and($config['requireNumbers'])->toBeTrue()
        ->and($config['requireSymbols'])->toBeTrue()
        ->and($config['uncompromised'])->toBeTrue()
        ->and($config['testPasswords']['primary'])->toBe('EllrT3st!2026')
        ->and($config['testPasswords']['alternate'])->toBe('EllrNew!2026');
});

it('resolves a readable password policy path', function () {
    expect(PasswordPolicy::configPath())->toContain('password-policy.json')
        ->and(is_readable(PasswordPolicy::configPath()))->toBeTrue();
});

it('exposes test passwords from the shared json', function () {
    expect(PasswordPolicy::validTestPassword())->toBe('EllrT3st!2026')
        ->and(PasswordPolicy::validTestPasswordAlt())->toBe('EllrNew!2026');
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
