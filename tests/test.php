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

test('readable_id format matches spec', function () {
    $rid = generate_readable_id('Welcome to Folio');
    assert_true(
        preg_match('/^[a-z0-9-]+-[2-9a-hjkmnp-z]{4}$/', $rid) === 1,
        "unexpected format: {$rid}"
    );
    assert_true(
        preg_match('/^welcome-to-folio-[2-9a-hjkmnp-z]{4}$/', $rid) === 1,
        "expected welcome-to-folio prefix, got: {$rid}"
    );
    $empty = generate_readable_id('');
    assert_true(
        preg_match('/^doc-[2-9a-hjkmnp-z]{4}$/', $empty) === 1,
        "expected doc fallback, got: {$empty}"
    );
});

test('100 readable_ids for same title are unique', function () {
    $seen = [];
    for ($i = 0; $i < 100; $i++) {
        $rid = generate_readable_id('Welcome to Folio');
        assert_true(!isset($seen[$rid]), "duplicate generated: {$rid}");
        $seen[$rid] = true;
    }
});

test('view.php?d=<rid>&token=<hex> resolves to the doc', function () {
    $row = db()->query('
        SELECT d.readable_id, s.token, d.body
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ')->fetch();
    assert_true($row !== false, 'expected a seeded share');
    assert_true(!empty($row['readable_id']), 'seeded doc missing readable_id');

    $_GET = ['d' => $row['readable_id'], 'token' => $row['token']];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    include __DIR__ . '/../public/view.php';
    $out = ob_get_clean();

    assert_true(
        strpos($out, htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8')) !== false,
        'expected doc body in response'
    );
});

test('audit_log on doc create includes readable_id', function () {
    $title = 'Audit Log Readable ID Test';
    $rid = generate_readable_id_unique(db(), $title);
    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, readable_id)
        VALUES (?, ?, 1, ?)
    ');
    $stmt->execute([$title, 'body', $rid]);
    $docId = (int) db()->lastInsertId();
    audit_log('create', 'document', $docId, [
        'title' => $title,
        'readable_id' => $rid,
    ]);

    $row = db()->query("
        SELECT details FROM audit_log
        WHERE action = 'create' AND entity_type = 'document'
        ORDER BY id DESC LIMIT 1
    ")->fetch();
    assert_true($row !== false, 'expected an audit row');
    $details = json_decode($row['details'], true);
    assert_true(isset($details['readable_id']), 'expected readable_id in audit details');
    assert_true(
        preg_match('/^[a-z0-9-]+-[2-9a-hjkmnp-z]{4}$/', $details['readable_id']) === 1,
        "audit readable_id wrong format: " . $details['readable_id']
    );
    assert_true($details['readable_id'] === $rid, 'audit readable_id does not match generated');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
