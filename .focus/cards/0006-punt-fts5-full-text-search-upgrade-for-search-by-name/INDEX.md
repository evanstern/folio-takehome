---
schema_version: 2
id: 6
uuid: 019e3d6b-9175-76d1-96f2-de9a795f9b5c
title: 'punt: FTS5 full-text search upgrade for search-by-name'
type: card
status: backlog
priority: p3
project: folio-takehome
created: 2026-05-18
---
## Overview

The search-by-name feature ships with `WHERE title LIKE :q COLLATE
NOCASE`, a server-side case-insensitive substring match on
`documents.title`. This card captures the upgrade path to **SQLite
FTS5 full-text search**, deferred deliberately.

## Why we punted

Per [[2026-05-18-1645-decision-search-like]]:

- Dataset size: handfuls to low hundreds of docs in this exercise.
  Sequential scan on `title` is faster than maintaining an FTS5
  virtual table at this scale.
- FTS5 adds: a virtual-table migration, a sync trigger trio
  (INSERT/UPDATE/DELETE keeps the FTS index in sync with the source
  table), a different query syntax (`MATCH`), and additional test
  surface
- For a 3-hour exercise where reviewer-clarity is the grading axis,
  `WHERE title LIKE …` reads in two seconds; FTS5 syntax requires
  background knowledge a reviewer may or may not have

## What "doing it properly" looks like

**Schema:**
```sql
-- migrations/00XX_create_documents_fts.sql

-- Virtual table mirrors title (and optionally body) into FTS5
CREATE VIRTUAL TABLE documents_fts USING fts5(
    title,
    content='documents',
    content_rowid='id'
);

-- Sync triggers
CREATE TRIGGER documents_ai AFTER INSERT ON documents BEGIN
    INSERT INTO documents_fts(rowid, title) VALUES (new.id, new.title);
END;
CREATE TRIGGER documents_ad AFTER DELETE ON documents BEGIN
    INSERT INTO documents_fts(documents_fts, rowid, title)
        VALUES('delete', old.id, old.title);
END;
CREATE TRIGGER documents_au AFTER UPDATE ON documents BEGIN
    INSERT INTO documents_fts(documents_fts, rowid, title)
        VALUES('delete', old.id, old.title);
    INSERT INTO documents_fts(rowid, title) VALUES (new.id, new.title);
END;

-- Backfill from existing rows
INSERT INTO documents_fts(rowid, title) SELECT id, title FROM documents;
```

**Query:**
```php
$stmt = $db->prepare(
    "SELECT d.*, fts.rank
     FROM documents_fts fts
     JOIN documents d ON d.id = fts.rowid
     WHERE documents_fts MATCH :q
     ORDER BY fts.rank"
);
// :q can be 'welcome', 'welcom*' (prefix), '"exact phrase"', etc.
$stmt->execute([':q' => $q]);
```

**Upgrade behavior:**
- Ranking comes for free via `fts5`'s `rank` column (BM25 by default)
- Prefix matching: append `*` to user input or expose it as a UX choice
- Body search: add `body` to the FTS5 column list and to the triggers
  (this becomes a meaningful product addition, not just a tech upgrade)

## Trigger to revisit

Any one of:
- Doc count exceeds ~1,000 (sequential LIKE noticeably slow)
- A user requests phrase search ("documents containing 'Q3 onboarding'
  as a phrase") or ranking ("show closest matches first")
- The feature scope grows to body-content search (FTS5 is the right
  move the moment we leave title-only)
- Performance complaints surface in real usage

## Migration risk

Medium. FTS5 virtual table + triggers is well-trodden in SQLite, but
the migration adds three triggers + one virtual table to a schema that
otherwise has zero. Worth a dedicated PR with focused test coverage:

- Backfill correctness (existing rows show up in search)
- Trigger correctness (INSERT/UPDATE/DELETE keep the index in sync)
- Query behavior (prefix, phrase, ranking)

## Why not "just LIKE on title + body"

Considered. Rejected: substring LIKE on long body text is the worst
of both worlds — slower than FTS5, less powerful than FTS5, and
exposes regex-like behavior the user can't predict (e.g., what
happens to `%` in body content). If we leave title-only, we should
go to FTS5 directly.

## Related

- [[2026-05-18-1645-decision-search-like]] — the locked decision
  that triggered this punt
- [[.coda/designs/search.md]] — the design doc whose "Rejected
  alternatives → FTS5" section points here
- [[2026-05-18-1928-pattern-punt-cards]]

## Tags

`punt`, `search-by-name`, `fts5`, `p3`
