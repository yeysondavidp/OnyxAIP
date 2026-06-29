# EPIC-07 — QR Code & Label System

> Turns every asset's `AssetCode` into a scannable shortcut. A technician scans a label on site
> and lands on the correct asset detail page — role-aware, tenant-safe, rate-limited. PMs can
> generate printable A4 PDF label sheets for a batch of assets in one action.

Related: EPIC-04 (Asset Registry — `AssetCode` source), EPIC-01 (role-aware access, signed-URL
middleware, rate-limiting baseline). Sprint: 4.

---

## US-07.1 — Role-aware QR code per asset resolving to asset detail

**As** Yeis (PM), Sneider (account technician), and Michael (guest technician)
**I want** every asset to have a unique QR code that I can scan to go directly to that asset's
detail page
**So that** I can look up an asset on site without manual searching, and see the right level of
detail for my role without being able to reach another tenant's data.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-04.1, US-01.2, US-01.3, US-01.4, US-01.5 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** an asset is created, **when** its record is saved, **then** a QR code image is
  generated server-side (PHP QR library) encoding the asset's lookup URL and stored via the
  Storage abstraction in the `app_storage` volume.
- **Given** a PM (Yeis) scans or follows the QR URL while authenticated, **when** the page
  loads, **then** they see full asset detail **plus** an Edit action for that asset.
- **Given** an account technician (Sneider) scans the QR URL while authenticated as
  `technician`, **when** the page loads, **then** they see full asset detail (AssetCode, type,
  model, serial, status, location notes, service history summary) with **no edit controls**.
- **Given** a guest (Michael) scans the QR URL with no session, **when** the page loads,
  **then** they see a **read-only** mobile-first asset detail view (AssetCode, type, model,
  status, location notes) with no edit controls and **no service history notes that contain
  another technician's GPS or personal data**.
- **Given** a guest scan, **when** the lookup resolves, **then** the asset detail page is
  **tenant-isolated**: the guest sees only the asset belonging to the encoded `AssetCode` and
  the response never exposes another client's records, even if the URL is manually modified.
- **Given** an `AssetCode` that does not exist or belongs to a decommissioned asset,
  **when** the QR URL is opened, **then** a plain-language not-found page is returned (HTTP 404)
  that does not reveal whether the code ever existed or which client owned it.
- **Given** the QR image, **when** displayed on the asset detail page or in a PDF label,
  **then** it is scannable by both iOS Safari and Android Chrome (§14.2).
- **Given** the asset detail page opened via QR scan on a smartphone, **when** rendered at
  320px viewport, **then** all content is legible, all tap targets are ≥44×44px, and the
  primary action (Edit for PM, back for technician/guest) is obvious (§14.7).

### Engineering Bar checklist

- **Secure:** QR lookup is unauthenticated but **never leaks tenant data** — the lookup
  controller resolves only by `AssetCode`, then enforces client-scoped visibility rules:
  authenticated actors go through the normal Policy (role + `client_id`); unauthenticated
  guests see only the fields explicitly allow-listed for guest display. The DB `assets` table
  has a unique index on `asset_code` (US-04.1 enforces this — verify before relying on it).
  QR image files are served via signed URL only (ADR-002); the public path is the lookup
  redirect, not the image file itself. `client_id` is never trusted from the URL — it is
  always derived from the resolved asset record.
- **Clean:** QR generation is a single `QrCodeService` (or equivalent) called at asset
  creation and from the label sheet generator (US-07.2) — not duplicated. Asset detail view
  uses the existing PM and mobile base shells (US-00.3). Guest field allow-list is a named
  constant, not scattered conditionals.
- **UX:** Mobile-first asset detail loads in ≤3 s on 4G (§14.1); clear "read-only" visual
  affordance for guest/technician views (no phantom edit buttons); plain-language not-found
  page; designed loading and error states; Australian English throughout.
- **No guessing:** Read the `assets` migration from EPIC-04 to confirm `asset_code` column
  name, uniqueness constraint and whether the QR image path column already exists before
  writing any code. Verify with tinker that the Storage path resolves to `app_storage`.

