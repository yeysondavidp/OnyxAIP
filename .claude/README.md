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

- Architecture: ADR-001 (frontend) and ADR-002 (deployment) **accepted**. Infra resolved: reuse
  the existing **MySQL** (dedicated DB/user) + stand up a **new project-owned, shareable Redis**
  (AOF + `noeviction`, namespaced) — ADR-002.
- Backlog: **all 18 epics mapped and detailed** — `product/product-backlog.md`.
- **73 user stories written** across `stories/epic-00-*` … `epic-17-*`: ~57 `📋 Ready`, a few
  `⏸ Blocked` only on an upstream epic, and US-04.7 `🧊 Deferred (post-v1)`. One file per epic,
  named `epic-<nn>-<slug>.md`.
- Next: begin **Sprint 0** (EPIC-00 Foundation).

### Resolved decisions (ONYX, 2026-06-24)
- **Redis:** new project-owned, shareable container with `noeviction` — queue is safe; no open risk.
- **Asset import:** **v1 is manual entry only**; CSV import deferred post-v1 (US-04.7) — SRA §16 Q3.
- **Campaign scope:** a parent job/campaign is **single-client** (US-08.5) — SRA §16 Q5.
