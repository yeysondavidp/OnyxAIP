# ONYX Asset Intelligence Platform (AIP)
## Software Requirements Analysis — v1.0

**Document Version:** 1.0 — Initial Draft
**Date:** June 2026
**Region:** Australia (Multi-state, Multi-client)
**Target Stack:** Laravel (PHP) + Responsive Web
**Status:** Draft — Pending Review
**Prepared by:** ONYX Visual

---

## Engineering bar — non-negotiable, applies to EVERY change

These principles override convenience and are **not optional or skippable** in any session. They
apply to every change, not just to work tracked as a story. The goal is that any future audit
finds **minimal or nil** issues, because every scenario was handled at design time.

1. **Secure (defence in depth).** For every mutation, layer the guards: UI (hide/disable) +
   Controller/Form Request (authorise + validate every input, never trust the client) + Model/Policy
   (business invariants, `$fillable` only — never `$guarded = []`) + DB (unique indexes, FKs with
   correct on-delete, NOT NULL where required). If the app guarantees something, the DB must too.
   Authorise every portal/admin action against the actor's role **and** their `client_id` scope; a
   `client_user` must never reach another tenant's data or escalate. Validate signed URLs / file
   paths before any read/write; serve files with the correct MIME type. ONYX's recurring audit
   weakness is "guard in the controller but not the DB" — do not repeat it.
2. **Clean.** Match the surrounding code's style, naming and comment density (read neighbours
   first). Reuse existing services/patterns instead of re-implementing. No dead
   code, no speculative abstractions (YAGNI). Bulk DB writes via `DB::table()->insert()` in
   transactions on hot paths. Comments in English; UI text in Australian English.
3. **UX/UI for non-technical users.** Every new surface must be usable by a non-technical user:
   clear hierarchy and obvious primary action; inline field-level validation (`@error`), not just a
   bulk summary; plain-language, non-blaming errors; designed loading/empty/error states; accessible
   tap targets (min 44px) and keyboard/focus order.
4. **No guessing.** Verify column/relation names with tinker or by reading the file before writing
   code that depends on them. If a request lacks a clear goal or context, ask **one** clarifying
   question before proceeding — never guess.

## 1. Introduction

### 1.1 Purpose

This document defines the functional and non-functional requirements for the ONYX Asset Intelligence Platform (AIP) — a web-based asset tracking and field service management system built from scratch on Laravel.

The platform serves two primary functions operating in tandem:

1. **Asset Registry** — A centralised, client-scoped register of all digital display assets (screens, media players, lightboxes, fixtures) deployed across retail locations nationally. Inspired by Asset Panda's asset lifecycle model.
2. **Tech Service Management** — A field job workflow engine for scheduling, executing, and documenting technical service visits to stores. Inspired by TradifyHQ's field execution model.

Together, these functions give ONYX a single source of truth for every asset it has deployed and every service action performed against it — across all clients (Pandora, Sephora, Dior, and others), states, and store types.

### 1.2 Scope

The platform covers the following functional domains:

- Multi-client asset registry with hierarchical organisation (Client → State → Store → Asset)
- Asset type support: digital screens, media players, lightboxes, window fixtures, cable infrastructure
- Player-to-screen topology mapping (one player driving one or multiple screens)
- Store-level asset inventory views
- Tech service job creation, assignment, and execution workflow (mobile-first, 5-screen)
- GPS timestamping and photo documentation for service visits
- Service history per asset and per store
- PM validation workflow post-service
- Installer/technician directory and invitation system
- SLA tracking per client
- Reporting: asset health, service frequency, open issues by client/state/store

**Out of scope for v1:** billing/invoicing, payroll, native mobile apps (iOS/Android binaries), CMS platform integrations, automated network monitoring hooks.

### 1.3 Definitions and Acronyms

