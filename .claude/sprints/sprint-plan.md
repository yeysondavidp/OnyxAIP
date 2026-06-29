# Sprint Plan — ONYX AIP v1.0

Roadmap of which epics land in which sprint and why. Vibecoding Scrum: short sprints, each
ending on a shippable vertical slice. Estimates are relative (story points, ~Fibonacci); team
velocity will calibrate after Sprint 0–1.

> Sequencing rule: **security and multi-client scope before any tenant data.** We never build a
> CRUD surface before the policy + `client_id` scope that protects it exists (Engineering Bar #1).

| Sprint | Theme | Epics | Goal / shippable outcome |
|--------|-------|-------|--------------------------|
| **0** | Foundation | EPIC-00 | App boots in Docker on the target server; layout, audit, scoping, CI all in place. Nothing tenant-facing yet, but every later story can be built safely. |
| **1** | Identity & security | EPIC-01 | A PM can log in; roles, policies, `client_id` scoping and signed-URL foundation exist and are tested. The security spine is real before any data. |
| **2** | The estate skeleton | EPIC-02, EPIC-03 | PM can create clients and stores and open a store dashboard. First tenant-scoped CRUD, end-to-end, proving the security spine. |
| **3** | Asset registry | EPIC-04 | PM can register assets of every type with type-specific fields and browse/filter them. CSV import scope confirmed first. |
| **4** | Topology, lifecycle, QR | EPIC-05, EPIC-06, EPIC-07 | Display Groups mapped, asset status state machine enforced + audited, QR labels printable and scannable. |
| **5** | Service jobs | EPIC-08 | PM can create jobs, attach assets, drive the job state machine, assign multiple technicians, build hierarchy. |
| **6** | Dispatch | EPIC-09 | PM invites technicians via signed URLs with calendar files; accept/decline lifecycle works. |
| **7** | Technician mobile flow | EPIC-10 | The full 5-screen Alpine-first field workflow: photos, GPS, asset outcomes, resilient upload. The product's beating heart. |
| **8** | Close the loop | EPIC-11 | PM validates jobs → auto asset transitions + append-only service history; remediation sub-jobs. |
| **9** | SLA | EPIC-12 | SLA profiles, business-hours clock with AU holidays, breach-risk flags across views. |
| **10** | Visibility & polish | EPIC-13, EPIC-14, EPIC-15, EPIC-16, EPIC-17 | Notifications, reports/exports, client dashboard, settings, audit viewer. v1 feature-complete. |

## Sprint 0 — detailed (current)

**Sprint goal:** *"The app runs in its real home (Docker on the small server) with the
foundations — layout, multi-client scoping, audit trail, quality gates — so every subsequent
story is built secure-by-default."*

Committed stories: **US-00.1 → US-00.7** (see `stories/epic-00-foundation.md`).

Definition of sprint done:
- `docker compose up` brings the full stack online locally and on the target server.
- A throwaway "hello" route renders inside the base layout on desktop and at 320px.
- The audit-log table + async writer exist and are unit-tested.
- The `client_id` scoping trait exists with passing tests proving cross-tenant reads are blocked.
- `./vendor/bin/pint`, Larastan, and Pest all run green in CI.
- Shared **MySQL** DB + least-privilege user provisioned; **new project-owned Redis** service up on
  the shared network with AOF + `noeviction` and a namespaced logical DB index + key prefix per
  ADR-002.

## Sprint 1 — detailed (next)

**Sprint goal:** *"A PM can securely sign in, and the role/policy/scope machinery that guards
every future feature is in place and proven by tests — including the technician signed-URL door."*

Committed stories: **US-01.1 → US-01.5** (see `stories/epic-01-identity-security.md`).

## Cadence & ceremonies (lightweight for vibecoding)

- **Sprint length:** 1 week (adjust after velocity is known).
- **Planning:** pull the next sprint's stories, confirm each is *Ready* (full ACs + Engineering
  Bar checklist + DoD). Don't start a story that isn't Ready.
- **Definition of Ready:** narrative + Given/When/Then + Engineering Bar checklist + deps +
  estimate.
- **Review/Demo:** the shippable slice runs in Docker.
- **Retro:** capture anything that should become a new ADR or a backlog change.
