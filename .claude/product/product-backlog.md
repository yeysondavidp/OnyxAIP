# Product Backlog — ONYX AIP v1.0

The full decomposition of the SRA into epics and stories. This is the **index of all work**.
Detailed Given/When/Then stories live in `../stories/epic-<nn>-*.md`. Status legend in
`../README.md`.

Priority: **P0** = must-have for a usable v1 · **P1** = needed for v1 completeness · **P2** =
nice-to-have / late v1.

---

## Story map (value flow)

```
                 ONYX has one trustworthy record of every asset and every service action.
                 ─────────────────────────────────────────────────────────────────────
PM sets up        PM registers        PM dispatches      Technician          PM closes the
the world         the estate          the work           does the visit      loop & reports
─────────         ───────────         ──────────         ─────────────       ─────────────
Clients           Assets (+types)     Service Jobs       5-screen mobile     Validation
Stores            Display Groups      Technicians        flow (Alpine)       Service history
SLA profiles      QR labels           Invitations        Photos + GPS        SLA tracking
                  Asset lifecycle     (signed URLs)       Asset outcomes      Dashboards
                                                                              Reports + exports
        ── underpinned by ──> Foundation · Identity & multi-client security · Audit trail
```

---

## EPIC-00 — Foundation & Infrastructure · P0 · 📋 detailed

Scaffolding everything else stands on. See `stories/epic-00-foundation.md`.

| ID | Story | Status |
|----|-------|--------|
| US-00.1 | Laravel 11 project scaffold + base configuration | 📋 Ready |
| US-00.2 | Docker Compose environment (dev + prod parity) per ADR-002 | 📋 Ready |
| US-00.3 | Base layout, design system tokens & accessibility baseline | 📋 Ready |
| US-00.4 | Multi-client scoping foundation (`client_id` convention + global scope) | 📋 Ready |
| US-00.5 | Audit trail foundation (append-only, async) | 📋 Ready |
| US-00.6 | Quality gates: Pint, Larastan, Pest, CI pipeline | 📋 Ready |
| US-00.7 | Backup & storage-volume alerting job | 📋 Ready |

## EPIC-01 — Identity, Roles & Multi-Client Security · P0 · 📋 detailed

The security spine. See `stories/epic-01-identity-security.md`.

| ID | Story | Status |
|----|-------|--------|
| US-01.1 | PM authentication (login, logout, password reset) | 📋 Ready |
| US-01.2 | Role model & RBAC (`pm`, `technician`, future `client_user`) | 📋 Ready |
| US-01.3 | Authorisation policies + `client_id` scope enforcement | 📋 Ready |
| US-01.4 | Signed-URL technician guest access foundation | 📋 Ready |
| US-01.5 | Security middleware & rate limiting baseline (§14.3) | 📋 Ready |

## EPIC-02 — Client Management · P0 · 📋 detailed

| ID | Story |
|----|-------|
| US-02.1 | CRUD clients (with `ClientCode` uniqueness, soft active flag) |
| US-02.2 | Assign/edit SLA profile on a client |
| US-02.3 | Client list with search/filter + active/inactive toggle |

## EPIC-03 — Store Management & Store Dashboard · P0 · 📋 detailed

| ID | Story |
|----|-------|
| US-03.1 | CRUD stores under a client (type, address, state, timezone) |
| US-03.2 | Store list filterable by client/state/type |
| US-03.3 | Store dashboard: metadata + asset inventory table (§8) |
| US-03.4 | Store dashboard: open faults + last-service-per-asset + SLA status |

## EPIC-04 — Asset Registry · P0 · 📋 detailed

| ID | Story |
|----|-------|
| US-04.1 | CRUD asset base model (shared fields, `AssetCode` uniqueness) |
| US-04.2 | Type-specific fields: Digital Screen |
| US-04.3 | Type-specific fields: Media Player |
| US-04.4 | Type-specific fields: Lightbox |
| US-04.5 | Type-specific fields: Window Fixture & Infrastructure |
| US-04.6 | Asset list/detail with filter by type & status |
| US-04.7 | CSV bulk import with field mapping — 🧊 **Deferred post-v1** (v1 = manual entry only, §16 Q3) |

## EPIC-05 — Display Group Topology · P1 · 📋 detailed

| ID | Story |
|----|-------|
| US-05.1 | CRUD Display Group (one player → many screens) with invariants |
| US-05.2 | Topology diagram on store dashboard (player → screens) |

## EPIC-06 — Asset Status Lifecycle · P0 · 📋 detailed

| ID | Story |
|----|-------|
| US-06.1 | Enforce status state machine (§4.5) at model + DB level |
| US-06.2 | Status change with actor/timestamp/reason → audit + history |
| US-06.3 | Auto-transitions driven by job create/validate (§5.2) |

## EPIC-07 — QR Code & Label System · P1 · 📋 detailed

| ID | Story |
|----|-------|
| US-07.1 | Generate unique QR per asset → asset detail (role-aware) |
| US-07.2 | Printable PDF label sheet (A4, multi-asset) |
| US-07.3 | Rate-limited QR lookup endpoint (§14.3) |

