---
tags: [decision, infrastructure, tooling, mcp, playwright]
description: Playwright MCP installed project-local via .coda/opencode.json, headless + isolated mode. Verified end-to-end by driving folio at localhost:8088.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: Playwright MCP, project-local

> **Status: APPROVED.** Step 1 of Evan's revised strategy (PROJECT.md):
> "install Playwright MCP (globally, locally, whatever) so we can use
> it for debugging/testing of the app."

## Context

We want browser-driving capability — for end-to-end verification of
the three features when they ship, and for any debugging that the
PHP test harness can't easily reach (form posts, redirects, the share
flow as a real recipient experiences it).

Microsoft ships two flavors:

- **Playwright MCP** — JSON-RPC MCP server, persistent browser state,
  rich introspection, heavier on tokens.
- **Playwright CLI + SKILLs** — newer, more token-efficient, the path
  Microsoft now actively recommends for coding agents.

For a 3-hour exercise with a tiny app (~380 LOC PHP, no auth, no JS),
both work. Evan's PROJECT.md says "install Playwright MCP." Honoring
that directive.

## Decision

**Playwright MCP, project-local.** Added to `.coda/opencode.json`:

```json
"mcp": {
  "playwright": {
    "type": "local",
    "command": ["npx", "@playwright/mcp@latest", "--headless", "--isolated"],
    "enabled": true
  }
}
```

Project-local (not global) because:
- It honors [[2026-05-18-1620-decision-coda-dir-shipped]]: the
  orchestrator setup travels with the repo. A reviewer running
  opencode against this branch sees the same tooling civicplus used.
- It scopes the dependency to this engagement. No leak into Evan's
  other projects.
- The Playwright MCP profile cache lives at
  `~/.cache/ms-playwright/mcp-<channel>-<workspace-hash>` — workspace-
  scoped automatically, so even with `--isolated` we don't pollute
  global caches.

## Flag choices

### `--headless`
This sandbox has no display server, so headless is the only mode that
runs. Drop it if interactive driving becomes useful (probably not
inside the budget).

### `--isolated`
Per Microsoft's docs: **a persistent profile can only be used by one
browser instance at a time.** Concurrent MCP clients sharing the same
workspace conflict. Each feature session that spawns its own opencode
will spawn its own Playwright MCP — so multiple browsers, one
workspace, definite conflict without `--isolated`.

Trade-off: no cookie/storage state persists between calls. For folio
that's a non-issue:
- No auth (`current_staff()` hardcodes id=1; see [[flag-no-auth]])
- Share tokens are in URLs, not cookies
- Recipient view is single-page

If we ever need persistence (e.g. testing the staff session if auth
gets added), drop `--isolated` and namespace via `--user-data-dir`.

## Verification (done now, not deferred)

End-to-end smoke test before declaring step 1 complete:
1. Confirmed `node` and `npx` resolve (node v22.22.2, npm 10.9.7)
2. Ran `npx -y @playwright/mcp@latest --help` — package resolves,
   command speaks the right flags
3. Pre-installed chromium-headless-shell (`npx playwright install
   chromium`) — 113 MB, cached at `~/.cache/ms-playwright/`. Future
   MCP invocations don't pay this cost.
4. Wrote a 30-line Node script using the underlying Playwright library
   to drive `http://localhost:8088/admin.php`:
   - Loaded the page (200 OK)
   - Confirmed title `Admin · Folio` and `<h1>Admin</h1>`
   - Filled the form, submitted, waited for `?created=N` redirect
   - Asserted success banner and that doc count went from 1 → 2
5. Re-seeded the DB and re-ran `tests/test.php` (green) so we're back
   at a known clean state.

That proves: chromium is installed, the workspace can drive its own
running app, and the full chain works. The MCP config in
`.coda/opencode.json` will activate on the next opencode session
restart — the same plumbing.

## What this enables for feature sessions