| Term | Definition |
|------|-----------|
| PM | Project Manager — ONYX internal staff who manage jobs and assets |
| Technician / Installer | Field worker who performs on-site service visits |
| Asset | A discrete piece of hardware deployed at a store location (screen, player, lightbox, fixture) |
| Player | Media player device (e.g., Beat MIB 02, Samsung SoC) driving one or more screens |
| Display Group | A logical grouping of one player driving one or more screens at a single location |
| Store | A retail location belonging to a client. Has a name, address, state, and store type |
| Client | The brand/retailer account (e.g., Pandora, Sephora, Dior) |
| Store Type | Classification of store format (e.g., Concept Store, Franchise, Department Store Concession) |
| Service Job | A discrete field service visit to a store to inspect, repair, or install assets |
| Sub-Job | A child job created under a parent when a visit was incomplete or requires remediation |
| SLA | Service Level Agreement — defines response and resolution time targets per client |
| GPS Stamp | Latitude/longitude + timestamp captured via device browser at job start/end |
| Asset Status | Current operational state of an asset (Active, Faulty, Offline, Decommissioned) |
| CRUD | Create, Read, Update, Delete |

### 1.4 Reference Systems

- **Asset Panda** — asset lifecycle tracking, audit history, custom fields per asset type, QR/barcode scanning
- **TradifyHQ** — field job management, technician dispatch, mobile execution workflow, job history
- **ServiceM8** — job scheduling and mobile workforce management
- **Simpro** — enterprise field service management

---

## 2. System Overview

### 2.1 Product Description

The AIP is a browser-based web application. All installer/technician-facing views are designed mobile-first (optimised for smartphones). PM-facing views are optimised for desktop/laptop. No native app installation is required.

The system operates on a three-role model: Project Manager, Technician (field), and Read-Only Client (future v2). The architecture is multi-client by design from day one — every record is scoped to a Client entity, ensuring clean data separation without a multi-tenant database split.

### 2.2 User Roles

| Role | Auth | Primary Capabilities |
|------|------|---------------------|
| Project Manager | Required — Full account | Full CRUD on assets, stores, clients, jobs; state management; reports |
| Technician (Guest) | None — Link-based | View job + asset details, execute service workflow (photos, GPS), mark completion |
| Technician (Account Holder) | Email/Password | All guest capabilities + job history, worked hours, assigned asset history |

### 2.3 Architecture Considerations

- **Backend:** Laravel 11.x, PHP 8.3+, RESTful routing with Blade + Livewire.
- **Frontend strategy:** Server-driven Blade/Livewire for PM (desktop) surfaces, where the
  workload is CRUD-heavy and round-trips are acceptable. The technician mobile workflow
  (Section 6) is built **Alpine.js-first**: all client-side interactions (camera capture, GPS,
  photo preview/removal, wizard step navigation) run locally with **no server round-trip**; the
  server is contacted only at meaningful checkpoints (Start Job, photo submit, Complete Job)
  through a small, well-defined endpoint contract. This keeps 4G performance within the NFR
  budget (§14.1) and lets the technician flow be extracted into a PWA later (§14.6) without
  touching PM code. Rationale and trade-offs: `.claude/architecture/ADR-001-frontend-stack.md`.
- **Deployment:** Single **small server** running **Docker Compose**. Lean footprint is a
  first-class constraint — every added service must justify its memory. Containers: nginx,
  php-fpm app, database, queue worker, scheduler. Persistent volume mounts are required for
  `storage/app` (photos) and the database data directory. Full topology, sizing and the
  queue/cache backend trade-off: `.claude/architecture/ADR-002-deployment.md`.
- **Database:** MySQL 8.x or PostgreSQL 15+ — UTC datetime storage, display per job timezone
- **File Storage:** Laravel Storage abstraction, local disk for v1, cloud-swappable via config
- **Email:** Laravel Mail, queue-driven dispatch
- **GPS:** Browser Geolocation API, server-side timestamping
- **Auth:** Laravel Breeze/Fortify for PM; signed URL tokens for technician guest access
- **Queue:** Laravel queue worker (backend chosen for small-server footprint — see ADR-002)

### 2.4 Planning & Delivery Method

This project is planned and delivered using **Scrum oriented to vibecoding**: work is broken
into epics and small, vertically-sliced user stories, each carrying its own acceptance criteria,
an embedded **Engineering Bar checklist** (the non-negotiable principles above, turned into a
per-story checklist), and a Definition of Done. This lets any session (human or agent) pick up a
story and implement it correctly without re-deriving context. All planning artefacts live in
`.claude/` — start at `.claude/README.md`.

---

## 3. Client & Store Data Model

### 3.1 Client

Each Client record represents a brand account (Pandora, Sephora, Dior, etc.).

