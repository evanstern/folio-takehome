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

function render_view_for_token(string $token): string {
    $script = __DIR__ . '/../public/view.php';
    $code = '$_GET = ["token" => ' . var_export($token, true) . '];'
        . '$_SERVER["REQUEST_METHOD"] = "GET";'
        . 'include ' . var_export($script, true) . ';';
    return run_php_script(['-d', 'auto_prepend_file=', '-r', $code]);
}

function post_admin_create_document(string $title, string $body, string $publishAt = ''): string {
    $script = __DIR__ . '/../public/admin.php';
    $code = '$_SERVER["REQUEST_METHOD"] = "POST";'
        . '$_POST = ['
        . '"title" => ' . var_export($title, true) . ','
        . '"body" => ' . var_export($body, true) . ','
        . '"publish_at" => ' . var_export($publishAt, true)
        . '];'
        . 'include ' . var_export($script, true) . ';';
    return run_php_script(['-d', 'auto_prepend_file=', '-r', $code]);
}

function run_php_script(array $args): string {
    $command = array_merge(['php'], $args);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    assert_true(is_resource($proc), 'expected php subprocess to start');

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($proc);

    assert_true($status === 0, 'php subprocess failed: ' . trim($stderr));

    return $stdout;
}

function make_doc_with_publish_at(?string $publishAtUtc, string $title, string $body): string {
    return make_doc_full($publishAtUtc, $title, $body)['token'];
}

function make_doc_full(?string $publishAtUtc, string $title, string $body): array {
    $rid = generate_readable_id_unique(db(), $title);
    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, publish_at, readable_id)
        VALUES (?, ?, 1, ?, ?)
    ');
    $stmt->execute([$title, $body, $publishAtUtc, $rid]);
    $docId = (int) db()->lastInsertId();
    $token = random_token();
    $stmt = db()->prepare('
        INSERT INTO shares (document_id, token, recipient_email)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$docId, $token, 'r@example.com']);
    return ['readable_id' => $rid, 'token' => $token];
}

function render_view_for_rid_and_token(string $rid, string $token): string {
    $script = __DIR__ . '/../public/view.php';
    $code = '$_GET = ["d" => ' . var_export($rid, true) . ', "token" => ' . var_export($token, true) . '];'
        . '$_SERVER["REQUEST_METHOD"] = "GET";'
        . 'include ' . var_export($script, true) . ';';
    return run_php_script(['-d', 'auto_prepend_file=', '-r', $code]);
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

test('generate_readable_id_unique returns distinct values across existing rows', function () {
    $title = 'Collision Probe';

    $first = generate_readable_id_unique(db(), $title);
    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, readable_id)
        VALUES (?, ?, 1, ?)
    ');
    $stmt->execute([$title, 'body one', $first]);

    $second = generate_readable_id_unique(db(), $title);
    assert_true($second !== $first, 'second generated readable_id should differ from existing row');

    $stmt->execute([$title, 'body two', $second]);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM documents WHERE readable_id IN (?, ?)');
    $countStmt->execute([$first, $second]);
    assert_true((int) $countStmt->fetchColumn() === 2, 'expected both readable_ids persisted uniquely');
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

test('future publish_at blocks recipient view', function () {
    $future = gmdate('Y-m-d H:i:s', time() + 3600);
    $token = make_doc_with_publish_at($future, 'Future Doc', 'secret body here');
    $html = render_view_for_token($token);
    assert_true(str_contains($html, 'Not yet available'), 'expected gate page');
    assert_true(!str_contains($html, 'secret body here'), 'body must not leak pre-publish');
});

test('past publish_at allows recipient view', function () {
    $past = gmdate('Y-m-d H:i:s', time() - 3600);
    $token = make_doc_with_publish_at($past, 'Past Doc', 'visible body content');
    $html = render_view_for_token($token);
    assert_true(str_contains($html, 'visible body content'), 'body should render');
    assert_true(!str_contains($html, 'Not yet available'), 'gate must not trigger');
});

test('null publish_at allows recipient view (back-compat)', function () {
    $stmt = db()->prepare('
        SELECT s.token, d.body
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.publish_at IS NULL
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected a seeded share with NULL publish_at');
    $html = render_view_for_token($row['token']);
    assert_true(str_contains($html, 'Welcome to Folio'), 'seeded body should render');
    assert_true(!str_contains($html, 'Not yet available'), 'gate must not trigger');
});

test('audit_log on create includes publish_at', function () {
    $futureLocal = date('Y-m-d\TH:i', time() + 7200);
    post_admin_create_document('Audited Doc', 'body', $futureLocal);

    $stmt = db()->prepare('
        SELECT d.id, a.details
        FROM documents d
        JOIN audit_log a
          ON a.entity_type = ?
         AND a.entity_id = d.id
         AND a.action = ?
        WHERE d.title = ?
        ORDER BY d.id DESC
        LIMIT 1
    ');
    $stmt->execute(['document', 'create', 'Audited Doc']);
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected created doc and matching audit_log row');
    $details = json_decode($row['details'], true);
    assert_true(is_array($details), 'details should be JSON object');
    assert_true(array_key_exists('publish_at', $details), 'publish_at key present');
    $docStmt = db()->prepare('SELECT publish_at FROM documents WHERE id = ?');
    $docStmt->execute([(int) $row['id']]);
    $publishAt = $docStmt->fetchColumn();
    assert_true($publishAt !== false, 'expected created document row');
    assert_true($details['publish_at'] === $publishAt, 'audit payload matches stored publish_at');
});

test('readable_id url respects publish_at gate (cross-feature)', function () {
    $future = gmdate('Y-m-d H:i:s', time() + 3600);
    $futureDoc = make_doc_full($future, 'Future Readable Doc', 'embargoed payload xyz');
    $html = render_view_for_rid_and_token($futureDoc['readable_id'], $futureDoc['token']);
    assert_true(str_contains($html, 'Not yet available'), 'd= URL must hit the gate for future docs');
    assert_true(!str_contains($html, 'embargoed payload xyz'), 'body must not leak via the d= URL pre-publish');

    $past = gmdate('Y-m-d H:i:s', time() - 3600);
    $pastDoc = make_doc_full($past, 'Past Readable Doc', 'visible body abc');
    $html = render_view_for_rid_and_token($pastDoc['readable_id'], $pastDoc['token']);
    assert_true(str_contains($html, 'visible body abc'), 'past-published doc should render via d= URL');
    assert_true(!str_contains($html, 'Not yet available'), 'gate must not trigger for past docs');
});

test('invalid publish_at shows validation error instead of fataling', function () {
    $html = post_admin_create_document('Broken Schedule', 'body', 'not-a-datetime');
    assert_true(str_contains($html, 'Publish time must be a valid date and time.'), 'expected validation message');
    $stmt = db()->prepare('SELECT COUNT(*) FROM documents WHERE title = ?');
    $stmt->execute(['Broken Schedule']);
    assert_true((int) $stmt->fetchColumn() === 0, 'invalid publish_at must not create a document');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
