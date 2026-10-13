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
| **11** | Close v1 + housekeeping | EPIC-03 (US-03.4), EPIC-15, ops | Repo/docs back in sync with production, Miranda visit recorded, open-faults and client dashboards real. **v1 functionally complete.** |
| **12** | Field findings (Miranda) | EPIC-19 | Content faults, owned follow-ups, player swaps, audio topology, captioned photos — what the 12 Oct 2026 visit could not record. |
| **13** | Polish & trust | EPIC-17, US-03.6, US-12.4 | Audit viewer, store document library, NZ public holidays. |
| **14** | AI-assisted service reports | EPIC-18 | *(Post-v1, needs ADR-003 accepted)* The per-visit Technical Service Report drafts itself from validated job data; PM reviews/edits before finalising. Provider-agnostic (ADR-003). Uses EPIC-19 captions and follow-ups. |

## Status review — 2026-10-13

Sprints 0–10 shipped to `master` between 2026-06-29 and 2026-07-02 (one commit per sprint),
followed by a bug sweep, the first production rollout (2026-07-07/08) and small production fixes
up to 2026-07-21 (PR #11). **No code commits since 2026-07-21.** On 2026-09-09 production moved
to the Proxmox VM `vm-app` (`/opt/onyx-aip`, public URL `https://pulse.onyxvisual.com.au`,
database on `vm-data`); `DEPLOYMENT.md` is updated in the same change as this review. Production
is in real use: 4 service jobs (3 validated: Dior DJS, Sephora Bondi, Sephora Castle Towers;
JOB-2026-004 Pandora Miranda still `invited`), 53 stores, 198 assets.

The story files were not updated as work shipped, so most stories still read `📋 Ready` /
`⏸ Blocked` even though they are done; reconcile them in Sprint 11 (H2).

| Epic | State in code | Gap |
|------|---------------|-----|
| EPIC-00 … EPIC-02 | ✅ Done | — |
| EPIC-03 Stores | ✅ US-03.1/3.2/3.3/3.5 | **US-03.4** "Open Faults" card on the store dashboard is still a placeholder; **US-03.6** store documents not started |
| EPIC-04 Assets | ✅ incl. US-04.8 (router fields, PR #11) | US-04.7 CSV import deferred (one-off vendor import exists) |
| EPIC-05 … EPIC-14, EPIC-16 | ✅ Done (Sprints 4–10) | US-12.4 NZ holidays not started |
| EPIC-15 Client dashboard | ❌ Not started | client page only shows contact, stores list and SLA profile |
| EPIC-17 Audit viewer | ❌ Not started | no route/view; audit rows are written but cannot be browsed |
| EPIC-18 AI reports | 📝 Planned only | ADR-003 proposed, not accepted |
| EPIC-19 Miranda findings | 📝 Planned (this review) | 7 stories, 2 need ONYX decisions |

**Repo state (checked 2026-10-13):**
- ✅ The guzzle 7.15.1 security bump is on `master` (included in the PR #11 squash) and running
  in production. The leftover branch `feature/asset-router-infrastructure-detail` is deleted.
- **This repo is public:** test DB credentials belong in an untracked `.env.testing`, not in
  `phpunit.xml` (H3). `.claude/files/` (client deliverables) is gitignored for the same reason.

## Sprint 11 — detailed (next)

**Sprint goal:** *"The repo, the docs and production agree with each other, the Miranda visit
is in the register, and the store and client dashboards show real faults — v1 is functionally
complete."*

| # | Item | Type | Est. |
|---|------|------|------|
| H1 | Git hygiene: planning docs + `DEPLOYMENT.md` on their own PR; delete the merged feature branch; fast-forward local `master` | Ops | 1 |
| H2 | Reconcile every story `Status:` line and the backlog tables with what shipped (table above) | Docs | 1 |
| H3 | Move test DB credentials out of `phpunit.xml` into an untracked `.env.testing` and rotate them | Security | 1 |
| H4 | **Record the Miranda visit in production** (checklist below) | Ops | 2 |
| US-03.4 | Store dashboard: real open faults + last service per asset + SLA status | Story | 5 |
| US-15.1 | Client-level aggregates (stores by state, assets by type/status) | Story | 5 |
| US-15.2 | Open faults + overdue jobs drill-down + CSV export | Story | 5 |

### H4 — Record the Pandora Miranda visit (12 Oct 2026) in production

State found on 2026-10-13: the **Pandora** client and store **Miranda** (`PAN-MIR-001`) exist;
job **JOB-2026-004** "Pandora - Miranda - Oct 2026" (Fault Repair) was created on the day but is
still `invited`: the technician link was never used, so there are no assets, photos or
checkpoints. The store has **no assets** registered. Serial numbers and MAC addresses come from
the local report (`.claude/files/reports/`), never from this file.

1. Register the assets at `PAN-MIR-001` (same naming as the Pandora Townsville player: model
   `MIB 19`, manufacturer Beat):
   - Front window player — Active.
   - Feature wall + audio player (old unit) — Active, in service.
   - Replacement player — on site, **not in service** (until US-19.4 exists: say so in the asset
     notes and keep it out of any Display Group).
   - Front window portrait screen (in totem) and feature wall screen; audio amplifier as an
     Infrastructure asset connected from the old player.
2. Display Groups: "Front window" (front window player → portrait screen); "Feature wall" (old
   player → feature wall screen).
3. JOB-2026-004: attach the affected assets and the final PDF report (PM attachment). The visit
   happened offline, and the state machine only allows `Completed` from `In Progress`
   (`JobTransitionService`); force-complete cannot close an `Invited` job. Closing it needs an
   ONYX decision: either the technician replays the flow through the link (photos and outcomes
   recorded, but times and GPS would be from the replay day, not 12 Oct) or the job waits for a
   PM back-entry story (below). Outcome once it closes, until US-19.2 exists: **Active** with a
   per-asset note "Content action required: …".
4. Next steps until US-19.3 exists: record the three steps in the job notes: (1) portrait content
   9:16, 1080 × 1920 px to the front window player; (2) content + audio schedule to the
   replacement player; (3) PM confirms the swap with the Store Manager — no return visit
   expected.
5. Once US-19.3/US-19.4 ship, convert those notes into follow-ups and record the swap.

**Gap found while planning H4:** there is no supported way to record a visit that happened
outside the technician link (no GPS, no checkpoints). If this repeats, add a story for PM
back-entry of an offline visit rather than forcing jobs through.

**Storage data point for SRA §16 Q6:** this visit produced 25 photos ≈ 34 MB (≈ 1.4 MB per
photo) for 4–5 assets.

## Sprint 12 — Field findings (Miranda)

**Sprint goal:** *"The next content-fault visit can be recorded completely in the platform:
what's wrong, who fixes it, which unit is waiting to be swapped and which photo shows what."*

Committed stories (see `stories/epic-19-field-findings-miranda.md`): **US-19.1** (2), **US-19.3**
(5), **US-19.2** (3, after D2), **US-19.6** (5), **US-19.5** (3), **US-19.4** (8, after D1).
Stretch: **US-19.7** (3). Order matters: 19.3 before 19.2 (19.2 creates follow-ups).

## Sprint 13 — Polish & trust

**US-17.1** + **US-17.2** (audit viewer), **US-03.6** (store document library), **US-12.4** (NZ
public holidays — detail the story first, it is still `🧊`).

## Sprint 14 — AI-assisted service reports

EPIC-18 once ADR-003 is accepted (D3). The Miranda report is the third exemplar and changes the
template: see the "Exemplars" note at the top of `stories/epic-18-service-report-generation.md`.

## Decisions needed from ONYX

| # | Decision | Blocks | Recommendation |
|---|----------|--------|----------------|
| D1 | Add a **Spare / Not in service** asset status to the §4.5 lifecycle, or only register a replacement at swap time? | US-19.4, H4 step 2 | Add `Spare` (in store, not deployed): it is what Miranda actually looked like |
| D2 | Asset status for "content action required": stays **Active** or goes **Faulty**? | US-19.2 | Active (hardware works) + open follow-up, so hardware fault counts stay honest |
| D3 | Accept ADR-003 (AI provider gateway) and which provider to start with | EPIC-18 | Accept; start with a paid model, keep Ollama as the swap path |
| D4 | Should the SLA clock start at the fault's reported date or at job creation? | US-19.7 | Keep job creation (no SLA change); show the reported date for context |
| D5 | Photo storage budget and alert thresholds (SRA §16 Q6) | US-00.7 thresholds | Use the ≈1.4 MB/photo data point above |

## Sprint 0 — detailed (done)

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

## Sprint 2 — detailed (complete)

**Sprint goal:** *"PM can create clients and stores and open a store dashboard — the first
tenant-scoped CRUD surfaces, proving the security spine end-to-end."*

Committed stories: **US-02.1, US-02.2, US-02.3, US-03.1, US-03.2** (US-03.3 and US-03.4
blocked by EPIC-04 / EPIC-06 / EPIC-08 / EPIC-12 — placeholder views shipped with designed
empty states).

Definition of sprint done:
- Full CRUD for `clients` and `stores` (create, read, edit, deactivate — no hard delete).
- Defence-in-depth on every mutation: UI + FormRequest + Policy + DB unique index.
- `client_code` / `store_code` unique at DB + application layers.
- State and StoreType stored as PHP enums; IANA timezone validated server-side.
- Livewire client list: search (`#[Url]`), active/inactive toggle.
- Livewire store list: search + client/state/type filters (`#[Url]`), active filter pills,
  inactive toggle.
- Store dashboard skeleton: metadata, asset inventory placeholder, open faults placeholder,
  SLA placeholder — all with designed empty states.
- SLA profile FK on `clients`: nullable, empty-state message in form, warning on detail page.
- 81/81 tests green; Pint + Larastan clean.

## Cadence & ceremonies (lightweight for vibecoding)

- **Sprint length:** 1 week (adjust after velocity is known).
- **Planning:** pull the next sprint's stories, confirm each is *Ready* (full ACs + Engineering
  Bar checklist + DoD). Don't start a story that isn't Ready.
- **Definition of Ready:** narrative + Given/When/Then + Engineering Bar checklist + deps +
  estimate.
- **Review/Demo:** the shippable slice runs in Docker.
- **Retro:** capture anything that should become a new ADR or a backlog change.