| Field | Type | Notes |
|-------|------|-------|
| ClientID | UUID | System-generated |
| ClientName | String | e.g., "Pandora ANZ", "Sephora Australia" |
| ClientCode | String (unique) | Short identifier, e.g., PAN, SEP, DIO |
| PrimaryContact | String | Client-side contact name |
| PrimaryEmail | String | |
| SLAProfileID | FK | Links to SLA configuration for this client |
| Notes | Long text | Internal ONYX notes |
| IsActive | Boolean | |
| CreatedAt / UpdatedAt | DateTime (UTC) | |

### 3.2 Store

Each Store belongs to a Client and represents a physical retail location.

| Field | Type | Notes |
|-------|------|-------|
| StoreID | UUID | |
| ClientID | FK | Parent client |
| StoreName | String | e.g., "Pandora Pitt St Mall" |
| StoreCode | String | e.g., PAN-SYD-001 |
| StoreType | Enum | Concept Store, Franchise, Department Store Concession, Pop-Up, Other |
| AddressLine1 | String | |
| Suburb | String | |
| State | Enum | NSW, VIC, QLD, WA, SA, TAS, ACT, NT |
| Postcode | String | |
| Country | String | Default: Australia |
| StoreTimezone | IANA String | e.g., Australia/Sydney |
| StoreManagerName | String / nullable | On-site contact |
| StoreManagerPhone | String / nullable | |
| StoreManagerEmail | String / nullable | |
| IsActive | Boolean | |
| Notes | Long text | Internal PM notes |

---

## 4. Asset Registry

### 4.1 Asset Types

The platform supports the following hardware categories. Each type has a shared base model plus type-specific fields.

| Asset Type | Description | Examples |
|-----------|-------------|---------|
| Digital Screen | Commercial display panel | Samsung QH98C, QM85C |
| Media Player | Content playback device driving one or more screens | Beat MIB 02, Samsung SoC |
| Lightbox | Illuminated static or dynamic display fixture | Window lightboxes, in-store displays |
| Window Fixture | Custom-fabricated display structure (may house screen/lightbox) | EST. Window Display 1500x1110 |
| Infrastructure | Cables, mounts, routers, ancillary hardware | Teltonika 4G Router, HDMI/RS232 cables |

### 4.2 Asset Base Data Model

All assets share the following base fields:

| Field | Type | Notes |
|-------|------|-------|
| AssetID | UUID | System-generated |
| AssetCode | String (unique) | QR/barcode-ready identifier, e.g., PAN-SCR-001 |
| AssetType | Enum | See 4.1 |
| ClientID | FK | Which brand this asset is deployed for |
| StoreID | FK | Current deployment location |
| AssetName | String | Human-readable label |
| Manufacturer | String | e.g., Samsung, Beat A/S, Trison |
| Model | String | e.g., QH98C, MIB 02 |
| SerialNumber | String / nullable | Manufacturer serial |
| PurchaseDate | Date / nullable | |
| WarrantyExpiry | Date / nullable | Alert when approaching |
| InstallDate | Date / nullable | Date first deployed to this store |
| AssetStatus | Enum | Active, Faulty, Offline, Under Maintenance, Decommissioned |
| Location Notes | String / nullable | e.g., "Left window bay, facing Queen St" |
| ParentAssetID | FK / nullable | For assets grouped under a Display Group |
| Notes | Long text | PM internal notes |
| CreatedAt / UpdatedAt | DateTime (UTC) | |

### 4.3 Type-Specific Fields

**Digital Screen (extends base):**
- ScreenSizeInches (Decimal)
- ResolutionWidth, ResolutionHeight (Integer)
- Orientation (Enum: Landscape, Portrait)
- MountType (String: e.g., Floor Totem, Wall Mount, Window Flush)
- TotемSuppliedBy (Enum: Client, ONYX)

**Media Player (extends base):**
- PlayerType (Enum: Standalone Hardware, SoC App)
- CMSPlatform (String / nullable: e.g., Navori QL, Samsung MagicInfo, Beat CMS)
- IPAddress (String / nullable)
- MACAddress (String / nullable)
- FirmwareVersion (String / nullable)
- ConnectedScreenIDs (Array FK): one player may drive one or more screens

**Lightbox (extends base):**
- LightboxDimensions (String: W x H x D in mm)
- LightType (Enum: LED, Fluorescent, Other)
- ContentChangeFrequency (Enum: Static, Weekly, Monthly, Campaign-based)

