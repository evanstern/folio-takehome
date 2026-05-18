<?php

/**
 * Apply pending SQL migrations from $migrationsDir against $db.
 *
 * Behavior:
 *  - Ensures schema_migrations tracking table exists (idempotent).
 *  - Discovers *.sql files in $migrationsDir, sorted lexicographically.
 *  - For each file not already recorded in schema_migrations, runs it
 *    inside a transaction and records the basename on success. On
 *    failure, rolls back and rethrows with file context.
 *
 * Returns ['applied' => [...basenames], 'skipped' => [...basenames]].
 *
 * Per .coda/designs/migrations-infra.md.
 */
function migrate(PDO $db, string $migrationsDir): array {
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
        $db->beginTransaction();
        try {
            $db->exec($sql);
            $stmt = $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
            $stmt->execute([$basename]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw new RuntimeException(
                "migration {$basename} failed: " . $e->getMessage(),
                0,
                $e
            );
        }
        $applied[] = $basename;
    }

    return ['applied' => $applied, 'skipped' => $skipped];
}
