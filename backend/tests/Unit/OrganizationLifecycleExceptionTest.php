<?php

use App\Exceptions\OrganizationLifecycleException;

covers(OrganizationLifecycleException::class);

it('stores the stable api reason code', function () {
    $exception = new OrganizationLifecycleException(
        'You cannot delete the organization that owns your account.',
        'cannot_delete_own_organization',
    );

    expect($exception->getMessage())->toBe('You cannot delete the organization that owns your account.')
        ->and($exception->reason)->toBe('cannot_delete_own_organization');
});
