# Deploying ONYX AIP

Production runs on `192.168.50.30` (LAN), as a git checkout at `~/onyx-aip` on the
`digital` user, running the images CI builds — see `docker-compose.yml`'s header
comment for the full picture. This file is the day-to-day operator's guide.

**This repo is public.** Never commit credentials, `.env`, or connection strings —
only procedures and non-secret facts belong here.

## Branch policy

`master` is protected: no direct pushes, PRs required (including for the repo
admin — `enforce_admins` is on). All work happens on a feature branch.

```bash
git checkout -b fix/whatever
# ...make changes, commit...
git push -u origin fix/whatever
gh pr create --base master --head fix/whatever --title "..." --body "..."
# wait for CI (Pint/Larastan/security audit) to pass, then:
gh pr merge <number> --squash --delete-branch
git checkout master && git pull origin master
```

`qa` exists as a branch for testing changes before they land on `master`. It was
cut from `master` at creation time and is **not** kept in sync automatically —
merge/rebase it yourself when you want it to catch up.

## What happens automatically on merge to master

`.github/workflows/docker-build.yml` builds and pushes two images to ghcr.io:

- `ghcr.io/yeysondavidp/onyx-aip-app:latest` — used by the `app`, `queue`, and
  `scheduler` services (same image, different `command:`/`CONTAINER_ROLE`)
- `ghcr.io/yeysondavidp/onyx-aip-nginx:latest` — nginx with `public/` (Vite
  build included) baked in at build time

`.github/workflows/ci.yml` runs on every push/PR: build the image, `composer
audit`, Pint, Larastan. It does **not** run the Pest suite — see `TESTING.md`
for why, and how to run it locally against real MySQL instead.

## Deploying to production

Once the merge's `Build & Push Docker Images` workflow run is green (check
`gh run list --workflow=docker-build.yml --branch master --limit 1`), SSH to
the server and:

```bash
ssh digital@192.168.50.30
cd ~/onyx-aip
git pull
docker compose pull
docker compose up -d
```

That's the whole deploy. Migrations, cache warming, and config caching all run
automatically on the `app` container's boot (`docker/entrypoint.sh`,
`RUN_MIGRATIONS_ON_BOOT=true` in `docker-compose.yml`) — no separate `artisan
migrate` step needed.

**Always run `docker compose pull && docker compose up -d` for the whole
stack, not just `app`.** nginx re-resolves the `app` container's address
dynamically (see the `resolver`/`fastcgi_pass $upstream_app` fix in
`docker/nginx/default.conf`), so a normal `up -d` no longer requires a
separate nginx restart — but always let compose bring up everything together
rather than cherry-picking services, to avoid re-introducing that class of bug.

## One-off commands

Run inside the `app` container:

```bash
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate          # normally not needed — runs on boot
docker compose exec app php artisan import:vendor-store-players {csv} [--dry-run]
```

To get a file into the container first (e.g. a CSV to import):

```bash
scp somefile.csv digital@192.168.50.30:/tmp/
ssh digital@192.168.50.30 "cd ~/onyx-aip && docker compose cp /tmp/somefile.csv app:/tmp/somefile.csv"
```

## Local development

`docker-compose.yml` only references prebuilt ghcr.io images (no `build:`) so
that a server deploy is a plain pull + up. For local development, build from
source instead:

```bash
cp docker-compose.override.yml.example docker-compose.override.yml
docker compose build
docker compose up -d
```

## Databases on 192.168.50.30

Three separate databases, three separate credentials — never share them
across environments:

| Database | Purpose | Notes |
|---|---|---|
| `onyx_aip` | Local dev | Shares its MySQL user with `onyx_aip_test` — see `TESTING.md` for the recommended (not yet applied) hardening to separate them. |
| `onyx_aip_test` | Local Pest runs | See `TESTING.md`. |
| `onyx_aip_prod` | Production | Dedicated `onyx_aip_prod` user, scoped to this database only. Credentials live in `~/onyx-aip/.env` on the server (`chmod 600`, gitignored) — nowhere else. |

## Provisioning scripts

`docker/mysql/provision.sql` and `docker/mysql/provision-prod.sql` are
templates (`CHANGE_ME` placeholder password) for creating the dev/test and
prod database + user respectively. Both have already been run once against
the live MySQL server — only needed again if provisioning a new environment.
