---
tags: [entity, lib, helpers]
description: lib/bootstrap.php — db connection, current_staff, audit_log, helpers
created: 2026-05-18
updated: 2026-05-18
---

# lib/bootstrap.php

48-line shared library required by every page. Sets the timezone
(`America/Chicago` — note this for scheduled-publishing UX), opens
the PDO, and exposes four helpers.

## Functions

### `db(): PDO`
Lazy singleton. Opens `../db.sqlite` (relative to `lib/`), sets
`ERRMODE_EXCEPTION` and `FETCH_ASSOC`, enables foreign keys.

### `current_staff(): array`
Reads `staff` row id=1. Throws if missing. **This is the only "auth"**
— see [[flag-no-auth]].

### `audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void`
Inserts into `audit_log`. Details are JSON-encoded. **Pattern to follow
for every new audited action in the three features** — see [[pattern-audit-log]].

### `random_token(int $bytes = 16): string`
`bin2hex(random_bytes($bytes))`. 32 hex chars at default. Used for share tokens.

### `h(string $s): string`
`htmlspecialchars` with quotes + UTF-8. Used everywhere for output escaping.

## Timezone
`date_default_timezone_set('America/Chicago')` runs at the top. For
[[decision-scheduling-gates-content]], `publish_at` storage format and
comparison need to be timezone-aware. Recommend storing UTC in SQLite
(`datetime('now')` is UTC by default in SQLite, so the existing
`created_at` columns are already UTC), comparing against
`gmdate('Y-m-d H:i:s')` in PHP, and rendering in `America/Chicago` for display.

## Related
- [[folio-schema]]
- [[pattern-audit-log]]
- [[decision-scheduling-gates-content]]
