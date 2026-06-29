# Personas

Who we build for. Each story should name the persona it serves. Derived from SRA §2.2.

## Yeis — Project Manager (ONYX internal) · PRIMARY

- **Role/auth:** Full account, role `pm`. Desktop/laptop, reliable connection.
- **Goals:** Keep a trustworthy single source of truth for every asset and every service action;
  dispatch technicians; validate completed work; watch SLAs; report to clients.
- **Context:** Manages multiple clients (Pandora, Sephora, Dior…) across all states. Lives in
  the PM dashboards, asset registry, job board.
- **Pains:** Inbound fault calls, chasing technicians, not knowing an asset's real status,
  SLA breaches discovered too late, assembling client reports by hand.
- **Design needs:** Dense but legible desktop UI (optimised 1280px+), fast filtering, bulk
  views, export.

## Michael — Field Technician, guest (link-based) · PRIMARY

- **Role/auth:** None. Reaches a job via a **signed URL** emailed to him. No login.
- **Goals:** Show up, do the visit, prove it was done (photos + GPS), record what he found per
  asset, leave. Minimal friction.
- **Context:** On a smartphone, on 4G, often inside a store with poor signal. May be mid-task
  when signal drops. Not technical.
- **Pains:** Slow/laggy forms, losing photos, unclear "what do I do next", tiny tap targets,
  re-doing work after a connection hiccup.
- **Design needs:** Mobile-first from 320px, big high-contrast CTAs, ≥44px targets, obvious
  single primary action per screen, plain non-blaming errors, no surprise data loss.
  (Drives ADR-001's Alpine-first decision.)

## Sneider — Field Technician, account holder · SECONDARY

- **Role/auth:** Email/password, role `technician`. Everything Michael can do, plus a history
  dashboard (past jobs, worked hours, assets serviced).
- **Goals:** Same as Michael, plus track own work and hours.
- **Design needs:** Same mobile-first flow + a lightweight personal dashboard.

## Rosie — Client read-only viewer (Pandora) · FUTURE (v2)

- **Role/auth:** Would be role `client_user`, scoped strictly to one `client_id`.
- **Goals:** See her brand's asset register and service history without PM access.
- **Status:** Out of v1 scope (SRA §16 Q1) — but the **role model and `client_id` scoping must
  be built day one** so adding her later is config, not rework. Security stories assume she
  exists.

## Store Manager — fault reporter · FUTURE / UNDECIDED (v2)

- **Role/auth:** Possibly public form / link, no account.
- **Goals:** Report "the screen is black" without phoning a PM, auto-creating a job.
- **Status:** Open question (SRA §16 Q2). Not planned for v1. Do not build, but don't design
  the job-creation path so it *can't* accept a non-PM trigger later.
