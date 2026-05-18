---
tags: [incident, flag, video-talking-point]
description: current_staff() hardcodes staff id=1; no auth at all in the staff-facing app
created: 2026-05-18
updated: 2026-05-18
---

# Flag: no auth

`lib/bootstrap.php`:
```php
function current_staff(): array {
    $stmt = db()->prepare('SELECT * FROM staff WHERE id = 1');
    ...
}
```

The staff admin pages (`/admin.php`, `/share.php`) call `current_staff()`,
which **always returns the seeded `freddy@folio.example` row regardless of who's hitting the page**. There is no login, no session, no cookie.

## Why it's intentional (probably)

For a take-home, this is a feature: the spec is about document sharing, not
auth. Adding auth would eat the budget. Real internal tools at this scale
sometimes ride on a reverse proxy's mTLS or an SSO header (`X-Auth-User`),
and the codebase punts that decision.

## Why it matters for the three features

- **Scheduled publishing:** affects no recipient-side auth, since recipients still gate on share token. Fine.
- **Readable IDs:** if we ever expose `/d/<readable-id>` *without* a token, we have a privacy problem — anyone with the URL sees the doc. Our [[2026-05-18-1640-decision-readable-ids-complement]] keeps the token, so this is contained.
- **Search:** the search endpoint is staff-only by URL, but there's no actual gate. Searching for "salaries" returns any matching doc. Worth naming.

## What to do about it

**Nothing in this exercise.** Flag in the video as "the obvious next thing
to do with more time" and move on.

## Talking points for the video

- "There's no auth. Every staff-facing page assumes you're Freddy. I didn't
  fix this — it's out of scope and the README doesn't ask for it — but
  it's worth naming because two of the three features (readable IDs,
  search) would behave differently if there were real staff identity."

## Related
- [[folio-bootstrap]]
- [[folio-admin-page]]
