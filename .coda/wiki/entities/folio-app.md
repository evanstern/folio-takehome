---
tags: [entity, app, overview]
description: Top-level map of the Folio app — surfaces, flows, stack
created: 2026-05-18
updated: 2026-05-18
---

# Folio app

## Summary

Minimal staff-facing document sharing tool. Staff create documents,
generate one-time share links bound to a recipient email, recipients
view the document by token. 381 LOC of PHP + 33 lines of SQL + 334 lines
of hand-rolled CSS (verified via `wc -l`).

## Stack

- PHP 8.3 CLI (`php -S 0.0.0.0:8000 -t public/`)
- SQLite (PDO with `pdo_sqlite`, foreign keys ON)
- Plain HTML + handwritten CSS, no JS, no build step, no Composer
- Docker / docker-compose for run + dev (volume-mounted, hot-reload via PHP's per-request load)

## Surfaces

| URL | File | Purpose |
|---|---|---|
| `/` | `public/index.php` | 3-line redirect to `/admin.php` |
| `/admin.php` | `public/admin.php` | list + create documents |
| `/share.php?doc=N` | `public/share.php` | generate share token for a doc, by recipient email |
| `/view.php?token=...` | `public/view.php` | recipient view by hex token |
| `/assets/style.css` | `public/assets/style.css` | the only stylesheet, all hand-rolled CSS variables + classes |

## Shared libs (server-side, not URL-routed)

| File | Purpose |
|---|---|
| `lib/bootstrap.php` | db, current_staff, audit_log, random_token, h — see [[folio-bootstrap]] |
| `lib/layout.php` | render_header/render_footer — see [[2026-05-18-1836-folio-layout]] |

## Flow

1. Staff loads `/admin.php` → sees doc list + create form
2. Staff submits new doc → row in `documents` + `audit_log('create', 'document', id, {title})`
3. Staff clicks "Create share →" → `/share.php?doc=N`
4. Staff enters recipient email → row in `shares` (random 32-char hex token) + `audit_log('create', 'share', id, {document_id, recipient_email})`
5. Share URL displayed: `http://<host>/view.php?token=<hex>`
6. Recipient visits → view.php joins shares→documents by token → renders body

## Auth model

There isn't one. `current_staff()` reads `staff` row id=1 unconditionally.
Seeded as `freddy@folio.example`. Out of scope to extend.

## Related
- [[folio-schema]]
- [[folio-bootstrap]]
- [[2026-05-18-1836-folio-layout]]
- [[folio-admin-page]]
- [[folio-share-page]]
- [[folio-view-page]]
- [[folio-docker]]
- [[folio-tests]]
- [[flag-no-auth]]
