---
tags: [incident, flag, video-talking-point]
description: shares table has no revoked_at — once a share token is created, it works forever
created: 2026-05-18
updated: 2026-05-18
---

# Flag: no share revocation

The `shares` table has `id, document_id, token, recipient_email, created_at`.
There is no `revoked_at`, no `expires_at`, no "active" flag.

Once a share link is generated and emailed, it's perpetually valid until
the row is deleted manually (and there's no UI for that).

## Why it matters

The README calls the share links "one-time links," but that's not really
what the code does — they're "single-recipient, permanent links until
manually deleted." A real product almost certainly needs revocation.

## Why it's out of scope here

Not one of the three features. Not in the README. Adding it would be a
fourth feature, eating budget.

## How it interacts with our features

- **Scheduled publishing:** the same share link continues working before
  AND after the publish time — pre-publish shows "not yet available,"
  post-publish shows content. That's coherent.
- **Readable IDs:** complement-not-replace means readable IDs don't change
  this — the token still gates access, and the token still doesn't expire.
- **Search:** unrelated.

## Talking points for the video

- "The README calls these 'one-time links' but they're actually permanent
  per-recipient links. With more time, share revocation would be the
  obvious fourth feature. Flagging because it'll be the first thing a
  customer asks for."

## Related
- [[folio-share-page]]
- [[folio-schema]]
