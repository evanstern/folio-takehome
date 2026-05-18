# Copilot Cloud Agent Instructions

## Quick orientation
- Stack: PHP 8.3 + SQLite, served by PHP built-in server in Docker.
- Main app flows:
  - Staff admin/create docs: `public/admin.php`
  - Create share links: `public/share.php`
  - Recipient view by token: `public/view.php`
- Core helpers live in `lib/bootstrap.php` and `lib/layout.php`.

## Fast start
- Start app: `docker compose up`
- Run tests in Docker: `docker compose exec app php tests/test.php`
- Fast local test path (when host PHP has SQLite enabled): `php tests/test.php`

## Working conventions for this repo
- Keep changes minimal and in plain PHP; do not introduce frameworks/tooling unless absolutely necessary.
- Use prepared SQL statements (existing pattern) and escape output with `h(...)`.
- Keep audit behavior consistent by using `audit_log(...)` for document/share/scheduling actions.
- `seed.php` resets `db.sqlite` from scratch; tests assume seeded state.
- If schema changes are needed, add migration file(s); do not directly edit `schema.sql` for feature work.

## Validation expectations
- Run the existing test harness before and after changes:
  - `php tests/test.php` (or Docker equivalent above)
- There is no dedicated linter/build pipeline in-repo; tests are the primary validation signal.

## Errors encountered during onboarding
- No blocking errors were encountered while onboarding this repository.

## Common errors and workarounds
- **`docker compose exec app ...` fails because service is not running**
  - Workaround: start services first with `docker compose up -d`, then re-run the command.
- **Port 8000 is already in use**
  - Workaround: copy `.env.example` to `.env`, set `FOLIO_PORT` to a free port (for example `8088`), and restart Compose.
- **`No staff row #1 found. Did you run \`php seed.php\`?`**
  - Workaround: run `php seed.php` (or restart via `docker compose up`) to reseed the SQLite database.
