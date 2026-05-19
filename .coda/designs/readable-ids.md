---
tags: [design, feature, readable-ids]
description: Short readable document IDs that complement (do not replace) the hex share token mechanism.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Design: readable IDs

> **Status: APPROVED.** Locked in
> [[2026-05-18-1640-decision-readable-ids-complement]] (session 2,
> surfaced via focus card + skeleton, no objections).

## Problem

> README: "Customers want each document to have a short, readable ID —
> something a person could say out loud, type into a URL, or paste into
> an email. Examples: `welcome-2026`, `onboarding-packet-3k`, `FOLIO-7QX4`."

The interesting calls: format, collision strategy, URL structure, and
complement-vs-replace for share tokens.

## Locked decisions

From [[2026-05-18-1640-decision-readable-ids-complement]]:

1. **Complement, not replace.** Readable IDs identify documents; the
   hex share token still gates recipient access.
2. **Format:** `<slug>-<4-char-base32>`. Slug from title (lowercase,
   non-alphanum → `-`, collapsed, ≤32 chars). Suffix is 4 chars from
   the Crockford-ish alphabet `23456789abcdefghjkmnpqrstuvwxyz`
   (no `0/1/i/l/o`, sayable + disambiguable).
3. **URL structure:** `/d/<readable-id>?token=<hex>`. Document identity
   in the path, access token in the query string.
4. **Migration backfills all existing rows** before adding the UNIQUE
   NOT NULL constraint. Two-step migration to sidestep SQLite's
   ALTER-COLUMN limitations.

## Scope

Single feature session delivers:

1. **Migration `migrations/0003_add_readable_id.sql`:**
   ```sql
   ALTER TABLE documents ADD COLUMN readable_id TEXT;
   -- Backfill happens in PHP via the runner before the next migration.
   ```
2. **Migration `migrations/0004_readable_id_unique_notnull.sql`** —
   added by the feature session AFTER it backfills existing rows in
   PHP (in `seed.php` or via a one-shot script). The cleanest path:
   the runner inserts backfill rows between migrations. Feature
   session picks: either (a) two SQL migrations with PHP backfill in
   `seed.php` between them, or (b) one SQL migration + UNIQUE INDEX
   on the populated column. **Recommend (b)** — simpler:
   ```sql
   -- migrations/0004_readable_id_unique.sql
   CREATE UNIQUE INDEX idx_documents_readable_id ON documents(readable_id);
   ```
   NOT NULL is enforced in PHP at insert time, not at the schema
   level. Documented trade-off.
3. **Helper `lib/readable_id.php`** exporting:
   - `generate_readable_id(string $title): string` — slug + suffix
   - `generate_readable_id_unique(PDO $db, string $title): string` —
     retry on collision (up to 5 attempts, then throw)
4. **`admin.php` create** generates a `readable_id` on insert.
5. **`share.php`** builds the share URL using `readable_id` in the
   path, hex token in the query.
6. **`view.php`** accepts `/view.php?d=<readable-id>&token=<hex>`.
   (URL rewriting to `/d/<rid>` is out of scope unless trivial in
   the existing PHP `-S` setup — feature session decides.)
7. **Backfill for seeded docs:** `seed.php` calls
   `generate_readable_id_unique` for each seed doc after insert and
   updates the row. Keeps seed deterministic-ish for tests.
8. **Audit log:** `audit_log('create', 'document', $docId, [..., 'readable_id' => $rid])`
   extends the existing create event.
9. **Tests:** four per "Test plan."

Out of scope:
- Path-segment routing (`/d/<rid>`). Query-string form (`?d=<rid>`)
  is functionally equivalent and doesn't require URL rewriting.
- Renaming documents. `readable_id` is immutable post-creation.
- Vanity IDs (user-chosen slugs).

## Schema impact

```sql
-- migrations/0003_add_readable_id.sql
ALTER TABLE documents ADD COLUMN readable_id TEXT;

-- migrations/0004_readable_id_unique.sql
CREATE UNIQUE INDEX idx_documents_readable_id ON documents(readable_id);
```

NOT NULL is enforced in PHP at insert time (`generate_readable_id_unique`
always returns a non-empty string; INSERT statements always include the
column). This avoids a table rebuild on existing data.

## Format spec

```
<slug>-<suffix>
slug:   first ≤32 chars of lowercased title, non-alphanum → '-',
        collapsed runs of '-', trimmed of leading/trailing '-';
        empty result → 'doc' (fallback)
suffix: 4 chars from '23456789abcdefghjkmnpqrstuvwxyz' (30-char alphabet)
```

Collision space per slug: 30⁴ ≈ 810,000.

Examples:
- `"Welcome to Folio"` → `welcome-to-folio-7qx4`
- `"Q3 Onboarding Packet"` → `q3-onboarding-packet-3kma`
- `""` → `doc-2bxv`

