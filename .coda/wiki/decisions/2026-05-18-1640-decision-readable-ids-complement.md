---
tags: [decision, feature, readable-ids]
description: Readable IDs identify documents; hex share tokens still gate recipient access. Complement, not replace.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: readable IDs complement, don't replace, share tokens

> **Status: APPROVED.** Surfaced to Evan via focus card #3 body and the
> design doc skeleton in session 2; no objections raised. The
> complement-not-replace framing was already documented in PROJECT.md
> as a civicplus pre-decided call ("Risks / Pre-decided Calls" section).
> Evan is welcome to override before the feature session spawns.

## Context

README explicitly leaves this open:
> Whether readable IDs **replace** the existing share-token mechanism
> or **complement** it (there are real tradeoffs either way — privacy,
> guessability, link permanence).

Three forces:
- **Privacy** — recipients shouldn't be able to enumerate or guess docs
- **Permanence** — staff want a stable, sayable ID per document
- **Convenience** — short, typeable URLs

## Decision

**Readable ID identifies the document. Share token still gates access.**

- Each document gets a permanent readable ID (e.g. `welcome-2026`, `onboarding-3k`)
- Recipient share URL: `http://<host>/d/<readable-id>?token=<hex>` (or `/view.php` with the same query shape — feature session can decide between routing approaches)
- Staff-facing URLs (admin previews etc.) can use bare `/d/<readable-id>` if we add a staff-auth check, BUT we have no auth — so for this exercise, the bare URL also requires token (or simply doesn't exist as a staff route, just a display reference)

## Format chosen

`<slug>-<4-char-base32>` where slug is the title slugified (lowercase, dashes, max 32 chars) and the suffix is a 4-character random base32 (`23456789abcdefghjkmnpqrstuvwxyz` — Crockford-ish, no ambiguous chars).

Examples: `welcome-packet-7qx4`, `onboarding-3kma`, `untitled-2bxv`.

- Collision-resistant: 30^4 = 810,000 per slug. Re-generate on collision.
- Human-sayable, mostly-typeable.
- Not guessable (4-char suffix is the privacy floor).
- Distinct from share tokens (which are 32 hex chars and recipient-bound).

## Rejected alternatives

- **Replace share tokens.** Breaks the link-permanence + privacy tradeoff. A readable ID is, by design, more guessable than a hex token. Without a token gate, anyone who knows a doc's slug could view it. Hard no.
- **Pure auto-incrementing word lists** (correct-horse-battery-staple style). Cute, but longer to type and not derived from the title — less "human-readable in context."
- **UUIDs.** Not readable.
- **User-chosen IDs.** Allows collisions and ugly conflicts. Out of scope to design the UX.

## Trade-offs accepted

- Adds one column to `documents`, one migration, one route.
- Increases the surface area of the URL space — but the token gate keeps the privacy guarantee where it was.
- Title slug can drift if the title changes; ID is generated at creation and immutable thereafter. We accept that the slug may not match the current title forever. Worth flagging in the video as a UX call.

## Talking points for the video

- "I considered replacing the share-token mechanism entirely. The trade-off
  is privacy vs. permanence — and you can't have both unless you keep
  the token as the access gate and let the readable ID be the *identifier*."
- "The 4-character suffix is the collision avoidance AND the privacy floor.
  Drop it and you have a guessable URL. Make it longer and it stops being
  readable."

## Related
- [[folio-schema]]
- [[folio-share-page]]
- [[folio-view-page]]
- [[2026-05-18-1635-decision-scheduling-gates-content]]
