-- 0001_init_schema.sql
--
-- Baseline migration. Owns the full initial schema: the tracking table
-- plus every domain table. There is no `schema.sql` — migrations are
-- the single source of truth.
--
-- `schema_migrations` uses CREATE TABLE IF NOT EXISTS because the runner
-- bootstraps the same table before discovering migrations (chicken-and-
-- egg). The domain tables use bare CREATE TABLE: a re-apply against a
-- populated database should fail loudly, not silently skip real drift.
--
-- Per .coda/designs/migrations-infra.md.
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE staff (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);

CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (created_by) REFERENCES staff(id)
);

CREATE TABLE shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    recipient_email TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (document_id) REFERENCES documents(id)
);

CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id INTEGER,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
