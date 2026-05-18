---
tags: [design, feature, readable-ids]
description: Short readable document IDs that complement (do not replace) the hex share token mechanism.
status: skeleton-awaiting-decision
created: 2026-05-18
updated: 2026-05-18
---

# Design: readable IDs

> **Status: SKELETON.** Body fills in once Evan locks
> [[2026-05-18-1640-decision-readable-ids-complement]] from
> `status: proposed` to `status: approved`. The shape below assumes
> the proposed decision: readable IDs identify documents, hex share
> tokens still gate recipient access.

## Problem

> README: "Customers want each document to have a short, readable ID —
> something a person could say out loud, type into a URL, or paste into
> an email. Examples: `welcome-2026`, `onboarding-packet-3k`, `FOLIO-7QX4`."

The interesting calls: format, length, collision strategy, URL structure,
and whether readable IDs **replace** or **complement** the existing hex
share-token mechanism.

## Decisions in scope

Locked in [[2026-05-18-1640-decision-readable-ids-complement]] (subject
to Evan's nod):

1. **Complement, not replace.** Readable IDs identify the document; the
   hex share token still gates recipient access.
2. **Format:** `<slug-from-title>-<4-char-base32>`. Lowercase, hyphen-
   separated slug from title; base32 suffix (no I/L/O/U) for collision
   resistance and sayability.
3. **URL structure:** `/d/<readable-id>?token=<hex>`. Document identity
   in the path, access token in the query string.
4. **Migration backfills all existing rows.** No NULL readable_ids
   anywhere — unique-not-null from the start.

## Scope

- [TBD on lock] Migration: `documents.readable_id TEXT UNIQUE NOT NULL`,
  backfill existing rows with generated IDs
- [TBD on lock] Helper function: `generate_readable_id($title): string`
  in `lib/bootstrap.php` (or new file)
- [TBD on lock] `admin.php` create form: generate readable_id on submit,
  show in confirmation
- [TBD on lock] `share.php`: build share URL from `readable_id`
- [TBD on lock] `view.php`: resolve `?d=<readable-id>` (or path-segment
  `/d/<readable-id>`) to document row before token check
- [TBD on lock] Audit-log: `create` details includes `readable_id`

## Schema impact

```sql
-- migrations/00X_add_readable_id.sql
ALTER TABLE documents ADD COLUMN readable_id TEXT;

-- Backfill: PHP runs after this migration to generate readable_ids for
-- existing rows, then a second migration adds the UNIQUE NOT NULL constraint
-- (SQLite can't add UNIQUE NOT NULL with backfill in one step).
--
-- OR: do the backfill inline if we trust the SQLite version supports it.
-- Decision detail to lock when Evan approves.
```

SQLite-specific concern: adding `UNIQUE NOT NULL` to an existing column
requires either (a) all rows already non-null + unique, or (b) the
table-rebuild pipeline. Backfilling first then adding the constraint via
a second migration sidesteps this.

## Format spec

```
<slug>-<suffix>
slug:   first ~30 chars of lowercased title with non-alphanumeric → "-"
        collapsed hyphens, trimmed of leading/trailing hyphens
suffix: 4 chars from base32 alphabet excluding {I, L, O, U} for sayability
        and disambiguation. ~28^4 ≈ 600K combinations per slug.
```

Examples:
- title `"Welcome Packet 2026"` → `welcome-packet-2026-7qx4`
- title `"Q3 Onboarding"` → `q3-onboarding-3k7p`
- title `""` (empty) → `doc-7qx4` (fallback slug)

Collision strategy: retry up to N times generating a new suffix. After N
retries (extremely unlikely), raise.

## Audit-log impact

`audit_log('create', 'document', $docId, ['title' => ..., 'readable_id' => $rid])`
extends the existing details payload.

`audit_log('share', 'document', $docId, ['readable_id' => $rid, 'token' => ...])`
on share-link generation (extends existing pattern).

## Test plan

In `tests/test.php`:

- `test('readable_id format')` — generate from known title, assert slug +
  4-char suffix, no banned chars
- `test('readable_id uniqueness')` — generate 1000 for same title, all
  unique
- `test('view.php resolves by readable_id')` — create doc, hit `/view.php?d=<rid>&token=<hex>`, assert document shown
- `test('audit_log on create includes readable_id')`

## Rejected alternatives

- **Replace integer IDs entirely with readable IDs.** Rejected:
  readable IDs are guessable; share tokens are not. Removing the token
  layer would change the privacy model. The complement design preserves
  the existing security guarantee while adding the UX win.
- **UUID v4 / nanoid.** Rejected: not human-sayable. `welcome-packet-7qx4`
  is readable; `550e8400-e29b-41d4-a716-446655440000` is not.
- **Pure slug from title (no suffix).** Rejected: collision risk. Two
  docs both titled "Welcome" → conflict. Suffix is small UX cost,
  meaningful guarantee.
- **Format `FOLIO-7QX4`.** Rejected (vs `welcome-packet-7qx4`): loses
  the title-derived context. Hard to scan in a list. The README example
  is just an example — not prescriptive.
- **Longer suffix (6-8 chars).** Rejected: 4 chars at ~28 alphabet is
  600K per slug; we will never have 600K docs sharing a title.
- **Numeric suffix.** Rejected: harder to say out loud than alpha.

## Video talking points

- "Customers said 'short, readable, sayable.' I shipped a slug + 4-char
  base32 suffix — readable, sayable, and collision-resistant without
  going to UUID."
- "The tricky call was complement vs replace. Replacing share tokens
  with readable IDs would mean anyone who guesses or hears a readable ID
  can read the doc. The complement design keeps the privacy model intact
  while still giving customers what they asked for."
- "I excluded I, L, O, U from the base32 alphabet — sayability and
  disambiguation. `1` vs `l` vs `I` is a real bug source in URLs people
  type from memory."

## Related

- [[2026-05-18-1640-decision-readable-ids-complement]]
- [[folio-schema]]
- [[folio-admin-page]]
- [[folio-share-page]]
- [[folio-view-page]]
- [[2026-05-18-1635-decision-scheduling-gates-content]] — readable-id
  resolution happens BEFORE publish-at gate
- Focus card #3
