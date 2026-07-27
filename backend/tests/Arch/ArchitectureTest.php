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
