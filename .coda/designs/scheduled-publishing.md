---
tags: [design, feature, scheduled-publishing]
description: Schedule documents for future publication; pre-publish recipients see "not yet available". Server tz configurable via FOLIO_TZ.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Design: scheduled publishing

> **Status: APPROVED.** All sub-decisions locked in
> [[2026-05-18-1635-decision-scheduling-gates-content]] (session 2).
> Q1=A with `.env` configurability (`FOLIO_TZ`), Q2=A with explicit
> "we simplified" honesty. Multi-tz upgrade path parked as a punted
> backlog focus card.

## Problem

> README: "Staff should be able to prepare a document in advance and
> have it become visible to recipients at a specific date and time.
> Before that time, someone hitting the share link should see a 'not
> yet available' message instead of the document."

The interesting calls are: timezone handling, where the gate lives,
and whether share-link creation is blocked for unpublished docs.

## Locked decisions

From [[2026-05-18-1635-decision-scheduling-gates-content]]:

1. `publish_at` is nullable; null = published immediately
2. Gate lives in `view.php`, not `share.php`
3. "Not yet available" page shows the publish time
4. Storage UTC, comparison UTC, display in server-configured tz
5. Audit-log entries on create (with `publish_at`) and on schedule change
6. Interaction with readable IDs handled in `view.php` after token validation

**Timezone:**
- Staff input interpreted in the server-configured tz (`FOLIO_TZ` env
  var, defaulting to `America/Chicago`).
- Recipient display in the same server tz, with the abbreviation appended
  via `DateTime::format('T')` (e.g. `"9:00 AM CDT"`).
- No JS. No per-document tz. Multi-tz is a punted upgrade path.

## Scope

Single feature session delivers:

1. **Migration** `migrations/0002_add_publish_at.sql`:
   ```sql
   ALTER TABLE documents ADD COLUMN publish_at TEXT NULL;
   ```
2. **`.env.example` update:** document `FOLIO_TZ` with default
   `America/Chicago` and a comment pointing at the punted multi-tz card.
3. **`lib/bootstrap.php` update:** replace the hardcoded
   `date_default_timezone_set('America/Chicago')` with
   `date_default_timezone_set(getenv('FOLIO_TZ') ?: 'America/Chicago')`.
   This is the SINGLE source of truth for app tz.
4. **`admin.php` create form:** add an optional `<input type="datetime-local"
   name="publish_at">`. Empty submission stores NULL (immediate publish).
   Non-empty submission is parsed in server tz, converted to UTC, stored.
5. **`view.php`:** after token validation, before rendering doc body,
   check `publish_at`. If non-null AND in future (UTC compare), render
   "not yet available" with the publish time formatted in server tz +
   abbreviation. Otherwise render doc as before.
6. **`share.php`:** does NOT block. Optionally adds a small banner
   "This document will be visible on YYYY-MM-DD HH:MM TZN" — civicplus
   recommends including this; feature session can drop it if scope tight.
7. **Audit log:** `audit_log('create', 'document', $docId, [..., 'publish_at' => $rawInputOrNull])`
   extends the existing call. No new audit action needed (no edit UI in
   this card).
8. **Tests:** four tests per "Test plan" below.

Out of scope:
- Edit UI for changing `publish_at` after creation (`schedule` audit
  action defined but not triggered — punt to backlog if desired)
- CRON / background "publisher" job (explicitly rejected)
- Per-document or per-staff timezone (punted card)

## Schema impact

```sql
-- migrations/0002_add_publish_at.sql
ALTER TABLE documents ADD COLUMN publish_at TEXT NULL;
-- Null = published immediately (back-compat with existing rows).
-- Non-null = UTC ISO-ish string 'YYYY-MM-DD HH:MM:SS', matching created_at format.
```

SQLite `ADD COLUMN` is safe on 3.25+. No table rebuild.

## Tz handling — concrete code

Server tz comes from one place:

```php
// lib/bootstrap.php
date_default_timezone_set(getenv('FOLIO_TZ') ?: 'America/Chicago');
```

**On admin create:**
```php
$rawInput = $_POST['publish_at'] ?? '';
if ($rawInput === '') {
    $publishAt = null;
} else {
    // Parsed in the current default tz (server-configured)
    $dt = new DateTime($rawInput);
    $dt->setTimezone(new DateTimeZone('UTC'));
    $publishAt = $dt->format('Y-m-d H:i:s');
}
```

