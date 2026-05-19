-- 0003_add_readable_id.sql
--
-- Adds `readable_id` to documents. Nullable at the SQL level because
-- SQLite cannot ALTER an existing column to NOT NULL without a full
-- table rebuild; we enforce non-null in PHP at insert time and rely on
-- the UNIQUE INDEX (0004) at the DB boundary to catch duplicates.
--
-- Per .coda/designs/readable-ids.md (Schema Impact).
ALTER TABLE documents ADD COLUMN readable_id TEXT;
