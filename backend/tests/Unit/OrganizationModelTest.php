<?php

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(Organization::class);

uses(RefreshDatabase::class);

it('reports whether a quickbooks realm is linked', function () {
    $connected = Organization::factory()->withRealm('realm-1')->create();
    $disconnected = Organization::factory()->create(['realm_id' => null]);
    $emptyRealm = Organization::factory()->create(['realm_id' => '']);

    expect($connected->hasQuickBooksRealm())->toBeTrue()
        ->and($disconnected->hasQuickBooksRealm())->toBeFalse()
        ->and($emptyRealm->hasQuickBooksRealm())->toBeFalse();
});
