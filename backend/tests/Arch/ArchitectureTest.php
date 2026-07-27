<?php

arch('controllers')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller');

arch('models')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('services')
    ->expect('App\Services')
    ->toHaveSuffix('Service');

arch('no debugging helpers')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('controllers do not import qbo sdk')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'QuickBooksOnline\API\DataService\DataService',
        'QuickBooksOnline\API\Facades\TimeActivity',
        'QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper',
    ]);

arch('http layer delegates qbo data service to services')
    ->expect('App\Http')
    ->not->toUse('QuickBooksOnline\API\DataService\DataService');
