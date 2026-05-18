---
tags: [entity, docker, dev-loop]
description: Dockerfile + docker-compose.yml + seed.php — the dev loop and the entry point for migrations
created: 2026-05-18
updated: 2026-05-18
---

# Docker + seed flow

## Dockerfile (8 lines)
- `php:8.3-cli`
- Installs `libsqlite3-dev` + `docker-php-ext-install pdo_sqlite`
- `WORKDIR /app`

## docker-compose.yml
- Top-level `name: ${COMPOSE_PROJECT_NAME:-folio-takehome}` pins the
  project name regardless of working directory (see
  [[2026-05-18-1851-decision-compose-project-name-pinned]])
- Single `app` service, builds locally
- Mounts `.:/app` (so host edits are live)
- Port `${FOLIO_PORT:-8000}:8000` (configurable via `.env`, see [[2026-05-18-1842-decision-port-configurable]])
- Passes `FOLIO_PORT` into the container so `seed.php` can print the right URLs
- Command: `sh -c "php seed.php && php -S 0.0.0.0:8000 -t public/"` (container always listens on 8000 internally)

## seed.php
- **Wipes `db.sqlite`** (unlink if exists)
- Runs `schema.sql` as the schema
- Inserts seed staff (`freddy@folio.example`), one `Welcome Packet` doc, one share for `recipient@example.com`
- Prints the share URL on stdout

## Implications for migrations

Per [[2026-05-18-1630-decision-migrations-shape]], `seed.php` is the natural place to invoke
the migration runner *after* loading `schema.sql` and *before* inserting seed
rows. The current `schema.sql` represents "the initial baseline as-was" and
shouldn't be edited — migrations append from there. (Alternative: bake the
new tables into `schema.sql` and only run migrations on existing DBs. We
rejected this — the README says "schema changes go through a migration file,"
which is unambiguous.)

## Related
- [[2026-05-18-1630-decision-migrations-shape]]
- [[2026-05-18-1842-decision-port-configurable]]
- [[2026-05-18-1851-decision-compose-project-name-pinned]]
- [[2026-05-18-1848-decision-bare-layout]]