**Infrastructure (extends base):**
- CableType (String / nullable)
- Length (Decimal / nullable)
- ConnectedFromAssetID, ConnectedToAssetID (FK / nullable)

### 4.4 Display Group (Player-to-Screen Topology)

A Display Group is a logical entity that maps one media player to one or more screens at a store. This models the reality of a single Beat MIB 02 driving multiple window screens simultaneously.

| Field | Type | Notes |
|-------|------|-------|
| DisplayGroupID | UUID | |
| StoreID | FK | |
| GroupName | String | e.g., "Window Bay — North" |
| PlayerAssetID | FK | The media player in this group |
| ScreenAssetIDs | Array FK | Screens driven by this player |
| LayoutDescription | String / nullable | e.g., "3 screens portrait, horizontal array" |
| Notes | String / nullable | |

A single store may have multiple Display Groups. A player belongs to exactly one Display Group. A screen belongs to exactly one Display Group.

### 4.5 Asset Status Lifecycle

```
Active → Faulty (fault reported)
Faulty → Under Maintenance (service job created)
Under Maintenance → Active (service job validated)
Under Maintenance → Decommissioned (PM decision)
Active → Offline (network/power loss, manual flag)
Offline → Active (restored)
Active / Faulty / Offline → Decommissioned (end of life)
```

Status transitions are logged to the audit trail with actor, timestamp, and optional reason note.

### 4.6 Asset QR Code & Label System

Each asset is assigned a unique AssetCode at creation. The system generates a printable QR code label per asset. Scanning the QR code from a mobile browser takes a technician directly to the asset detail page (read-only for guests, full detail for account holders). This eliminates manual asset lookup during site visits.

QR code generation: server-side via PHP QR code library. Labels printable as PDF (A4 sheet, multiple assets per sheet).

---

## 5. Tech Service Job Management

Service jobs are the operational mechanism through which ONYX technicians interact with store assets. A service job documents what was done, to which assets, at which store, with photographic and GPS evidence.

### 5.1 Service Job Data Model

| Field | Type | Notes |
|-------|------|-------|
| JobID | UUID | |
| JobReference | String (unique) | PM-defined alphanumeric |
| JobName | String | Human-readable title |
| JobDescription | Long text | Full scope description |
| ClientID | FK | |
| StoreID | FK | |
| JobType | Enum | Routine Maintenance, Fault Repair, New Installation, Deinstall, Survey, Other |
| JobTimezone | IANA String | From store's timezone |
| ScheduledDate | Date (UTC stored) | |
| ScheduledTime | Time (UTC stored) | |
| EarlyStartWindow | Enum | Anytime, 30 min, 1 hr, 2 hr, 4 hr — configurable per job |
| JobStatus | Enum | See 5.3 |
| AssignedTechnicians | Many-to-Many pivot | See 5.4 |
| AffectedAssetIDs | Array FK | Assets this job addresses |
| ParentJobID | FK / nullable | If remediation sub-job |
| ClientEmail | String / nullable | |
| ClientName | String / nullable | |
| PMAttachments | File references | Briefs, diagrams, photos |
| SLABreached | Boolean (computed) | Auto-flagged if response/resolution outside SLA |
| CreatedAt / UpdatedAt | DateTime (UTC) | |

### 5.2 Affected Assets

A service job explicitly references which assets it is addressing. This creates the service history linkage:

- PM selects one or more assets from the store's registered asset inventory when creating the job
- Each affected asset receives a service history entry upon job validation
- Asset status changes (e.g., Faulty → Under Maintenance) are triggered automatically when a job referencing that asset is created
- Asset status returns to Active automatically when the job is validated (unless PM overrides)

### 5.3 Job Status State Machine

| Status | Triggered By | Description |
|--------|-------------|-------------|
| Draft | PM creates job | Not yet sent to technician |
| Invited | PM sends invitation | Technician emailed the signed job link |
| Accepted | Technician accepts via link | Confirmed attendance |
| In Progress | First technician clicks Start Job | On-site execution begun |
| Completed | All assigned technicians submit | All after-photos submitted |
| Validated | PM marks validated | PM reviewed and approved |
| Requires Remediation | PM flags issue | Sub-job created |
| Cancelled | PM cancels | Soft-deleted from active views |

