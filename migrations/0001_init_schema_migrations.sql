-- 0001_init_schema_migrations.sql
--
-- Bootstrap migration: declares the schema_migrations tracking table.
-- The runner also ensures this table exists via CREATE TABLE IF NOT EXISTS
-- before discovering migrations (chicken-and-egg). Using IF NOT EXISTS
-- here makes re-running this file a safe no-op on the standard apply
-- path, so 0001 is recorded like any other migration.
--
-- Per .coda/designs/migrations-infra.md.
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);
