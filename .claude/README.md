# ONYX AIP — Planning Hub (`.claude/`)

This folder is the **single source of truth for planning** the ONYX Asset Intelligence
Platform. The product requirements live in `/CLAUDE.md` (the SRA v1.0); **this folder turns
those requirements into executable work** using Scrum oriented to vibecoding.

> If you are an agent or a developer starting a task: read this file, then the relevant
> story in `stories/`. Each story is self-contained — it carries its own acceptance criteria,
> Engineering Bar checklist and Definition of Done so you don't have to re-derive context.

## Folder map

| Path | What it holds |
|------|---------------|
| `architecture/` | Architecture Decision Records (ADRs). Long-lived, numbered, immutable once accepted. |
| `product/personas.md` | The people we build for (PM, technicians, future client). |
| `product/product-backlog.md` | Every epic and every story title, with the story map and priority. **The index of all work.** |
| `sprints/sprint-plan.md` | The sprint roadmap — which epics land in which sprint, and why. |
| `stories/` | Detailed user stories grouped by epic, one file per epic. The actual work units. |

## How we work — Scrum for vibecoding

1. **Epics → Stories.** The backlog (`product/product-backlog.md`) decomposes the SRA into
   epics; each epic file in `stories/` holds small, vertically-sliced stories.
2. **A story is the unit of work.** It is "ready" when it has: a user-value narrative,
   Given/When/Then acceptance criteria, an Engineering Bar checklist, dependencies, an estimate,
   and a Definition of Done. Don't start a story that isn't ready — flesh it out first.
3. **Vertical slices.** Prefer a story that delivers a thin end-to-end path (DB → policy →
   controller/Livewire → UI) over a horizontal layer. Every slice must be shippable.
4. **The Engineering Bar is embedded, not optional.** Every story repeats the four
   non-negotiables from `/CLAUDE.md` as a concrete, tickable checklist for *that* story.
   "Guard in the controller but not the DB" is the recurring audit failure — the checklist
   exists to stop it at design time.
5. **Definition of Done (global).** On top of per-story criteria, every story must satisfy:
   - Defence-in-depth verified across UI + Request + Model/Policy + DB.
   - Authorised by role **and** `client_id` scope where data is tenant-scoped.
   - Tests written and passing (feature/unit; one happy-path integration test per command).
   - Pint (style) + Larastan (static analysis) clean.
   - UX states designed: loading / empty / error; inline `@error` validation.
   - Australian English in UI text; English in code/comments.
   - Audit-logged where the action is significant (§14.5).

## Story ID scheme

`US-<epic>.<n>` — e.g. `US-04.3` is the 3rd story of Epic 04 (Asset Registry).
Epics are `EPIC-<nn>`. ADRs are `ADR-<nnn>`.

## Status legend (used in the backlog)

`📋 Ready` · `✍️ Drafting` · `🚧 In progress` · `✅ Done` · `🧊 Backlog (not yet detailed)`

## Current state of planning

- Architecture: ADR-001 (frontend) and ADR-002 (deployment) **accepted**. ADR-003 (AI
  gateway, provider-agnostic, with data minimisation) **accepted 2026-10-14**. ADR-004
  (agent access via an MCP endpoint) **accepted 2026-10-13**. Infra
  resolved: reuse the existing **MySQL** (dedicated DB/user) + stand up a **new project-owned,
  shareable Redis** (AOF + `noeviction`, namespaced) — ADR-002.
- Backlog: **22 epics mapped and detailed** — `product/product-backlog.md` (EPIC-00 … EPIC-17 are
  the original SRA v1.0 scope; EPIC-18 is a new, post-v1-proposed capability — see below;
  EPIC-19 comes from field findings; EPIC-20 is agent access; EPIC-21 is field device discovery).
- **81 user stories written** across `stories/epic-00-*` … `epic-18-*`: ~57 `📋 Ready` in the
  original v1 scope, a few `⏸ Blocked` only on an upstream epic, US-04.7 `🧊 Deferred (post-v1)`,
  plus **8 new stories in EPIC-18** (Technical Service Report Generation, AI-assisted — 3
  `📋 Ready`, 5 `⏸ Blocked` on EPIC-11/EPIC-16). One file per epic, named `epic-<nn>-<slug>.md`.
- **Delivery (review 2026-10-13):** Sprints 0–10 shipped and are live in production; EPIC-15
  (client dashboard), EPIC-17 (audit viewer), US-03.4 open faults, US-03.6 and US-12.4 are the
  remaining v1 gaps. Story `Status:` lines lag behind the code — see the status review in
  `sprints/sprint-plan.md`.
- **EPIC-19** (8 stories) captures what the Pandora Miranda visit (12 Oct 2026) could not record.
- Next: **Sprint 11** — housekeeping, US-03.4 + EPIC-15; then **Sprint 12** — agent access
  (EPIC-20) and closing the Miranda job (US-19.8).

### EPIC-18 — Technical Service Report Generation (2026-07-24)

Discovered from two real ONYX deliverables (`.claude/files/`): the hand-written Pandora Pitt
Street Mall report (the target format) and raw technician notes for Dior Boutique Pacific Fair.
Not in the original SRA §13.1 export table — this is prose, not tabular data. Explored per ONYX's
request that AI text generation be **provider-agnostic from day one**: any model, paid (Claude,
GPT) or self-hosted (Ollama running Gemma or similar), swappable by config only — see **ADR-003**
and `stories/epic-18-service-report-generation.md`. AI drafts; the PM always reviews and finalises
(never auto-sent). Status: proposed, not yet scheduled into a committed sprint.

### Resolved decisions (ONYX, 2026-06-24)
- **Redis:** new project-owned, shareable container with `noeviction` — queue is safe; no open risk.
- **Asset import:** **v1 is manual entry only**; CSV import deferred post-v1 (US-04.7) — SRA §16 Q3.
- **Campaign scope:** a parent job/campaign is **single-client** (US-08.5) — SRA §16 Q5.

---

## Quality gates & testing conventions (US-00.6)

Run locally with `composer ci` (runs lint → analyse → test in sequence).

| Gate | Tool | Command | Config |
|------|------|---------|--------|
| Style | Laravel Pint | `composer lint` / `./vendor/bin/pint` | `pint.json` (preset: laravel) |
| Static analysis | Larastan (PHPStan) | `composer analyse` / `./vendor/bin/phpstan analyse` | `phpstan.neon` (level 5, includes Larastan extension) |
| Tests | Pest | `composer test` / `./vendor/bin/pest` | `phpunit.xml` |

CI runs all three gates on every push/PR via `.github/workflows/ci.yml`. A failing gate blocks merge.

### Testing conventions
- **One integration test per command** (happy path, fast). Mark with `RefreshDatabase` only where
  needed. Prefer pure unit tests for business logic that doesn't need the DB.
- **Pest functional syntax** for new tests: `it('...', function () { ... })`. PHPUnit class-based
  tests already in the suite are also valid — Pest runs both.
- `QUEUE_CONNECTION=sync` in `phpunit.xml` so queued jobs (e.g. `WriteAuditLog`) run inline in
  tests without needing a real worker. Never change this to `redis` in the test env.
- `CACHE_STORE=array` and `SESSION_DRIVER=array` in tests — no Redis dependency.
- **Larastan level 5.** Raise to 6+ as the project matures; update `phpstan.neon` and document
  the reason here.
- **No `@phpstan-ignore`, no baselines, no `T.must`-style suppressions.** Fix the underlying
  type issue. Trait helpers accessed via `static::` must be `protected`, not `private`.