Permitted transitions mirror the TradifyHQ field job model (see the Field Job Management SRA v1.2 for full transition rules).

### 5.4 Multi-Technician Assignment

A service job supports multiple assigned technicians simultaneously. Each has an independent invite lifecycle: invited → accepted → started → completed. Job-level status logic:

- Enters **In Progress** when the first technician starts
- Reaches **Completed** only when all assigned technicians submit, OR PM forces completion with a reason note

### 5.5 Job Hierarchy

Identical to the Field Job Management SRA v1.2 hierarchy model:

| Level | Type | Executed By | Max Children |
|-------|------|-------------|-------------|
| 0 (Root) | Parent Job / Campaign | PM only | Unlimited Sub-Jobs |
| 1 | Sub-Job (Store Visit) | One or more Technicians | Max 1 Remediation Sub-Job |
| 2 (max) | Remediation Sub-Job | One or more Technicians | None |

**Standalone Jobs** (single store, no campaign parent) are fully supported.

---

## 6. Technician Mobile Workflow (5 Screens)

The technician accesses the job via signed URL link. Interface is mobile-first, optimised for 375px–768px viewports.

### Screen 1 — Job Overview (Pre-Start)

- Displays: JobName, JobDescription, Store name + address, scheduled date/time (in job timezone)
- Affected assets list: each asset shown with AssetCode, AssetType, current status, and location notes — tappable to view asset detail
- Primary CTA: **Start Job** (full-width, high-contrast)
- Alert callout: "Start Job only when you are on site. Your GPS location will be recorded."
- Calendar save options: Google Calendar URL + .ics download
- Early start window enforcement: button disabled with message if outside window

### Screen 2 — Before Photos + GPS Capture

- Triggered on Start Job tap
- Native camera capture (HTML input[type=file][capture=environment])
- Minimum 1 photo required before continuing
- GPS captured via Geolocation API; failure logged but non-blocking
- StartTimestampUTC recorded server-side
- If GPS denied: warning shown, technician confirms to proceed
- Thumbnail preview with individual photo removal
- Cancel returns to Screen 1, discards photos, reverts status to Accepted

### Screen 3 — Job Briefing & Asset Reference

- Full JobDescription, store address, all PM attachments
- **Asset panel:** expandable list of affected assets — each shows AssetCode, model, serial number, current status, location notes, and QR code. Technician can update an asset's status directly from this screen (e.g., mark as Faulty during inspection)
- Read-only for PM attachments
- Sticky bottom bar: **Complete Job** CTA only forward action

### Screen 4 — After Photos, Asset Outcomes + Completion

- After photo capture (minimum 1)
- EndGPS + EndTimestampUTC recorded
- **Asset outcome section:** for each affected asset, technician selects the post-service status (Active, Still Faulty, Decommissioned, Replaced) and optionally adds a per-asset note (max 500 chars)
- Completion Notes field (optional, max 1000 chars) for general job notes
- Submit Job button: disabled until at least 1 after-photo added
- Cancel returns to Screen 3; after-photos and notes discarded

### Screen 5 — Job Summary (Post-Completion)

- Read-only summary: JobReference, store, scheduled and actual times, duration, before/after photos, completion notes
- Asset outcome summary: each affected asset's new status post-service
- Account holder: link to job history dashboard
- Guest: prompt to create account

---

## 7. Asset Service History

Every validated service job contributes an immutable service history record per affected asset:

| Field | Notes |
|-------|-------|
| AssetID | |
| ServiceJobID | |
| ServiceDate | |
| TechnicianID(s) | |
| JobType | |
| StatusBefore | Asset status at job creation |
| StatusAfter | Asset status at job validation |
| TechnicianNotes | Per-asset outcome notes from Screen 4 |
| BeforePhotoURLs | |
| AfterPhotoURLs | |

This record is append-only at the application level. It is visible from the asset detail page as a chronological service log.

---

## 8. Store Dashboard

Each store has a dedicated PM-facing dashboard showing:

- Store metadata (type, address, contact)
- Full asset inventory table: filterable by type and status
- Display Group topology diagram (player → screens)
- Active and recent service jobs
- Open faults (assets with Faulty/Offline status)
- Last service visit date per asset
- SLA compliance status for any open jobs

---

## 9. Client Dashboard

