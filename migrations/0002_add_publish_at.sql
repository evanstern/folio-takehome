-- 0002_add_publish_at.sql
--
-- Scheduled publishing: optional future-visible timestamp on documents.
-- NULL = published immediately (back-compat with seeded rows from 0001).
-- Non-null = UTC 'YYYY-MM-DD HH:MM:SS', matching the created_at format.
--
-- Per .coda/designs/scheduled-publishing.md.
ALTER TABLE documents ADD COLUMN publish_at TEXT NULL;
