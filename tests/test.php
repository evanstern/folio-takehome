<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/migrate.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

test('migrate() applies pending migrations and skips applied ones', function () {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tmpDir = sys_get_temp_dir() . '/folio-migrate-test-' . uniqid();
    mkdir($tmpDir);
    file_put_contents(
        $tmpDir . '/0001_test.sql',
        'CREATE TABLE test_table (id INTEGER PRIMARY KEY);'
    );

    try {
        $result1 = migrate($db, $tmpDir);
        assert_true(count($result1['applied']) === 1, 'first run applies migration');
        assert_true(count($result1['skipped']) === 0, 'first run skips nothing');
        assert_true($result1['applied'][0] === '0001_test.sql', 'first run records basename');

        // Verify the migration SQL actually executed, not just got recorded.
        $tableExists = (bool) $db->query(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='test_table'"
        )->fetchColumn();
        assert_true($tableExists, 'first run creates the table from migration SQL');

        $result2 = migrate($db, $tmpDir);
        assert_true(count($result2['applied']) === 0, 'second run applies nothing');
        assert_true(count($result2['skipped']) === 1, 'second run skips applied');
        assert_true($result2['skipped'][0] === '0001_test.sql', 'second run skips by basename');
    } finally {
        @unlink($tmpDir . '/0001_test.sql');
        @rmdir($tmpDir);
    }
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
