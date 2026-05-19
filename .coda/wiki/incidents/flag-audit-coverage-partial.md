---
tags: [incident, flag, video-talking-point, audit]
description: audit_log covers everything we shipped, but "scheduling changes" is only logged at creation — no reschedule audit because no reschedule UI
created: 2026-05-18
updated: 2026-05-18
---

# Flag: audit_log coverage is partial vs. literal README reading

## What the README says

> "Document creation, scheduling changes, and share actions should be
> logged to `audit_log` (pattern is in `lib/bootstrap.php`)."

Three nouns: **creation**, **scheduling changes**, **share actions**.

## What actually ships

| README clause | Where logged | Notes |
|---|---|---|
| Document creation | `public/admin.php` — `audit_log('create', 'document', $docId, [...])` | baseline call site; we extended the payload |
| Share actions | `public/share.php` — `audit_log('create', 'share', $shareId, [...])` | baseline call site, unchanged |
| Scheduling changes | folded into the `create` event payload (`publish_at` key) | **no separate audit row for reschedules** |

Our feature work added **zero new `audit_log()` call sites**. We only
*extended the payload* of the existing `create` event for documents:

- `scheduled-publishing` branch: added `publish_at` to the payload
- `readable-ids` branch: added `readable_id` to the payload

Tests assert both keys land in `audit_log.details` (`tests/test.php:210`
for `readable_id`, `tests/test.php:271` for `publish_at`).

## Why "scheduling changes" is only half-covered

The literal README phrase implies an audit row whenever `publish_at` is
modified — not just when it's first set. We didn't ship that because
**there is no edit/reschedule UI**. `admin.php` has a create form, no
edit form. Staff who want a different `publish_at` have to recreate the
document. So there is no "change" event to log.

The decision to scope out an edit/reschedule flow is captured implicitly
by [[2026-05-18-1635-decision-scheduling-gates-content]] (which only
defined the *create-time* gate) and by the focus board punt cards.

## Other audit gaps (true on baseline too, we didn't worsen them)

Things the existing code does that are **not** audited:

- **Recipient views** — `public/view.php` has no `audit_log` call. Opening
  a share link is silent. Would be `audit_log('view', 'share', $shareId, [])`.
- **Share-link revocation/expiry** — no UI, no audit (see also
  [[flag-no-share-revocation]]).
- **Document deletion** — no UI, no audit.
- **Document body/title edits** — no UI, no audit.

## Talking points for the video

- "We logged everything we wrote. The pattern from `lib/bootstrap.php`
  was already there — we just extended the payload of the existing
  `create` event with the new fields each feature contributed
  (`publish_at`, `readable_id`)."
- "We didn't build a reschedule UI, so there's no second audit event for
  `publish_at` changes. Strictly read, the README's 'scheduling changes'
  clause points at a reschedule audit row we'd add the moment we ship an
  edit form. That's a one-line addition once the UI exists:
  `audit_log('schedule', 'document', $docId, ['publish_at' => $new,
  'previous' => $prev])`."
- "The recipient view path is unaudited on baseline. We left that as-is.
  With more time it would be a defensible `audit_log('view', 'share', …)`
  — useful for 'who saw what when' compliance questions."

## How to close the gap (if asked)

Minimal patch in `view.php` (after successful token resolution):

```php
audit_log('view', 'share', $share['id'], [
    'document_id' => $doc['id'],
    // optionally: 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]);
```

Not shipping it because it wasn't requested and adds a write on every
recipient page-load, which has its own design tradeoffs (write
amplification, PII in audit log, etc.).

## Related
- [[2026-05-18-1600-pattern-audit-log]]
- [[2026-05-18-1635-decision-scheduling-gates-content]]
- [[flag-no-share-revocation]]
- [[folio-bootstrap]]
