<?php

/**
 * SQL builders for merging local time entries with QuickBooks snapshot rows.
 */

namespace App\Support;

use App\Models\TimeActivitySnapshot;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds scalable count and union queries for the employee merged time entry list.
 */
class TimeEntryMergedListQuery
{
    /**
     * Counts merged local and snapshot rows without scanning a union subquery.
     *
     * @param  User  $user  Employee whose entries are listed.
     * @param  array{realm_id: string, employee_ref: string}|null  $context  Snapshot query context.
     * @return int
     */
    public static function count(User $user, ?array $context): int
    {
        $localCount = TimeEntry::query()
            ->where('user_id', $user->id)
            ->count();

        if ($context === null) {
            return $localCount;
        }

        $snapshotCount = TimeActivitySnapshot::query()
            ->where('realm_id', $context['realm_id'])
            ->where('qbo_employee_ref', $context['employee_ref'])
            ->whereNotExists(fn ($query) => self::applyLinkedQboIdExistsSubquery($query, $user))
            ->count();

        return $localCount + $snapshotCount;
    }

    /**
     * Builds the union query that merges local entries with legacy snapshot rows.
     *
     * @param  User  $user  Employee whose entries are listed.
     * @param  array{realm_id: string, employee_ref: string}|null  $context  Snapshot query context.
     * @return Builder
     */
    public static function unionQuery(User $user, ?array $context): Builder
    {
        $localListId = self::prefixedIdExpression('local', 'id');
        $localQuery = TimeEntry::query()
            ->where('user_id', $user->id)
            ->selectRaw($localListId.' as list_id')
            ->selectRaw('COALESCE(start_time, created_at) as sort_time')
            ->selectRaw("'local' as row_source")
            ->selectRaw('id as local_id')
            ->selectRaw('NULL as qbo_id');

        if ($context === null) {
            return $localQuery->toBase();
        }

        $snapshotListId = self::prefixedIdExpression('qbo', 'qbo_id');
        $snapshotQuery = TimeActivitySnapshot::query()
            ->where('realm_id', $context['realm_id'])
            ->where('qbo_employee_ref', $context['employee_ref'])
            ->whereNotExists(fn ($query) => self::applyLinkedQboIdExistsSubquery($query, $user))
            ->selectRaw($snapshotListId.' as list_id')
            ->selectRaw('COALESCE(start_time, last_synced_at) as sort_time')
            ->selectRaw("'snapshot' as row_source")
            ->selectRaw('NULL as local_id')
            ->selectRaw('qbo_id as qbo_id');

        return $localQuery->toBase()->unionAll($snapshotQuery->toBase());
    }

    /**
     * Correlates snapshots to local rows already linked by QuickBooks identifier.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|Builder  $query  Outer snapshot query builder.
     * @param  User  $user  Employee whose linked rows are excluded.
     * @return void
     */
    private static function applyLinkedQboIdExistsSubquery($query, User $user): void
    {
        $query->selectRaw('1')
            ->from('time_entries')
            ->whereColumn('time_entries.qbo_id', 'time_activity_snapshots.qbo_id')
            ->where('time_entries.user_id', $user->id)
            ->whereNotNull('time_entries.qbo_id')
            ->where('time_entries.qbo_id', '!=', '');
    }

    /**
     * Builds a database-specific expression that prefixes an identifier column.
     *
     * @param  string  $prefix  Stable list identifier prefix.
     * @param  string  $column  Source column name.
     * @return string
     */
    private static function prefixedIdExpression(string $prefix, string $column): string
    {
        $quotedPrefix = "'{$prefix}:'";

        return match (DB::connection()->getDriverName()) {
            'sqlite' => $quotedPrefix.' || CAST('.$column.' AS TEXT)',
            default => "CONCAT({$quotedPrefix}, {$column})",
        };
    }
}
