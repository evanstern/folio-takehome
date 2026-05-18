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

Same as Zach's wiki. YAML frontmatter:
```yaml
---
tags: [entity, domain-tag]
description: One-line summary
created: YYYY-MM-DD
updated: YYYY-MM-DD
---
```
Internal links: `[[page-name]]` (no paths, no `.md`).

## Pages

### Entities (codebase map)
- [[folio-app]] — top-level overview of the app and its surfaces
- [[folio-schema]] — current SQLite schema (staff, documents, shares, audit_log)
- [[folio-bootstrap]] — `lib/bootstrap.php`: db, audit_log, current_staff, helpers
- [[folio-layout]] — `lib/layout.php`: `render_header`/`render_footer`, the one shared UI shell
- [[folio-admin-page]] — `public/admin.php`: doc list + create form
- [[folio-share-page]] — `public/share.php`: token generation
- [[folio-view-page]] — `public/view.php`: recipient view by token
- [[folio-tests]] — `tests/test.php` and the homemade test harness
- [[folio-docker]] — Dockerfile + docker-compose.yml + seed.php flow

### Decisions (Civicplus's ambiguity calls)
- [[decision-migrations-shape]] — numbered SQL files in `migrations/`, applied by tiny PHP runner via `seed.php`, tracked in `schema_migrations`
- [[decision-readable-ids-complement]] — readable IDs identify documents; hex share tokens still gate recipient access
- [[decision-scheduling-gates-content]] — scheduling gates *content visibility*, not document/ID existence
- [[decision-search-like]] — server-side case-insensitive LIKE substring match on title; not FTS, not fuzzy
- [[decision-coda-dir-shipped]] — `.coda/` ships in the submission (README invites it; orchestrator setup is part of the grade)

### Patterns
- [[pattern-audit-log]] — how `audit_log()` is called (entity_type/entity_id/details JSON)
- [[pattern-test-harness]] — the in-file `test()` + `assert_true()` pattern in `tests/test.php`
- [[pattern-feature-session-brief]] — civicplus's adapted feature-session brief (see also AGENTS.md)
- [[pattern-branching-worktrees]] — git worktrees per feature, branched off main
- [[pattern-focus-task-convention]] — focus cards link back to design docs; structure + lifecycle
- [[pattern-collaboration-with-evan]] — peer-mode operation: propose with artifacts, wait for approval, surface proactively

### Incidents / things to flag
- [[flag-no-auth]] — `current_staff()` hardcodes id=1; no auth at all. Out of scope to fix, worth naming.
- [[flag-token-in-url-via-host-header]] — `share.php` uses `$_SERVER['HTTP_HOST']` for the share URL; host-header trust is technically a bug, worth flagging.
- [[flag-no-share-revocation]] — shares table has no `revoked_at`. Customers will ask. Out of scope here.
