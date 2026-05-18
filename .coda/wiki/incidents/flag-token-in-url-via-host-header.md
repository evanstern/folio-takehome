---
tags: [incident, flag, security, video-talking-point]
description: share.php constructs the recipient URL from $_SERVER['HTTP_HOST'], which is attacker-controlled
created: 2026-05-18
updated: 2026-05-18
---

# Flag: HTTP_HOST in share URL

`public/share.php`:
```php
Share link ready:
<code>http://<?= h($_SERVER['HTTP_HOST']) ?>/view.php?token=<?= h($created_token) ?></code>
```

`HTTP_HOST` comes from the client's `Host:` header. In a real
deployment behind a misconfigured reverse proxy (or directly exposed),
an attacker who can get a staff user to load `/share.php?doc=N` with
a forged Host header could phish themselves the share URL — though
the token itself is still generated and stored against the email, so
the impact is bounded.

## Severity

Low for a take-home running on localhost. In production with a public-
facing host this is real but well-known; the fix is a configured `APP_URL`
env var or a `BASE_URL` constant.

## What to do about it

**Nothing in this exercise.** It's pre-existing, out of scope, and fixing
it would mean introducing config plumbing the rest of the app doesn't have.

## Talking points for the video

- "Pre-existing thing worth flagging: the share URL is built from
  `$_SERVER['HTTP_HOST']`, which is attacker-controlled. Out of scope to
  fix — but I noticed it while mapping the share flow for readable IDs."

## Related
- [[folio-share-page]]
