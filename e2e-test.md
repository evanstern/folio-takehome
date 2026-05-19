---
title: Folio merged-main E2E test
status: repeatable
updated: 2026-05-19
---

# Folio E2E test (merged main)

Use this when you want to verify the **current merged `main` branch**
end-to-end after feature merges. This is the human-readable runbook for
repeating the same checks civicplus ran after merging:

- migrations-infra
- scheduled-publishing
- readable-ids

The intent is not exhaustive QA. It is a **confidence pass** for the
three most important integrated behaviors:

1. admin page loads cleanly
2. future-dated document is blocked behind the publish gate
3. immediate document is visible via the readable-id + token URL

## Preconditions

- Repo checked out to `main`
- Docker available
- Port `8088` free (or `.env` updated accordingly)

## Step 0 — clean restart

From repo root:

```sh
docker compose down -v
docker compose up -d
sleep 5
docker compose exec -T app php tests/test.php
```

Expected:

```text
12 passed, 0 failed.
```

If tests fail, stop here. The browser pass is not meaningful on a dirty
or already-broken state.

## Step 1 — load admin page

Open:

```text
http://127.0.0.1:8088/admin.php
```

Expected:

- page title is `Admin · Folio`
- staff banner shows `Freddy Folio · freddy@folio.example`
- create form has:
  - `Title`
  - `Body`
  - `Publish at (optional, America/Chicago)`
- documents table is visible

Non-blocking known console noise:

- `favicon.ico` 404 is acceptable

## Step 2 — create a future-dated document

Fill the form with:

- **Title:** `E2E Future Doc`
- **Body:** `future-body-secret`
- **Publish at:** a clearly future local timestamp in the configured tz
  (example used during validation: `2026-05-19T23:45`)

Submit.

Expected:

- redirect to `admin.php?created=<id>`
- success banner `Document #<id> created.`
- new row in the table with title `E2E Future Doc`

## Step 3 — generate a share link for the future-dated document

Open:

```text
/share.php?doc=<future-doc-id>
```

Fill recipient email with something like:

- `future@example.com`

Submit.

Expected:

- success banner `Share link ready:`
- link shape should be:

```text
http://127.0.0.1:8088/view.php?d=<readable-id>&token=<hex>
```

This confirms readable-ids and share-token composition are both active.

## Step 4 — verify the future-dated share link is gated

Open the generated URL from Step 3.

Expected:

- page title `Not yet available · Folio`
- heading `Not yet available`
- message includes the formatted publish time, e.g.

```text
This document will be visible on May 19, 2026 at 11:45 PM CDT.
```

- body text `future-body-secret` **must not appear anywhere on the page**

This is the critical cross-feature assertion:

- readable-id lookup works
- token gate works
- scheduled-publishing gate still runs **after** readable-id resolution

## Step 5 — create an immediate document

Back on admin:

- **Title:** `E2E Immediate Doc`
- **Body:** `immediate-visible-body`
- **Publish at:** leave blank

Submit.

Expected:

- success banner for the new doc id
- row appears in the document list

## Step 6 — generate a share link for the immediate document

Open:

```text
/share.php?doc=<immediate-doc-id>
```

Fill recipient email:

- `immediate@example.com`

Submit.

Expected:

- success banner `Share link ready:`
- link shape:

```text
http://127.0.0.1:8088/view.php?d=<readable-id>&token=<hex>
```

## Step 7 — verify the immediate doc renders normally

Open the generated URL from Step 6.

Expected:

- page title `E2E Immediate Doc · Folio`
- visible paragraph `Shared with immediate@example.com`
- body contains:

```text
immediate-visible-body
```

- `Not yet available` **must not appear**

## Pass criteria

The merged `main` passes this E2E if all of the following are true:

- clean Docker restart succeeds
- PHP test suite prints `12 passed, 0 failed`
- future-dated share URL is readable-id + token shaped
- future-dated doc is gated and body does not leak
- immediate share URL is readable-id + token shaped
- immediate doc body renders normally

## Known acceptable noise

- `favicon.ico` 404 in browser console

## Failure triage hints

### `publish_at` missing-column / undefined-key errors

Likely stale environment. Reset fully:

```sh
docker compose down -v
rm -f db.sqlite
docker compose up -d
```

### Readable-id share banner missing or malformed

Check:

- `migrations/0003_add_readable_id.sql`
- `migrations/0004_readable_id_unique.sql`
- `public/share.php`
- `seed.php`

### Future doc body leaks through `?d=<rid>&token=<hex>`

The readable-id resolution path in `public/view.php` is bypassing the
scheduled-publishing gate. This is the highest-priority regression.

### Immediate doc shows `Not yet available`

Check whether the form serialized a non-empty `publish_at` value or the
timezone conversion path in `public/admin.php` produced an unexpected
future UTC timestamp.

## Suggested prompt for an agent

If you want another agent to run the same pass, give it this:

> Restart merged `main` cleanly (`docker compose down -v && up -d`),
> run `docker compose exec -T app php tests/test.php`, then perform a
> browser E2E covering two flows:
> 1) create a future-dated document, generate a share link, verify the
> readable-id+token URL shows `Not yet available` and does not leak body;
> 2) create an immediate document, generate a share link, verify the
> readable-id+token URL renders the body normally. Treat favicon 404 as
> non-blocking noise. Report exact observed URLs, titles, and whether the
> future body leaked.

## What civicplus observed on 2026-05-19

- admin loaded cleanly
- only console error was favicon 404
- future doc `E2E Future Doc` produced a share URL of the form:
  - `view.php?d=e2e-future-doc-j6y5&token=<hex>`
- future share page showed:
  - title `Not yet available · Folio`
  - `This document will be visible on May 19, 2026 at 11:45 PM CDT.`
  - no body leak
- immediate doc `E2E Immediate Doc` produced a share URL of the form:
  - `view.php?d=e2e-immediate-doc-h4w3&token=<hex>`
- immediate share page rendered:
  - title `E2E Immediate Doc · Folio`
  - `Shared with immediate@example.com`
  - body `immediate-visible-body`

That run passed.