PM-facing dashboard aggregated at the client level:

- Store count by state
- Asset count by type and status
- Open faults (count and list, drillable to store)
- Overdue service jobs (SLA breached)
- Recent service activity
- Export: CSV of all assets + status for client

---

## 10. SLA Management

### 10.1 SLA Profile

Each client is assigned an SLA profile:

| Field | Notes |
|-------|-------|
| SLAProfileID | |
| ClientID | FK |
| AcknowledgementWindow | e.g., 2 business hours |
| OnSiteResponseMetro | e.g., Next business day |
| OnSiteResponseRegional | e.g., 1–2 business days |
| ResolutionTarget | e.g., 5 business days |
| MonitoringCoverage | e.g., 24/7 automated, Business hours only |

### 10.2 SLA Tracking

- When a service job is created (fault type), SLA clock starts
- System computes breach risk: flags jobs approaching or exceeding SLA windows
- SLA status visible on job list, client dashboard, and store dashboard
- Business hours logic: SLA clock excludes weekends and public holidays (state-specific)
- Australian public holiday calendar integrated per state

---

## 11. Installer / Technician Management

Identical model to the Field Job Management SRA v1.2 Installer Directory (Section 4.5), with the following additions:

- **Specialty categories** include: AV Installation, Digital Signage, Electrical, Retail Fit-out, Lightbox Service, Network/Connectivity
- **Certifications field** (multi-select, free-text): e.g., White Card, EWP, Working at Heights
- **Preferred clients** (multi-select): technicians familiar with specific client environments
- **Asset competency** (optional note): specific hardware the technician is experienced with (e.g., Samsung commercial displays, Beat MIB 02)

---

## 12. Notifications

### 12.1 PM Notifications

PM receives configurable in-app and email notifications for:

- Job status changes (Completed, Requires Remediation)
- SLA breach warnings (configurable threshold: e.g., 80% of window elapsed)
- SLA breach events
- Asset status changes (manual or system-triggered)
- Warranty expiry approaching (configurable: 30/60/90 days before)
- New fault reported by technician

### 12.2 Technician Notifications

- Job invitation email with signed URL
- Job reminder (configurable: 24h before scheduled time)
- Link expiry warning

---

## 13. Reporting

### 13.1 Available Reports

| Report | Scope | Export |
|--------|-------|--------|
| Asset Register | Client / State / Store / Type | CSV, PDF |
| Asset Status Summary | Client / State | CSV |
| Service History per Asset | Per asset | PDF |
| Service History per Store | Per store | PDF, CSV |
| Open Faults | Client / State | CSV |
| SLA Compliance | Client / Date range | CSV |
| Technician Hours | Per technician / Date range | CSV |
| Warranty Expiry Forecast | Client / Date range | CSV |

### 13.2 Display Group Report

Per store, a topology report showing each Display Group: player model + serial → screen(s) model + serial, current status of all components, last service date. Designed to be included in client reporting packs.

---

## 14. Non-Functional Requirements

### 14.1 Performance

- All installer-facing screens: Time to Interactive ≤ 3 seconds on 4G
- PM dashboard with up to 1,000 active assets per client: load within 2 seconds
- Photo uploads: real-time progress feedback, non-blocking
- Email dispatch: asynchronous (queued)

### 14.2 Responsiveness & Device Support

- Technician-facing: fully functional from 320px viewport width
- PM-facing: functional at 768px+, optimised at 1280px+
- Browsers: current Chrome, Safari, Firefox, Edge
- Explicit testing: iOS Safari and Android Chrome for photo capture and QR scanning

### 14.3 Security

- All routes protected by appropriate middleware (auth, signed URL, role checks)
- Signed job URLs validated server-side on every request
- File MIME type and extension validation against allow-list before storage
- Files not publicly accessible without signed URL with expiry
- CSRF protection on all forms
- SQL injection and XSS protection via Laravel built-ins
- Rate limiting on invitation sending, login, photo upload, and QR lookup endpoints
- GPS data access restricted to PM and assigned technician only

### 14.4 Scalability

- Laravel Storage abstraction used throughout — cloud driver swap is config-only
- Queue workers independently scalable
- Database indexed on: ClientID, StoreID, AssetStatus, JobStatus, TechnicianID, ScheduledDate

### 14.5 Audit Trail

