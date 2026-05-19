<?php

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/layout.php';

$token = $_GET['token'] ?? '';
$readableId = $_GET['d'] ?? '';

if ($readableId !== '') {
    $stmt = db()->prepare('
        SELECT d.*, s.recipient_email
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE d.readable_id = ? AND s.token = ?
    ');
    $stmt->execute([$readableId, $token]);
} else {
    $stmt = db()->prepare('
        SELECT d.*, s.recipient_email
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE s.token = ?
    ');
    $stmt->execute([$token]);
}
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    render_header('Not found');
    ?>
    <div class="centered-message">
        <h1>Share link not found</h1>
        <p>The link you used is invalid or has been removed.</p>
    </div>
    <?php
    render_footer();
    exit;
}

$nowUtc = gmdate('Y-m-d H:i:s');
if ($doc['publish_at'] !== null && $doc['publish_at'] > $nowUtc) {
    $publishDt = new DateTime($doc['publish_at'], new DateTimeZone('UTC'));
    $publishDt->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $display = $publishDt->format('M j, Y \a\t g:i A T');

    render_header('Not yet available');
    ?>
    <div class="centered-message">
        <h1>Not yet available</h1>
        <p>This document will be visible on <strong><?= h($display) ?></strong>.</p>
        <p class="meta">Check back then with the same link.</p>
    </div>
    <?php
    render_footer();
    exit;
}

render_header($doc['title']);
?>

<h1 class="page-title"><?= h($doc['title']) ?></h1>
<p class="meta">Shared with <?= h($doc['recipient_email']) ?></p>

<pre class="doc-body"><?= h($doc['body']) ?></pre>

<?php render_footer(); ?>
