# ADR-001 — Frontend stack: Blade/Livewire for PM, Alpine-first for the technician flow

- **Status:** Accepted
- **Date:** 2026-06-24
- **Deciders:** ONYX Visual (engineering)
- **Context source:** SRA §2.3, §6, §14.1, §14.6

## Context

The platform has two very different frontends:

- **PM (desktop):** CRUD-heavy — clients, stores, assets, jobs, dashboards, reports. Round-trips
  are acceptable; productivity and a single language matter.
- **Technician (mobile):** the 5-screen workflow (§6) — camera capture, GPS, photo
  preview/removal, multi-step wizard. Highly interactive, runs on **4G inside retail stores**
  (often signal-poor back-of-house). NFR §14.1 sets Time-to-Interactive ≤ 3s on 4G. NFR §14.6
  says offline is out of scope for v1 but **the architecture must not preclude a future PWA**.

The risk with Livewire is that **every interaction is a server round-trip**. For CRUD that is
fine; for a camera/GPS/photo wizard on flaky mobile networks it is the wrong default, and
Livewire is fundamentally **hostile to offline/PWA** because it requires connectivity per
interaction.

## Decision

1. **PM surfaces → Blade + Livewire** (with Alpine for local sprinkles). This is the default for
   everything desktop/CRUD.
2. **Technician mobile workflow → Alpine.js-first "islands".** All client-side interactions
   (camera, GPS, photo thumbnails/removal, wizard navigation, validation) run **locally with no
   server round-trip**. The server is contacted only at **meaningful business checkpoints**:
   - `Start Job` → records `StartTimestampUTC` + start GPS server-side, flips status.
   - `Submit before-photos` / `Submit after-photos` → upload endpoints.
   - `Complete Job` → records `EndTimestampUTC` + end GPS, asset outcomes, completion notes.
3. The technician flow talks to the server through a **small, explicit endpoint contract**
   (a handful of signed POST routes), not through ad-hoc Livewire component state.

## Consequences

**Positive**
- 4G performance stays within budget — the laggy interactions never hit the network.
- The technician flow can be **extracted into a PWA/SPA later without touching PM code**,
  because its server contract is already a thin set of endpoints (§14.6 future-proofed).
- One backend language (PHP) for the bulk of the app.

**Negative / costs**
- Two interaction models in one codebase — contributors must know *which* surface they're on.
  Mitigation: the technician flow lives in its own directory namespace and this ADR is linked
  from every technician-flow story.
- Photo upload, GPS and offline-queueing logic is hand-written Alpine/JS rather than
  free-from-Livewire. Accepted: it is exactly the logic that must not round-trip.

## Guardrails for implementers

- Do **not** implement camera/GPS/photo-preview as Livewire actions. Keep them client-side.
- Keep the technician server contract small and signed (§14.3). Validate every signed URL and
  file upload server-side regardless of client-side checks (defence in depth — see Engineering
  Bar #1).
- Don't leak PM Livewire components into the technician namespace.

## Related

- ADR-002 (deployment) — the small-server footprint reinforces keeping the JS build lean.
- Stories: EPIC-10 (Technician Mobile Workflow).
