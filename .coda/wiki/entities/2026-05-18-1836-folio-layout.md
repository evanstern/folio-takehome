---
tags: [entity, lib, layout, ui]
description: lib/layout.php — render_header/render_footer, the one shared UI shell
created: 2026-05-18
updated: 2026-05-18
---

# lib/layout.php

35-line UI shell. Two functions, no state, no classes. Required by
`public/admin.php` and `public/share.php`. **Not** required by
`public/view.php` (recipient page hand-rolls its own header — likely
intentional, recipients don't see the staff nav).

## Functions

### `render_header(string $title, ?array $staff = null): void`
Emits the `<!doctype>`, `<head>` (with `<title><?= h($title) ?> · Folio</title>`
and `<link rel="stylesheet" href="/assets/style.css">`), top nav, and opens
`<main class="container">`. If `$staff` is passed, renders the user pill.

### `render_footer(): void`
Closes `</main></body></html>`.

## Stylesheet
`public/assets/style.css` is the only CSS file. Hand-written, no build step.
No JS anywhere in the app.

## Surfaces affected by features

- **Search:** the most natural place to put a search input is either in the
  nav (global) or above the doc table on `admin.php` (scoped). The latter is
  simpler — no need to thread search state into the layout. Design call lives
  in the search design doc.
- **Scheduled publishing:** no layout impact beyond a new column/field on
  `admin.php`.
- **Readable IDs:** new column in the doc table on `admin.php`. The share
  URL on `share.php` may reshape, but it's plain text inside the page body.

## Related
- [[folio-admin-page]]
- [[folio-share-page]]
- [[folio-view-page]]
- [[folio-bootstrap]]