The feature-session brief ([[2026-05-18-1610-pattern-feature-session-brief]])
can now reference Playwright as available tooling. Specific use cases
the three features will benefit from:

- **Scheduled publishing:** verify the "not yet available" page renders
  for a pre-publish-time share without needing to fake the clock at
  the PHP layer
- **Readable IDs:** verify the new URL shape (`/d/<slug>?token=<hex>`)
  actually routes to the right document
- **Search:** verify the search input + filtered results render
  correctly, beyond the SQL-level test

PHP unit tests still cover the data-layer assertions; Playwright is
for end-to-end UX verification, not coverage padding.

## Remote-browser option (documented, not configured)

Evan asked: can Playwright drive a browser on his home machine instead
of headless-in-sandbox? Yes, three ways, summarized for future
reference:

1. **CDP endpoint.** Run chrome on the remote host with
   `--remote-debugging-port=9222`, SSH-tunnel the port back here
   (`ssh -L 9222:localhost:9222 ...`), swap the MCP command to
   `--cdp-endpoint=http://localhost:9222`. Most flexible. Lets Evan
   *watch* automation for the video.
2. **Playwright browser extension.** Install the Chrome/Edge
   extension on the remote machine, use MCP `--extension` mode.
   Lighter security footprint — per-session grant.
3. **Remote Playwright server.** `playwright run-server` on the
   remote host, MCP connects via `--remote-endpoint`. Most
   "production" pattern, heaviest setup.

Not configuring any of these now. Headless-in-sandbox covers the
verification need. If the video calls for "watch civicplus drive a
real browser," option 1 with an SSH tunnel is a 2-line config change.
The decision to defer was Evan's:

> "Stick with headless, revisit if needed."

## Rejected alternatives

- **Global MCP** (`~/.config/opencode/opencode.json`). Doesn't ship
  with the submission. Reviewers can't replicate the setup without
  manually adding it. Diverges from the orchestrator-setup-ships
  decision.
- **Playwright CLI + SKILLs.** Microsoft's currently recommended path.
  More token-efficient, structured around skills. **But:** Evan's
  PROJECT.md explicitly says "install Playwright MCP." Switching the
  recommendation mid-exercise (without re-surfacing to Evan) would
  contradict [[2026-05-18-1740-pattern-collaboration-with-evan]] —
  no unilateral design changes. CLI remains a path we can pivot to
  *with* his nod if MCP friction shows up.
- **No browser tooling at all** (just `curl` for verification). Works
  for current surfaces but won't catch JS-time issues if any feature
  needs them. We already added no-JS-required as a constraint in
  [[2026-05-18-1645-decision-search-like]], but it's not airtight —
  having browser verification available is cheaper than realizing we
  need it mid-feature.

## Trade-offs accepted

- **First MCP call in a new opencode session pays a one-time `npx`
  resolve cost** (~few seconds). Chromium itself is cached.
- **Browser binary lives in `~/.cache/ms-playwright/`** — outside the
  repo. Won't show up in fresh clones. Reviewers running the MCP
  themselves will trigger their own download. Document this in the
  README? Probably not — it's standard Playwright behavior.
- **`--isolated` means no cross-session state.** Documented above.

## Talking points for the video

- "Step 1 of Evan's revised strategy was installing Playwright MCP.
  I considered switching to Playwright CLI — Microsoft's now-
  recommended path for coding agents — but Evan's directive was
  explicit and switching unilaterally would have violated the
  peer-collaboration pattern I codified earlier. MCP it is."
- "The end-to-end smoke test ran headless chromium against the app
  at `:8088`, filled the create-document form, asserted the redirect
  + banner + table update. That's the kind of verification I want
  available for every feature session, not just an afterthought."

## Related
- [[2026-05-18-1620-decision-coda-dir-shipped]]
- [[2026-05-18-1842-decision-port-configurable]]
- [[2026-05-18-1740-pattern-collaboration-with-evan]]
- [[2026-05-18-1610-pattern-feature-session-brief]]
