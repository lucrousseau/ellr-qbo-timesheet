<?php

/**
 * Tracks QuickBooks sync cursors per connected company realm.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $realm_id
 * @property Carbon|null $last_reconciled_at
 * @property Carbon|null $last_webhook_at
 */
class QboRealmSyncState extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'realm_id',
        'last_reconciled_at',
        'last_webhook_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_reconciled_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }
}
