-- 0004_readable_id_unique.sql
--
-- Uniqueness for documents.readable_id. NOT NULL is enforced in PHP
-- (see lib/readable_id.php) because SQLite cannot retrofit NOT NULL
-- without a table rebuild.
--
-- Per .coda/designs/readable-ids.md (Schema Impact).
CREATE UNIQUE INDEX idx_documents_readable_id ON documents(readable_id);
