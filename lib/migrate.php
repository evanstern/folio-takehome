<?php

/**
 * Apply pending SQL migrations from $migrationsDir against $db.
 *
 * Behavior:
 *  - Validates $migrationsDir is an actual directory; throws if not.
 *    An empty directory is a valid no-op.
 *  - Forces PDO::ERRMODE_EXCEPTION for the duration of the call so
 *    SQL errors raise instead of returning false (and being silently
 *    recorded as applied). Prior error mode is restored before return.
 *  - Ensures schema_migrations tracking table exists (idempotent).
 *  - Discovers *.sql files in $migrationsDir, sorted lexicographically.
 *  - For each file not already recorded in schema_migrations, runs it
 *    inside a transaction and records the basename on success. On
 *    failure, rolls back (only if still in a transaction) and rethrows
 *    with file context.
 *
 * Returns ['applied' => [...basenames], 'skipped' => [...basenames]].
 *
 * Per .coda/designs/migrations-infra.md.
 */
function migrate(PDO $db, string $migrationsDir): array {
    if (!is_dir($migrationsDir)) {
        throw new RuntimeException(
            "migrations directory not found: {$migrationsDir}"
        );
    }

    $priorErrMode = $db->getAttribute(PDO::ATTR_ERRMODE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version    TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );

        $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);

        $appliedSet = [];
        foreach ($db->query('SELECT version FROM schema_migrations') as $row) {
            $appliedSet[$row['version']] = true;
        }

        $applied = [];
        $skipped = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if (isset($appliedSet[$basename])) {
                $skipped[] = $basename;
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException(
                    "migration {$basename} failed: could not read file {$file}"
                );
            }
            try {
                $db->beginTransaction();
                $db->exec($sql);
                $stmt = $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
                $stmt->execute([$basename]);
                $db->commit();
            } catch (Throwable $e) {
                // Guard rollBack: if beginTransaction() threw (caller
                // already had an active txn) or commit() failed (txn
                // already closed), rollBack() would throw and mask the
                // original error.
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw new RuntimeException(
                    "migration {$basename} failed: " . $e->getMessage(),
                    0,
                    $e
                );
            }
            $applied[] = $basename;
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    } finally {
        $db->setAttribute(PDO::ATTR_ERRMODE, $priorErrMode);
    }
}
