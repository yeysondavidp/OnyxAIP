# ONYX Asset Intelligence Platform

Asset registry and field service management for ONYX Visual.
Laravel 13 · PHP 8.3 · Livewire 4 · Alpine.js · Tailwind CSS 4 · MySQL 8

---

## Getting started

### Prerequisites

- PHP 8.3+
- Composer 2
- Node 22+
- MySQL 8 (see credentials below)

### Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Edit .env: set DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan migrate
npm install
npm run dev
```

Visit `http://localhost:8000/smoke` to confirm Livewire, Alpine, and DB all respond.

### Docker setup

```bash
# 1. Create the shared Docker network (once per host)
docker network create shared_net

# 2. Copy and configure env
cp .env.example .env
# Set DB_HOST to your MySQL server IP, configure DB_DATABASE / DB_USERNAME / DB_PASSWORD

# 3. Build and start
docker compose up -d

# 4. Run migrations
docker compose exec app php artisan migrate
```

The app will be available on `http://localhost:${APP_PORT:-8080}`.

---

## Stack

| Layer | Choice | Reason |
|-------|--------|--------|
| PM surfaces | Blade + Livewire | CRUD-heavy, round-trips acceptable (ADR-001) |
| Technician mobile flow | Alpine-first islands | Camera/GPS must never round-trip on 4G (ADR-001) |
| Queue + cache | Redis (AOF, noeviction) | Durability + no silent job loss (ADR-002) |
| Database | MySQL 8 (shared container) | Reuse existing host infrastructure (ADR-002) |

Architecture decisions: `.claude/architecture/`

---

## Development conventions

- **Timezones:** all datetimes stored in UTC. Use `App\Support\Tz::display($dt, $timezone)` before presenting any datetime to a user.
- **Mass assignment:** `$guarded = []` is banned. Every model must declare explicit `$fillable`.
- **Multi-client scoping:** every tenant-scoped model uses the `ClientScoped` trait (US-00.4). Never skip it.
- **Quality gates:** `./vendor/bin/pint`, Larastan, and Pest must pass before merging.
- **Tests:** use the `onyx_aip_test` database (configured in `phpunit.xml`). `RefreshDatabase` cleans it automatically.
- **Australian English** in all UI copy.

## Planning

Stories, epics, and sprint plan: `.claude/`
