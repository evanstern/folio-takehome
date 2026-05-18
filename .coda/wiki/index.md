# Wiki — Civicplus

Project knowledge base for the Folio take-home. Pages are seeded by Zach
(progenitor architect) at handoff and maintained by Civicplus through
normal operation.

Format: **Obsidian markdown** with YAML frontmatter (same convention as Zach).

## Page Types

- **entities/** — one page per significant thing in the codebase (file, table, function)
- **patterns/** — recurring approaches and conventions (existing or chosen)
- **decisions/** — design calls Civicplus made on ambiguous spec items
- **incidents/** — bugs found, surprises, things-to-flag-in-the-video

## Conventions

YAML frontmatter:
```yaml
---
tags: [entity, domain-tag]
description: One-line summary
created: YYYY-MM-DD
updated: YYYY-MM-DD
---
```

Internal links: `[[page-name]]` (no paths, no `.md`).

### Slug naming: date-prefixed

**Every new wiki page is named `YYYY-MM-DD-HHMM-<slug>.md`**, regardless
of subdirectory. The timestamp is minute-precision local time at the
moment the page was created (or, for pages reconstructed in retrospect,
the best-known time of creation).

Examples:
- `2026-05-18-1842-decision-port-configurable.md`
- `2026-05-18-1836-folio-layout.md`
- `2026-05-18-1600-pattern-audit-log.md`

Why:
- `ls patterns/` immediately shows creation order. Useful when patterns
  layer on each other (e.g. `pattern-collaboration-with-evan` was
  written in response to Evan's edits; the timestamp makes that
  obvious without reading the body).
- Decisions accumulate over a session in real time; the prefix shows
  the dependency chain (the bare-layout call came BEFORE the docker
  compose name-pin call, and that ordering matters for understanding
  why each exists).
- Renames are cheap: every wikilink is `[[full-slug]]` and rewrites
  with a `sed` one-liner.

Exceptions:
- **Incidents** (`flag-*.md`) use bare slugs. They're not events —
  they're persistent attributes of the codebase the orchestrator
  flagged. Ordering doesn't matter.
- **Entity pages** for top-level codebase artifacts (`folio-app`,
  `folio-schema`, `folio-bootstrap`, etc.) that were seeded in Zach's
  initial wiki and represent baseline knowledge keep their bare slugs
  for readability — they're the "what is this codebase" map, not a
  session timeline. New entity pages added during a session DO get
  date prefixes (e.g. `2026-05-18-1836-folio-layout` was added during
  civicplus's first-session wiki sweep).

When in doubt: date-prefix it.

## Pages

### Entities (codebase map)
- [[folio-app]] — top-level overview of the app and its surfaces
- [[folio-schema]] — current SQLite schema (staff, documents, shares, audit_log)
- [[folio-bootstrap]] — `lib/bootstrap.php`: db, audit_log, current_staff, helpers
- [[2026-05-18-1836-folio-layout]] — `lib/layout.php`: `render_header`/`render_footer`, the one shared UI shell
- [[folio-admin-page]] — `public/admin.php`: doc list + create form
- [[folio-share-page]] — `public/share.php`: token generation
- [[folio-view-page]] — `public/view.php`: recipient view by token
- [[folio-tests]] — `tests/test.php` and the homemade test harness
- [[folio-docker]] — Dockerfile + docker-compose.yml + seed.php flow

### Decisions (civicplus's design calls, in creation order)
- [[2026-05-18-1620-decision-coda-dir-shipped]] — `.coda/` ships in the submission (README invites it; orchestrator setup is part of the grade) — **approved**
- [[2026-05-18-1630-decision-migrations-shape]] — numbered SQL files in `migrations/`, applied by tiny PHP runner via `seed.php`, tracked in `schema_migrations` — **proposed**
- [[2026-05-18-1635-decision-scheduling-gates-content]] — scheduling gates *content visibility*, not document/ID existence — **proposed**
- [[2026-05-18-1640-decision-readable-ids-complement]] — readable IDs identify documents; hex share tokens still gate recipient access — **proposed**
- [[2026-05-18-1645-decision-search-like]] — server-side case-insensitive LIKE substring match on title; not FTS, not fuzzy — **proposed**
- [[2026-05-18-1842-decision-port-configurable]] — host port via `FOLIO_PORT` in `.env`, default 8000 (pushback moment) — **approved**
- [[2026-05-18-1848-decision-bare-layout]] — repo uses coda-lite bare layout: `.bare/` + sibling worktrees, `main/` is civicplus's home base — **approved**
- [[2026-05-18-1851-decision-compose-project-name-pinned]] — `name: folio-takehome` pinned in `docker-compose.yml`, overridable per worktree via `COMPOSE_PROJECT_NAME` — **approved**
- [[2026-05-18-1858-decision-playwright-mcp-project-local]] — Playwright MCP in `.coda/opencode.json`, headless + isolated, verified end-to-end — **approved**

### Patterns (operational backbone, in creation order)
- [[2026-05-18-1600-pattern-audit-log]] — how `audit_log()` is called (entity_type/entity_id/details JSON)
- [[2026-05-18-1605-pattern-test-harness]] — the in-file `test()` + `assert_true()` pattern in `tests/test.php`
- [[2026-05-18-1610-pattern-feature-session-brief]] — civicplus's adapted feature-session brief (see also AGENTS.md)
- [[2026-05-18-1730-pattern-branching-worktrees]] — coda-lite bare layout + git worktrees per feature
- [[2026-05-18-1735-pattern-focus-task-convention]] — focus cards link back to design docs; structure + lifecycle
- [[2026-05-18-1740-pattern-collaboration-with-evan]] — peer-mode operation: propose with artifacts, wait for approval, surface proactively

### Incidents / things to flag
- [[flag-no-auth]] — `current_staff()` hardcodes id=1; no auth at all. Out of scope to fix, worth naming.
- [[flag-token-in-url-via-host-header]] — `share.php` uses `$_SERVER['HTTP_HOST']` for the share URL; host-header trust is technically a bug, worth flagging.
- [[flag-no-share-revocation]] — shares table has no `revoked_at`. Customers will ask. Out of scope here.