**On view check:**
```php
$nowUtc = gmdate('Y-m-d H:i:s');
if ($doc['publish_at'] !== null && $doc['publish_at'] > $nowUtc) {
    $publishDt = new DateTime($doc['publish_at'], new DateTimeZone('UTC'));
    $publishDt->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $display = $publishDt->format('M j, Y \a\t g:i A T');  // "Jun 1, 2026 at 9:00 AM CDT"
    render_not_yet_available($display);
    exit;
}
```

The `T` format token produces `CDT`/`CST`/`UTC`/etc. automatically.

## Audit-log impact

Per [[2026-05-18-1600-pattern-audit-log]]:

- `audit_log('create', 'document', $docId, ['title' => $title, 'publish_at' => $rawInputOrNull])`
  on doc create (extend existing call with `publish_at` in details JSON)
- `audit_log('schedule', 'document', $docId, ['publish_at' => $newWhen])`
  — defined for future use if/when edit UI lands. Not triggered in this
  card because there's no edit UI.

## Test plan

In `tests/test.php`, add four tests:

- `test('future publish_at blocks recipient view')` — insert doc with
  `publish_at` 1 hour in the future (UTC), hit view via include with
  valid token, assert response contains "not yet available"
- `test('past publish_at allows recipient view')` — insert doc with
  `publish_at` 1 hour in the past, hit view with valid token, assert
  response contains the doc body
- `test('null publish_at allows recipient view')` — use seeded docs
  (`publish_at IS NULL`), assert normal view works (back-compat)
- `test('audit_log on create includes publish_at')` — create doc via
  admin form with a `publish_at`, query audit_log for the most recent
  `create` row, assert details JSON contains `publish_at`

Uses the existing `tests/test.php` `test()` and `assert_true()` helpers
per [[2026-05-18-1605-pattern-test-harness]].

## Rejected alternatives

### Timezone alternatives

Captured in full in
[[2026-05-18-1635-decision-scheduling-gates-content]]. Highlights:

- **Browser-local tz via JS** (Q1/Q2 option B). Adds JS + attack
  surface. Cost > benefit at this scale.
- **Per-document tz `<select>`** (Q1 option C). Real upgrade path,
  parked as punted focus card.
- **UTC display to recipients** (Q2 option B). Looks like an error
  message.
- **Both server-tz and UTC** (Q2 option D). Noisy.

### Schema/behavior alternatives

- **`publish_at NOT NULL DEFAULT now()`** — non-trivial behavior
  change for existing rows. Null-as-immediate is more honest.
- **Hide publish time on "not yet available"** — privacy theater.
  Recipient has the token; let them know when to return.
- **Block share-link creation for unpublished docs** — defeats the
  "prepare in advance" use case.
- **Store `publish_at` as Unix timestamp** — diverges from existing
  `created_at`. Consistency wins.
- **CRON / background publisher** — rejected in PROJECT.md. The
  implicit-publish-at-request-time model is simpler and accurate.

## Video talking points

- "The README answered the main UX question — pre-publish shows 'not
  yet available', not 404. The interesting calls were: do you let staff
  share unpublished docs? (Yes — that's the feature.) Do you tell the
  recipient when to come back? (Yes — half-useful otherwise.)"
- "The timezone thing is the trap. SQLite stores UTC. The existing
  code had Central hardcoded. I made it configurable via `FOLIO_TZ`
  in `.env` — small generalization for real benefit: deterministic
  test comparisons and a clean upgrade path to multi-region."
- "I considered three options for recipient timezone display and
  shipped the simplest. The honest framing is 'we punted multi-tz' —
  a recipient in Tokyo still has to do mental math. Per-document tz
  is parked as a backlog focus card so a reviewer can see we
  considered it deliberately."
- "No CRON, no background worker. The publish is implicit — view.php
  compares current UTC to `publish_at` on every request. For this
  scale, that's the right shape."

## Related

- [[2026-05-18-1635-decision-scheduling-gates-content]]
- [[folio-schema]]
- [[folio-view-page]]
- [[folio-bootstrap]]
- [[2026-05-18-1600-pattern-audit-log]]
- [[2026-05-18-1640-decision-readable-ids-complement]] — readable-id
  resolution happens BEFORE publish-at gate in view.php
- Focus card #2 `scheduled-publishing`
- Punted focus card: multi-tz support (created in step 6c)
