<?php

/**
 * Tests for approval payload validation and grouping default.
 */

use App\Http\Requests\ApproveTimeEntryRequest;
use Illuminate\Support\Facades\Validator;

covers(ApproveTimeEntryRequest::class);

it('defaults grouping to false when the field is omitted', function () {
    $request = ApproveTimeEntryRequest::create('/api/time-entry-approvals/1/approve', 'POST', []);

    expect($request->groupForQbo())->toBeFalse();
});

it('accepts an explicit grouping choice', function () {
    $request = ApproveTimeEntryRequest::create('/api/time-entry-approvals/1/approve', 'POST', [
        'group_for_qbo' => true,
    ]);

    expect($request->groupForQbo())->toBeTrue();
});

it('rejects non-boolean grouping values', function () {
    $request = new ApproveTimeEntryRequest;
    $validator = Validator::make(
        ['group_for_qbo' => 'maybe'],
        $request->rules(),
    );

    expect($validator->fails())->toBeTrue();
});
