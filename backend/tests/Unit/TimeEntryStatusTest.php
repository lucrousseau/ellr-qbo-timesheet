<?php

use App\Enums\TimeEntryStatus;

covers(TimeEntryStatus::class);

it('marks draft and rejected entries as editable and submittable', function () {
    expect(TimeEntryStatus::Draft->isEditable())->toBeTrue()
        ->and(TimeEntryStatus::Draft->isSubmittable())->toBeTrue()
        ->and(TimeEntryStatus::Rejected->isEditable())->toBeTrue()
        ->and(TimeEntryStatus::Rejected->isSubmittable())->toBeTrue();
});

it('locks pending and approved entries from employee edit and submit', function () {
    expect(TimeEntryStatus::Pending->isEditable())->toBeFalse()
        ->and(TimeEntryStatus::Pending->isSubmittable())->toBeFalse()
        ->and(TimeEntryStatus::Approved->isEditable())->toBeFalse()
        ->and(TimeEntryStatus::Approved->isSubmittable())->toBeFalse();
});

it('allows only approved entries to sync to quickbooks', function () {
    expect(TimeEntryStatus::Approved->isSyncable())->toBeTrue()
        ->and(TimeEntryStatus::Draft->isSyncable())->toBeFalse()
        ->and(TimeEntryStatus::Pending->isSyncable())->toBeFalse()
        ->and(TimeEntryStatus::Rejected->isSyncable())->toBeFalse();
});