Collision strategy: `generate_readable_id_unique` calls
`generate_readable_id` then probes `documents.readable_id` for the
result; retries up to 5 times with fresh suffixes; throws on persistent
collision (statistically impossible but defensive).

### Alternative we considered: time-shaped IDs

In PR review Evan explicitly raised whether something like
`MM-DD-YYYY-HHMM` would be a better "human-readable" shape.

Design answer: **not as the default.** If the ID itself encodes time,
we immediately inherit uniqueness and timezone questions:

- minute-granularity timestamps collide unless we add seconds or a suffix
- the identifier starts carrying timezone semantics
- `MM-DD-YYYY` is readable to US eyes but not especially sortable or
  universal
- timestamp-heavy IDs lose title context unless combined with the slug,
  at which point they are longer than the chosen shape

If Folio ever wants chronological meaning inside the identifier, the
best version is not a bare timestamp but a hybrid like
`<slug>-YYYYMMDD-HHMM-<2-4 char suffix>`.

Example:
- `welcome-packet-20260518-2014-7q`

That keeps title context and uniqueness, but it's a larger, less sayable
identifier than `slug-4char`. For this exercise the chosen shape stays
the best balance.

## Audit-log impact

Per [[2026-05-18-1600-pattern-audit-log]]:

- `audit_log('create', 'document', $docId, ['title' => $title, 'readable_id' => $rid, 'publish_at' => $publishAtOrNull])`
  on doc create — extends the existing payload with `readable_id`.

No new audit action. Readable IDs are immutable; nothing to log on
update.

## Test plan

In `tests/test.php`, add:

- `test('readable_id format')` — `generate_readable_id("Welcome to Folio")`
  matches `/^welcome-to-folio-[2-9a-hj-np-z]{4}$/`
- `test('readable_id uniqueness')` — generate 100 IDs for the same
  title, all unique; insert N=10 of them into a fresh in-memory DB,
  assert UNIQUE INDEX prevents a duplicate INSERT
- `test('view.php resolves by readable_id')` — insert doc, hit
  `view.php?d=<rid>&token=<hex>` via include, assert doc body in
  response
- `test('audit_log on create includes readable_id')` — create doc via
  admin, query audit_log most-recent `create` row, assert details
  JSON contains `readable_id` matching the generated format

## Rejected alternatives

Captured in [[2026-05-18-1640-decision-readable-ids-complement]]:

- **Replace share tokens entirely** — breaks privacy model
- **UUID v4 / nanoid** — not human-sayable
- **Pure slug, no suffix** — collision risk for duplicate titles
- **`FOLIO-XXXX` style (no title context)** — loses scanability in a list
- **Timestamp-shaped IDs** (`MM-DD-YYYY-HHMM`, `YYYYMMDD-HHMM`) —
  readable, but weak on uniqueness unless paired with a suffix, heavier
  on timezone assumptions, and worse than `slug-4char` at preserving
  title context in a short ID.
- **Longer suffix (6-8 chars)** — sufficient space with 4 chars at
  this scale (30⁴ ≈ 810K per slug)
- **Numeric suffix** — harder to say out loud
- **User-chosen IDs** — collision handling + UX scope out

## Video talking points

- "Customers said 'short, readable, sayable.' I shipped slug + 4-char
  base32. Readable, sayable, collision-resistant without going UUID."
- "We considered more human-meaningful time-shaped IDs like
  `MM-DD-YYYY-HHMM`, but those either collide or drag timezone semantics
  into the identifier. If we ever want that product feel, the right
  shape is a hybrid like `slug-YYYYMMDD-HHMM-7q`, not a bare timestamp."
- "The privacy call was complement vs replace. Replacing share tokens
  with readable IDs means anyone who guesses or hears a readable ID
  can read the doc. The complement design preserves the existing
  privacy guarantee while giving customers what they asked for."
- "I excluded `0/1/i/l/o` from the alphabet — sayability and
  disambiguation. `1` vs `l` vs `I` is a real bug source when people
  type URLs from memory."
- "I'm enforcing NOT NULL in PHP rather than at the schema level —
  SQLite can't add NOT NULL to an existing column without a table
  rebuild, and the enforcement at the call site is just as honest.
  The UNIQUE INDEX catches duplicates at the database boundary."

## Related

- [[2026-05-18-1640-decision-readable-ids-complement]]
- [[folio-schema]]
- [[folio-admin-page]]
- [[folio-share-page]]
- [[folio-view-page]]
- [[2026-05-18-1635-decision-scheduling-gates-content]] — readable-id
  resolution happens BEFORE publish-at gate in view.php
- Focus card #3 `readable-ids`
