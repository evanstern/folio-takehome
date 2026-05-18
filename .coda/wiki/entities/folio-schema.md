---
tags: [entity, schema, sqlite]
description: Current SQLite schema in schema.sql — four tables, no indexes beyond PKs/uniques
created: 2026-05-18
updated: 2026-05-18
---

# Folio schema

`schema.sql` (33 lines, all 4 tables). **Do not edit directly** —
migrations only. See [[2026-05-18-1630-decision-migrations-shape]].

## Tables

### `staff`
- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `email TEXT NOT NULL UNIQUE`
- `name TEXT NOT NULL`

### `documents`
- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `title TEXT NOT NULL`
- `body TEXT NOT NULL`
- `created_by INTEGER NOT NULL` → `staff(id)`
- `created_at TEXT NOT NULL DEFAULT (datetime('now'))`

### `shares`
- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `document_id INTEGER NOT NULL` → `documents(id)`
- `token TEXT NOT NULL UNIQUE` (32-char hex via `random_token(16)`)
- `recipient_email TEXT NOT NULL`
- `created_at TEXT NOT NULL DEFAULT (datetime('now'))`

### `audit_log`
- `id INTEGER PRIMARY KEY AUTOINCREMENT`
- `staff_id INTEGER` (nullable, but in practice always set by `audit_log()`)
- `action TEXT NOT NULL`
- `entity_type TEXT`
- `entity_id INTEGER`
- `details TEXT` (JSON blob)
- `created_at TEXT NOT NULL DEFAULT (datetime('now'))`

## Schema changes coming from the three features

Likely new columns / tables (final decisions live in design docs):

- **Scheduled publishing:** `documents.publish_at TEXT NULL` (null = published immediately)
- **Readable IDs:** `documents.readable_id TEXT UNIQUE` (added with backfill in migration; nullability TBD in design doc)
- **Search:** no schema change for the LIKE approach. If FTS5 were chosen, an FTS virtual table would be added; we rejected FTS — see [[2026-05-18-1645-decision-search-like]].
- **Migrations infra:** `schema_migrations (filename TEXT PRIMARY KEY, applied_at TEXT)`

## Related
- [[2026-05-18-1630-decision-migrations-shape]]
- [[2026-05-18-1600-pattern-audit-log]]
- [[folio-bootstrap]]
