---
tags: [entity, page, share]
description: public/share.php — create a one-time share token for a recipient
created: 2026-05-18
updated: 2026-05-18
---

# public/share.php

76 lines. Generates a share token for a given doc + recipient.

## Behavior

- Reads `?doc=N`, 404s if missing
- **POST:** validates email, generates `random_token()`, inserts into `shares`, calls `audit_log('create', 'share', $shareId, [...])`, displays the resulting view-URL
- The view-URL is constructed as `http://<HTTP_HOST>/view.php?token=<hex>` — see [[flag-token-in-url-via-host-header]]

## Surfaces affected by features

- **Readable IDs:** share URL probably becomes `http://<host>/d/<readable-id>?token=<hex>` (or similar — final URL shape in [[decision-readable-ids-complement]]).
- **Scheduled publishing:** when generating a share, optionally display the upcoming publish time so staff know the recipient will see "not yet available" until then. Could also block share creation for unpublished docs — design call.
- **Search:** no impact.

## Dependencies
Requires `lib/bootstrap.php` and `lib/layout.php`.

## Related
- [[folio-view-page]]
- [[folio-layout]]
- [[pattern-audit-log]]
- [[flag-token-in-url-via-host-header]]
