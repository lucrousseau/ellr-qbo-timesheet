<?php

/**
 * Local read model for QuickBooks time activities.
 */

namespace App\Models;

use Database\Factories\TimeActivitySnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cached QuickBooks time activity row scoped to a company realm.
 *
 * @property int $id
 * @property string $realm_id
 * @property string $qbo_id
 * @property string $qbo_employee_ref
 * @property string|null $customer_ref
 * @property string|null $customer_name
 * @property string|null $project_ref
 * @property string|null $project_name
 * @property string|null $item_ref
 * @property string|null $item_name
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property Carbon|null $txn_date
 * @property string|null $description
 * @property string|null $billable_status
 * @property string|null $qbo_sync_token
 * @property Carbon|null $qbo_last_updated_at
 * @property Carbon $last_synced_at
 */
class TimeActivitySnapshot extends Model
{
    /** @use HasFactory<TimeActivitySnapshotFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'realm_id',
        'qbo_id',
        'qbo_employee_ref',
        'customer_ref',
        'customer_name',
        'project_ref',
        'project_name',
        'item_ref',
        'item_name',
        'start_time',
        'end_time',
        'txn_date',
        'description',
        'billable_status',
        'qbo_sync_token',
        'qbo_last_updated_at',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'txn_date' => 'date',
            'qbo_last_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
