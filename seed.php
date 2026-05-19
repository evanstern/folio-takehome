<?php

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/migrate.php';

$dbPath = __DIR__ . '/db.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = db();

// Migrations own the schema. 0001 is the baseline.
migrate($pdo, __DIR__ . '/migrations');

$pdo->exec("
    INSERT INTO staff (email, name) VALUES
        ('freddy@folio.example', 'Freddy Folio')
");

$title = 'Welcome Packet';
$readableId = generate_readable_id_unique($pdo, $title);
$stmt = $pdo->prepare('
    INSERT INTO documents (title, body, created_by, readable_id)
    VALUES (?, ?, 1, ?)
');
$stmt->execute([
    $title,
    "Welcome to Folio!\n\nThis is the body of your welcome packet.",
    $readableId,
]);
$docId = (int) $pdo->lastInsertId();

$token = random_token();
$stmt = $pdo->prepare('
    INSERT INTO shares (document_id, token, recipient_email)
    VALUES (?, ?, ?)
');
$stmt->execute([$docId, $token, 'recipient@example.com']);

$port = getenv('FOLIO_PORT') ?: '8000';
echo "Seeded db.sqlite.\n";
echo "Admin:        http://localhost:{$port}/admin.php\n";
echo "Sample share: http://localhost:{$port}/view.php?d={$readableId}&token={$token}\n";
