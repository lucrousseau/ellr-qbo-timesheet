<?php

/**
 * Applies SQLite PRAGMA settings on an open PDO connection.
 */

namespace App\Support;

use PDO;

/**
 * Configures busy timeout and journal mode for SQLite connections.
 */
class SqliteConnectionPragmas
{
    /**
     * Applies configured SQLite pragmas to the given connection.
     *
     * @param  PDO  $pdo  Open SQLite PDO connection.
     * @param  int  $busyTimeout  Busy timeout in milliseconds; skipped when zero.
     * @param  mixed  $journalMode  Journal mode name; skipped when not a non-empty string.
     * @return void
     */
    public static function apply(PDO $pdo, int $busyTimeout, mixed $journalMode): void
    {
        if ($busyTimeout > 0) {
            $pdo->exec('PRAGMA busy_timeout = '.$busyTimeout);
        }

        if (is_string($journalMode) && $journalMode !== '') {
            $pdo->exec('PRAGMA journal_mode = '.$journalMode);
        }
    }
}