## EPIC-08 — Service Job Management · P0 · 📋 detailed

| ID | Story |
|----|-------|
| US-08.1 | CRUD service job (reference uniqueness, client/store scope) |
| US-08.2 | Attach affected assets to a job (§5.2) |
| US-08.3 | Job status state machine (§5.3) with permitted transitions |
| US-08.4 | Multi-technician assignment with independent invite lifecycle (§5.4) |
| US-08.5 | Job hierarchy: parent / sub-job / remediation sub-job (§5.5) |
| US-08.6 | PM attachments (briefs/diagrams) with safe file handling |
| US-08.7 | Job board / list with filters (status, client, state, SLA) |

## EPIC-09 — Technician Directory & Invitation · P0 · 📋 detailed

| ID | Story |
|----|-------|
| US-09.1 | CRUD technician profiles (specialty, certs, preferred clients) §11 |
| US-09.2 | Send signed job invitation email + calendar ICS / Google URL |
| US-09.3 | Technician accept/decline via link; invite lifecycle states |
| US-09.4 | Link expiry handling + resend |

## EPIC-10 — Technician Mobile Workflow (5 screens) · P0 · 📋 detailed

Alpine-first per ADR-001. Each screen is a story.

| ID | Story |
|----|-------|
| US-10.1 | Screen 1 — Job Overview + Start Job (early-start window enforcement) |
| US-10.2 | Screen 2 — Before photos (client-side) + GPS capture + start checkpoint |
| US-10.3 | Screen 3 — Briefing & asset reference + in-visit asset status update |
| US-10.4 | Screen 4 — After photos + per-asset outcomes + completion checkpoint |
| US-10.5 | Screen 5 — Job summary (guest = create-account prompt; holder = dashboard) |
| US-10.6 | Resilient photo upload (retry without restarting workflow) §14.6 |

## EPIC-11 — Job Validation & Service History · P0 · 📋 detailed

| ID | Story |
|----|-------|
| US-11.1 | PM validate job → asset auto-transitions + write service history |
| US-11.2 | PM flag "Requires Remediation" → create remediation sub-job |
| US-11.3 | Append-only service history per asset (§7) on asset detail |
| US-11.4 | Service history per store view |

## EPIC-12 — SLA Management · P1 · 📋 detailed

| ID | Story |
|----|-------|
| US-12.1 | CRUD SLA profiles (§10.1) |
| US-12.2 | SLA clock start on fault job + business-hours/holiday calendar (§10.2) |
| US-12.3 | Breach-risk computation + flags on job/store/client views |

## EPIC-13 — Notifications · P1 · 📋 detailed

| ID | Story |
|----|-------|
| US-13.1 | PM notifications: job status, asset status, new fault (§12.1) |
| US-13.2 | SLA breach warning + breach event notifications |
| US-13.3 | Warranty-expiry approaching notifications (configurable) |
| US-13.4 | Technician notifications: invite, reminder, link-expiry (§12.2) |

## EPIC-14 — Reporting · P1 · 📋 detailed

| ID | Story |
|----|-------|
| US-14.1 | Asset Register report (CSV/PDF) by client/state/store/type |
| US-14.2 | Service history reports (per asset / per store) |
| US-14.3 | Open faults + SLA compliance reports |
| US-14.4 | Technician hours + warranty-expiry forecast |
| US-14.5 | Display Group topology report (§13.2) |

## EPIC-15 — Client Dashboard · P1 · 📋 detailed

| ID | Story |
|----|-------|
| US-15.1 | Client-level aggregates (stores by state, assets by type/status) §9 |
| US-15.2 | Open faults + overdue jobs drill-down + CSV export |

## EPIC-16 — Settings · P2 · 📋 detailed

| ID | Story |
|----|-------|
| US-16.1 | SLA thresholds, early-start window, warranty-alert config |
| US-16.2 | Email templates (Blade-style with variables) §15 |

## EPIC-17 — Audit Trail Viewer · P2 · 📋 detailed

| ID | Story |
|----|-------|
| US-17.1 | PM audit-log viewer filtered by asset / store / job (§14.5) |

---

## SRA §16 open questions — status

1. **Client read-only portal (v2):** still v2; role model handled defensively in EPIC-01. *(Open — v2.)*
2. **Store-manager fault reporting:** still v2; don't preclude a non-PM job-creation trigger. *(Open — v2.)*
3. **Asset CSV import (US-04.7):** ✅ **Resolved (2026-06-24) — v1 is manual entry only;** import
   deferred post-v1.
4. **Network monitoring auto-status:** out of v1; keep status API extensible (EPIC-06). *(Open — v2.)*
5. **Multi-store campaign client scope:** ✅ **Resolved (2026-06-24) — campaigns are single-client**
   (EPIC-08.5).
6. **Photo storage budget/alerts:** sizing → US-00.7; agree numbers with ONYX. *(Open — pre-deploy.)*