Full async audit log across all entities. Every significant action logged with: actor (user ID + role), action type, target entity (model + ID), before/after values, IP, user agent, UTC timestamp.

Key events logged:
- Asset creation, edit, status change, decommission
- Display Group creation and modification
- Service job state transitions
- Technician invitation and acceptance
- Photo uploads
- SLA breach events
- Client/store/asset CRUD

Audit logs: append-only, read-only at application level. PM can filter audit log by asset, store, or job.

### 14.6 Reliability

- GPS failure: non-blocking, logged, visible to PM
- Photo upload failure: retry without restarting workflow
- Offline support: not in scope for v1, architecture must not preclude future PWA
- Email delivery failures: logged with context for PM resend
- Concurrent uploads from multiple technicians: no race conditions

### 14.7 Accessibility

- Technician-facing UI: WCAG 2.1 AA
- Minimum touch target: 44×44px on mobile

---

## 15. System Module Summary

| Module | Primary User | Key Functions |
|--------|-------------|---------------|
| Client Management | PM | CRUD clients, SLA profiles |
| Store Management | PM | CRUD stores, store dashboard |
| Asset Registry | PM | CRUD assets, type-specific fields, QR labels |
| Display Group Manager | PM | Player-to-screen topology mapping |
| Asset Status Lifecycle | System + PM | Status transitions, history |
| QR Code System | System | Label generation, mobile asset lookup |
| Service Job Management | PM | CRUD jobs, state machine, asset linkage |
| Technician Invitation | PM + Technician | Signed URL, calendar ICS |
| Technician Mobile Workflow | Technician | 5-screen flow, GPS, photos, asset outcomes |
| Job Validation | PM | Review, validate, or flag remediation |
| Service History | System | Append-only per-asset and per-store log |
| SLA Management | PM + System | SLA profiles, clock, breach alerts |
| Technician Directory | PM | CRUD technician profiles, specialty, certs |
| Reporting | PM | Asset register, service history, SLA, hours |
| Notifications | System | Email + in-app for PMs and technicians |
| Audit Trail | System | Async, append-only, full entity coverage |
| File Storage | System | Local disk v1, cloud-swappable abstraction |
| Email Templates | PM | Blade-style templates with variable support |
| Settings | PM | SLA thresholds, early start window, expiry config |

---

## 16. Open Questions

1. **Client visibility portal (v2 scope?):** Should clients (e.g., Pandora's Rosie) have a read-only login to view their own asset register and service history, without PM access? If yes, this shapes the role model from day one.

2. **Fault reporting by store managers:** Should store managers be able to log a fault (e.g., screen is black) via a public-facing form or link, which then auto-creates a service job in the platform? This would reduce PM handling of inbound fault calls.

3. **Asset import:** Given existing deployments (Pandora, Dior, Sephora), initial asset data will need to be bulk-imported. CSV import with field mapping UI, or manual entry only?

4. **Automated network monitoring integration:** Beat MIB 02 and Samsung SoC players have remote monitoring capability. Should the platform receive uptime/fault alerts from these devices to auto-update asset status (Offline → flag), or remain manual-only in v1?

5. **Multi-store campaigns:** When a service job covers multiple stores simultaneously (e.g., a national rollout across 20 Pandora stores), the Parent Job model handles this. Confirm: should a Parent Job be scoped to a single client only, or can a campaign span multiple clients (unlikely, but worth ruling out)?

6. **Photo storage sizing:** With original-quality photos across potentially hundreds of store assets and service jobs, disk/cloud storage volume needs to be estimated before v1 deployment. Agree storage budget and alert thresholds with ONYX.

---

## 17. Assumptions

- All users operate in Australia. International timezone support not required in v1.
- The platform is single-tenant, serving ONYX Visual and its clients. Client data is logically separated at the application layer, not via database schema separation.
- Technicians will predominantly access the system via smartphone (iOS or Android), default mobile browser.
- PM has reliable desktop/laptop access.
- File storage uses local disk on application server for v1. Persistent volume mount required for Docker deployments.
- Initial asset data for existing clients will be provided by ONYX in CSV format for bulk import.
- Australian public holiday data will be sourced from an open API or static calendar per state for SLA business-hours calculation.

---

## 18. Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | June 2026 | ONYX Visual | Initial draft — multi-client asset registry + tech service management platform |

---

*End of Document*