### Definition of Done

QR generated at asset creation and stored via Storage abstraction; lookup route resolves
asset by `AssetCode`; three role variants (PM edit, technician read-only, guest read-only)
render correctly at 320px; cross-tenant isolation tested (a guest URL for Client A returns
404 if manually pointed at Client B's `AssetCode`); not-found page is plain-language; QR
scannable on iOS Safari and Android Chrome; audit log entry written for every guest QR scan
(actor = anonymous, action = `asset.qr_scan`, target = asset UUID, IP + user agent recorded).

---

## US-07.2 — Printable PDF label sheet (A4, multi-asset)

**As** Yeis (PM)
**I want** to select a set of assets and download a single A4 PDF sheet containing one label
per asset, each showing the QR code and human-readable asset details
**So that** I can print and physically attach labels to hardware before or after a deployment,
eliminating manual lookup in the field.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-07.1, US-04.1, US-01.3 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis is on the asset list for a store or client, **when** she selects one or more
  assets via checkboxes and triggers "Generate Label Sheet", **then** a server-side PDF is
  generated and a download begins (or a signed URL is returned for the generated file).
- **Given** the generated PDF, **when** printed on A4, **then** it contains one label per
  asset; each label includes: the QR code image, `AssetCode`, `AssetName`, `AssetType`,
  `Manufacturer`, `Model`, and `StoreName` in legible text (minimum 10pt equivalent).
- **Given** multiple assets selected, **when** the PDF is generated, **then** labels are laid
  out in a grid (≥2 per row) that fits A4 without clipping; the layout is visually clean and
  separates labels clearly.
- **Given** the PM selects assets across two different clients, **when** generation is
  attempted, **then** the request is **denied** (a PM may only generate a sheet for assets
  within their permitted `client_id` scope) and a plain-language error is shown.
- **Given** the generated PDF file, **when** stored, **then** it is saved via the Storage
  abstraction (not written to the public filesystem) and served back to the PM via a
  **signed URL with a short expiry** (e.g. 10 minutes) rather than a permanent public path.
- **Given** the PM's asset detail page for a single asset, **when** they click "Download Label",
  **then** a single-asset PDF label is generated and downloaded following the same rules.
- **Given** no assets are selected, **when** generation is triggered, **then** a clear
  inline error message is shown before any server request is made.

### Engineering Bar checklist

- **Secure:** PDF generation is authorised via the existing Asset Policy — each `asset_id` in
  the selection is individually authorised against the actor's role **and** `client_id` scope
  before being included in the PDF (a bad actor cannot inject a foreign `asset_id` into the
  batch). The generated PDF path is not guessable: use UUID-named files. Signed URL with expiry
  (ADR-002 guardrail). No asset data appears in the PDF filename itself.
- **Clean:** PDF generation is handled by a dedicated `LabelSheetService` (or Mailable/job)
  that reuses `QrCodeService` from US-07.1 — no copy-paste of QR logic. Use a well-supported
  PHP PDF library (e.g. DomPDF or Snappy); document the choice. Temporary working files (if
  any) are cleaned up after generation. `DB::table()` bulk read, not N+1 Eloquent in the loop.
- **UX:** "Generate Label Sheet" action is a clear primary button in the asset list; a loading
  state prevents double-submit; on completion a plain success message with a download link
  replaces the button; any error (e.g. no assets selected, policy denial) is inline, specific,
  and in Australian English; the PDF label layout is tidy enough to hand to a client.
- **No guessing:** Confirm the PDF library is installed and produces A4 output at the correct
  page size before building the layout. Confirm `storage/app/labels/` is under the
  `app_storage` persistent volume by reading ADR-002 and the Docker Compose config. Do not
  assume the QR image path column exists on `assets` — verify with tinker or read the
  migration.

### Definition of Done

Single-asset and batch label PDFs generate correctly; each label contains all required fields
plus a scannable QR code; PDF served via signed URL with expiry; policy authorisation tested
(cross-client batch is denied); no N+1 queries; temporary files cleaned up; audit log entry
written (`asset.label_pdf_generated`, list of asset UUIDs, PM actor, UTC timestamp); PM UI
has loading + success + error states designed.

---

## US-07.3 — QR/asset lookup endpoint: rate limiting and abuse protection

**As** the engineering team (protecting Yeis's platform and every client's data)
**I want** the public-facing QR lookup endpoint to be rate-limited and hardened against abuse
**So that** the platform meets §14.3 security requirements and a bad actor cannot enumerate
assets or mount a denial-of-service attack through the QR scan path.

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-07.1, US-01.5 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** the QR lookup route, **when** the same IP makes more than the configured threshold
  of requests in a rolling window, **then** subsequent requests receive HTTP 429 with a plain,
  non-blaming "Too many requests — please try again shortly" message (§14.3; §14.7).
- **Given** a throttled response, **when** returned, **then** it includes a `Retry-After`
  header so compliant clients can back off correctly, and the body is a designed mobile-first
  page (not a raw Laravel exception dump).
- **Given** an `AssetCode` that does not match any record, **when** the lookup runs, **then**
  the response time is **constant** (no timing oracle distinguishing "never existed" from
  "wrong client") and the HTTP response is always 404 with the same not-found page body.
- **Given** a lookup request, **when** it completes (hit, miss, or throttled), **then** a
  structured log entry is written synchronously (not queued — this is observability, not
  audit): IP, user agent, `AssetCode` attempted, outcome (`hit` / `miss` / `throttled`),
  response time ms. The log entry **never** includes the asset's `client_id`, `ClientName`,
  or any tenant-identifying data in a miss or throttled case.
- **Given** the named rate limiter for QR lookup, **when** defined, **then** it is the same
  limiter referenced by US-01.5's middleware baseline — not a duplicate definition.
- **Given** a correctly resolved lookup that redirects to the asset detail page,
  **when** the redirect occurs, **then** it is an HTTP 302 to the asset detail route (not an
  expose of raw model data in the lookup response).
- **Given** a request with a malformed `AssetCode` (e.g. containing path-traversal characters,
  SQL metacharacters, or exceeding max length), **when** received, **then** it is rejected with
  a 422 before any DB query runs, and the attempt is logged as `malformed`.

### Engineering Bar checklist

- **Secure:** named rate limiter applied via middleware (not ad-hoc inside the controller);
  limiter keyed by IP (unauthenticated path — no user to key on); constant-time response for
  misses to prevent enumeration; input validated (max length, character allow-list) before DB
  touch; lookup log never leaks tenant data; the 302 redirect destination is the authorised
  asset detail route — the controller does not return raw asset JSON on the QR path.
- **Clean:** one named limiter (`qr-lookup`) defined in `RouteServiceProvider` (or equivalent)
  and applied to the route — reuses the US-01.5 limiter infrastructure; no inline `RateLimiter`
  call inside the controller; lookup logging is a single structured log channel call, not
  duplicated across branches; the not-found and throttled Blade views reuse the mobile shell
  (US-00.3).
- **UX:** 429 page is mobile-first, displays a human-readable retry countdown if the
  `Retry-After` value is short enough to be meaningful (≤60 s), and has ≥44px tap targets;
  404 not-found page is plain-language and non-blaming ("We couldn't find that asset. Check
  the label and try again.") — no technical jargon; both pages render cleanly at 320px.
- **No guessing:** confirm the `qr-lookup` limiter threshold against §14.3 expectations and
  document the chosen value (e.g. 30 requests/minute per IP) in the route or limiter
  definition comment before shipping. Confirm the structured log channel name by reading the
  existing `config/logging.php` rather than assuming a channel exists.

### Definition of Done

Named rate limiter applied and tested (a test hits the threshold and asserts 429 +
`Retry-After`); constant-time miss response tested (timing variance < 50 ms between miss
variants); malformed input rejected before DB query (assert no DB query fired); structured
lookup log written for hit/miss/throttled/malformed outcomes and confirmed to contain no
tenant data on non-hit paths; 404 and 429 pages render correctly at 320px; limiter threshold
value documented in code; no duplicate limiter definition; happy-path integration test (valid
scan → 302 to asset detail).
