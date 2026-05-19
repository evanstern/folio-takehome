<?php

/**
 * Readable document IDs.
 *
 * Format: `<slug>-<suffix>`
 *   slug:   first <=32 chars of lowercased title, non-alphanum -> '-',
 *           collapsed runs of '-', trimmed; empty -> 'doc' fallback.
 *   suffix: 4 chars from a 30-char Crockford-ish alphabet (no 0/1/i/l/o).
 *
 * Per .coda/designs/readable-ids.md (Format Spec). Readable IDs identify
 * documents; the existing hex share token still gates recipient access.
 */

const READABLE_ID_ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

function generate_readable_id(string $title): string {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 32);
    // Re-trim after substr in case the cut landed on a hyphen boundary.
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'doc';
    }

    $suffix = '';
    for ($i = 0; $i < 4; $i++) {
        $suffix .= READABLE_ID_ALPHABET[random_int(0, 29)];
    }

    return "{$slug}-{$suffix}";
}

function generate_readable_id_unique(PDO $db, string $title): string {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidate = generate_readable_id($title);
        $stmt = $db->prepare('SELECT 1 FROM documents WHERE readable_id = ?');
        $stmt->execute([$candidate]);
        if ($stmt->fetchColumn() === false) {
            return $candidate;
        }
    }
    throw new RuntimeException("could not generate unique readable_id for title: {$title}");
}
