<?php

/**
 * Guards dev stack configuration files against accidental Docker and frontend regressions.
 */
it('keeps docker compose aligned with the sqlite dev stack', function () {
    $composePath = dirname(base_path()).'/docker-compose.yml';
    $compose = file_get_contents($composePath);

    expect($compose)->toBeString()
        ->and($compose)->not->toContain('DB_CONNECTION: mysql')
        ->and($compose)->not->toContain('mysql_data:')
        ->and($compose)->not->toMatch('/^\s+mysql:\s*$/m')
        ->and($compose)->toContain('DEV_SEED_ENABLED: "true"')
        ->and($compose)->toContain('VITE_API_URL: /api')
        ->and($compose)->toContain('VITE_API_PROXY_TARGET: http://api:8000');
});

it('documents dev seed opt-in in backend env example', function () {
    $backendExample = file_get_contents(base_path('.env.example'));

    expect($backendExample)->toContain('DEV_SEED_ENABLED=true')
        ->and($backendExample)->toContain('DEV_SEED_TENANT_ADMIN_EMAIL')
        ->and($backendExample)->toContain('DEV_SEED_PLATFORM_EMAIL')
        ->and($backendExample)->toContain('DB_CONNECTION=sqlite');
});
