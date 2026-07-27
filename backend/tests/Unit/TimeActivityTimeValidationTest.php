<?php

use App\Support\TimeActivityTimeValidation;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TimeActivityTimeValidation::class);

it('allows end time after start time', function () {
    expect(TimeActivityTimeValidation::isEndAfterStart(
        '2026-07-27T09:00:00',
        '2026-07-27T17:00:00',
    ))->toBeTrue();
});

it('rejects equal start and end times', function () {
    expect(TimeActivityTimeValidation::isEndAfterStart(
        '2026-07-27T09:00:00',
        '2026-07-27T09:00:00',
    ))->toBeFalse();
});

it('rejects end time before start time', function () {
    expect(TimeActivityTimeValidation::isEndAfterStart(
        '2026-07-27T17:00:00',
        '2026-07-27T09:00:00',
    ))->toBeFalse();
});

it('skips validation when either time is missing', function () {
    expect(TimeActivityTimeValidation::isEndAfterStart(null, '2026-07-27T09:00:00'))->toBeTrue()
        ->and(TimeActivityTimeValidation::isEndAfterStart('2026-07-27T09:00:00', null))->toBeTrue();
});

it('normalizes sdk time values to strings', function () {
    $sdkTime = new class
    {
        public function __toString(): string
        {
            return '2026-07-27T09:00:00';
        }
    };

    expect(TimeActivityTimeValidation::normalizeTime($sdkTime))->toBe('2026-07-27T09:00:00')
        ->and(TimeActivityTimeValidation::normalizeTime(null))->toBeNull();
});

it('skips incomplete checks when both resolved times are present', function () {
    TimeActivityTimeValidation::abortWhenUpdateTimesIncomplete(
        true,
        '2026-07-27T09:00:00',
        '2026-07-27T17:00:00',
    );

    expect(true)->toBeTrue();
});

it('aborts when partial time updates cannot resolve both fields', function () {
    try {
        TimeActivityTimeValidation::abortWhenUpdateTimesIncomplete(true, '2026-07-27T09:00:00', null);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => TimeActivityTimeValidation::INCOMPLETE_TIME_FIELDS_MESSAGE,
                'errors' => [
                    'start_time' => [TimeActivityTimeValidation::INCOMPLETE_TIME_FIELDS_MESSAGE],
                    'end_time' => [TimeActivityTimeValidation::INCOMPLETE_TIME_FIELDS_MESSAGE],
                ],
            ]);
    }
});

it('skips incomplete checks when time fields are not being updated', function () {
    TimeActivityTimeValidation::abortWhenUpdateTimesIncomplete(false, null, '2026-07-27T09:00:00');

    expect(true)->toBeTrue();
});

it('aborts with a validation response when end time is invalid', function () {
    try {
        TimeActivityTimeValidation::abortUnlessEndAfterStart(
            '2026-07-27T17:00:00',
            '2026-07-27T09:00:00',
        );
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => TimeActivityTimeValidation::END_AFTER_START_MESSAGE,
                'errors' => [
                    'end_time' => [TimeActivityTimeValidation::END_AFTER_START_MESSAGE],
                ],
            ]);
    }
});
