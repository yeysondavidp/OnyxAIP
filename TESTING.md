# Running the test suite

The automated suite (`./vendor/bin/pest` / `php artisan test`) runs against a **real MySQL
database**, never SQLite — `create_technician_profiles_table.php` (and others) use raw
`DB::statement()` MySQL-only DDL (`DROP FOREIGN KEY`, `DROP PRIMARY KEY`, ...) that SQLite can't
parse, so the suite can't even migrate on SQLite.

## Why this doesn't run in GitHub Actions CI

It used to. `Tests (Pest)` hung for an identical ~12 minutes on GitHub's hosted runner across
three different MySQL setups (a `services:` container, the same with `--tmpfs`, and a manually
started `docker run` with `--tmpfs`) — while an exact local repro of the same Docker image, same
MySQL image, same flags, same command ran clean in 130s. That rules out our MySQL config as the
cause; whatever's wrong is specific to running Pest-against-real-MySQL inside GitHub's hosted
runner itself, and wasn't worth chasing further at the cost of a ~20min CI cycle per attempt.

`onyx-vein` (the sibling project) never hit this because its CI never attempts it either — its
workflow only builds/pushes Docker images, and its suite runs locally the same way described
below. `ci.yml` here now does the same: build + Pint + Larastan in CI, Pest locally.

## The test database

Tests run against `onyx_aip_test` on the same MySQL server as dev (`192.168.50.30`) — a separate
**database**, but currently the **same MySQL user** (`onyx_aip`) as dev. `phpunit.xml` hardcodes
`DB_DATABASE=onyx_aip_test` as a safety net (PHPUnit sets it before any `.env` loads, so it always
wins), so the suite can't accidentally point at the dev database by a missing/misconfigured env
file — but because the user is shared, a bug in that override would fail differently than it
should: a wrong *value* could still authenticate against dev, whereas a dedicated user would
simply fail to connect. `RefreshDatabase` runs `migrate:fresh` on every run, so a mistaken target
means real data loss, not just a test failure.

**Recommended hardening** (not yet applied — needs a MySQL root/admin run on `192.168.50.30`):

```sql
CREATE DATABASE IF NOT EXISTS onyx_aip_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'onyx_aip_test'@'%' IDENTIFIED BY '<strong-password>';

-- ALL on this DB only — includes REFERENCES, needed for foreign keys.
GRANT ALL PRIVILEGES ON onyx_aip_test.* TO 'onyx_aip_test'@'%';
FLUSH PRIVILEGES;
```

Then update `phpunit.xml`'s `DB_USERNAME`/`DB_PASSWORD` to match. After that, the worst case for a
misconfigured override is a connection error, never a wipe of dev data — matching how `onyx-vein`
already does this (see its `TESTING.md`).

## Running

No `.env.testing` file is needed — unlike `onyx-vein`, every env var the suite needs is already
inline in `phpunit.xml`, forced ahead of anything `.env` would set.

```bash
./vendor/bin/pest                                          # whole suite
./vendor/bin/pest tests/Feature/Assets/AssetCrudTest.php    # one file
./vendor/bin/pest --filter="pm can view the asset list"     # by name
```
