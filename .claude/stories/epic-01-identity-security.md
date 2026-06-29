# EPIC-01 — Identity, Roles & Multi-Client Security

> The security spine. Built **before any tenant data** so every CRUD surface that follows is
> guarded by role **and** `client_id` scope from its first commit (Engineering Bar #1; SRA §2.2,
> §14.3). The future client portal (Rosie, §16 Q1) is handled defensively here so adding her in
> v2 is config, not rework.

Related: ADR-001 (technician signed-URL contract), US-00.4 (scoping foundation), US-00.5 (audit).
Sprint: 1.

---

## US-01.1 — PM authentication (login, logout, password reset)

**As** Yeis (PM)
**I want** to sign in securely with email/password and recover my password
**So that** only authorised ONYX staff reach PM functionality.

**Estimate:** 3 · **Priority:** P0 · **Depends on:** US-00.1, US-00.3 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** valid credentials, **when** a PM logs in, **then** they reach the PM dashboard; logout
  ends the session.
- **Given** invalid credentials, **when** login is attempted, **then** a plain, non-blaming error
  shows (no "user not found" enumeration) and the attempt is rate-limited (§14.3).
- **Given** a forgotten password, **when** reset is requested, **then** a signed, expiring reset
  email is sent via the queue (§2.3) and resets the password on use.
- **Given** an unauthenticated visitor, **when** they hit any PM route, **then** they are
  redirected to login by middleware.

### Engineering Bar checklist
- **Secure:** Breeze/Fortify; bcrypt/argon hashing; rate limiting on login + reset; no account
  enumeration; secure+httpOnly cookies; CSRF on forms; reset tokens signed + expiring.
- **Clean:** use the framework auth scaffold, don't hand-roll; reuse the base layout shell.
- **UX:** clear single primary action; inline `@error` validation; plain-language errors;
  designed loading state on submit; 44px targets.
- **No guessing:** confirm Fortify vs Breeze against the layout already built in US-00.3.

### Definition of Done
Login/logout/reset work; rate limiting + no enumeration verified by tests; queued reset email;
auth middleware guards PM routes; happy-path integration test.

---

## US-01.2 — Role model & RBAC

**As** the engineering team
**I want** a clear role model (`pm`, `technician`, and a defined-but-dormant `client_user`)
**So that** every action can be authorised against the actor's role (SRA §2.2; §16 Q1).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-01.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a user, **when** created, **then** they hold exactly one role from a typed enum:
  `pm`, `technician`, `client_user`.
- **Given** a `pm`, **when** acting, **then** they have full CRUD capability (subject to later
  policies); **given** a `technician`, **then** they only reach technician capabilities.
- **Given** `client_user`, **when** the role exists, **then** it is **defined and scaffolded but
  not yet routable** (Rosie is v2) — its presence must not open any v1 surface.
- **Given** a role check, **when** performed, **then** it is centralised (enum + helper), not
  string-compared ad hoc across the codebase.

### Engineering Bar checklist
- **Secure:** roles are a typed enum (no magic strings); `client_user` cannot reach PM/technician
  capabilities; defence-in-depth — role checks live in policies (US-01.3), not just UI.
- **Clean:** single role enum + helpers reused everywhere; no duplicated role logic.
- **UX:** N/A directly; navigation later hides actions a role can't perform (UI layer of d-i-d).
- **No guessing:** Confirmed — v1 ships only `pm` + `technician` active; `client_user` defined but
  inert (ONYX, 2026-06-24, §16 Q1).

### Definition of Done
Role enum + helpers; capability mapping documented; tests prove role boundaries; `client_user`
present but inert; no magic-string role checks anywhere.

---

## US-01.3 — Authorisation policies + `client_id` scope enforcement

**As** the engineering team (protecting every tenant)
**I want** Laravel policies on every model that combine **role + `client_id` scope**
**So that** no actor can read or mutate another tenant's data or escalate (Engineering Bar #1).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-00.4, US-01.2 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** any tenant-scoped model, **when** an action is attempted, **then** a policy authorises
  it against **both** the actor's role **and** their `client_id` scope.
- **Given** a `client_user` (test fixture for Rosie), **when** they target another client's
  record, **then** the policy **denies** — proven by test.
- **Given** a PM, **when** they act on any ONYX client, **then** the policy allows it, but denies
  anything outside ONYX's data.
- **Given** a controller/Form Request, **when** it handles a mutation, **then** it calls
  `authorize()` **and** validates input — and the **DB constraints from US-00.4 still hold** even
  if a policy were bypassed (layered guarantee).
- **Given** a policy, **when** written, **then** it is the single authorisation source for that
  model (no scattered inline checks).

### Engineering Bar checklist
- **Secure (the heart of this epic):** authorise by role **and** `client_id` for **every**
  portal/admin action; verify the full stack UI + Request + Policy + DB for at least one model
  end-to-end as the reference pattern. Never trust `client_id` from the client.
- **Clean:** one policy per model; a reusable scope-check helper; documented reference pattern
  other epics copy.
- **UX:** unauthorised attempts return a clear 403 surface, not a stack trace.
- **No guessing:** verify relations/columns with tinker before writing scope checks.

### Definition of Done
Policy pattern documented as the reference for all later CRUD; cross-tenant deny tests pass for
`client_user`; one model proven secure across all four layers; no inline ad-hoc auth checks.

---

## US-01.4 — Signed-URL technician guest access foundation

**As** Michael (guest technician)
**I want** to reach my assigned job through a secure link with no login
**So that** I can work with minimal friction while ONYX keeps the door safe (SRA §2.2, §14.3;
ADR-001).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-01.2 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a job invitation, **when** a signed URL is generated, **then** it is **expiring**,
  scoped to one technician + one job, and **validated server-side on every request** (§14.3).
- **Given** a tampered or expired link, **when** opened, **then** access is denied with a clear,
  plain-language page (and a resend path later in US-09.4).
- **Given** a valid guest link, **when** opened, **then** the technician gets **only** that job's
  permitted data — never another job, asset, or tenant (scope honoured even without a login).
- **Given** the guest endpoints, **when** hit, **then** they are **rate-limited** (§14.3) and CSRF
  is handled appropriately for the technician POST checkpoints (ADR-001 contract).
- **Given** GPS data, **when** accessed, **then** only the assigned technician and PM can reach it
  (§14.3).

### Engineering Bar checklist
- **Secure:** signed + expiring + single-purpose URLs; server-side validation every request;
  rate limiting; scope enforced for an *unauthenticated* actor (hardest case); GPS access
  restricted. Defence in depth: even the guest checkpoint endpoints validate inputs and the DB
  constraints hold.
- **Clean:** one signed-link service reused by invitations (US-09.2) and the mobile flow
  (EPIC-10); thin, explicit endpoint contract per ADR-001.
- **UX:** expired/invalid link page is plain-language, non-blaming, mobile-first, with a clear
  next step.
- **No guessing:** confirm token lifetime + scope shape against §14.3 before building.

### Definition of Done
Signed-URL service with expiry + per-(technician,job) scope; tamper/expiry denied with a friendly
page; rate-limited; guest scope tested; GPS access restriction tested; documented as the contract
EPIC-09/10 build on.

---

## US-01.5 — Security middleware & rate-limiting baseline

**As** the engineering team
**I want** the app-wide security middleware and rate limits configured once
**So that** every route inherits the SRA §14.3 protections without per-feature reinvention.

**Estimate:** 3 · **Priority:** P0 · **Depends on:** US-01.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** any route, **when** registered, **then** it sits behind the correct middleware group
  (auth / signed / role) — documented mapping of group → purpose.
- **Given** sensitive endpoints (login, invitation send, photo upload, QR lookup), **when** hit
  repeatedly, **then** **named rate limiters** throttle them (§14.3).
- **Given** any response, **when** returned, **then** baseline security headers are present
  (HSTS in prod, X-Content-Type-Options, frame options, referrer policy) and CSRF is enforced on
  state-changing requests.
- **Given** file-serving, **when** any file is requested, **then** it requires a signed URL — no
  public file routes exist (reinforces ADR-002).

### Engineering Bar checklist
- **Secure:** centralised middleware + named limiters; security headers; CSRF; no public file
  routes; Laravel SQLi/XSS defaults relied on, not bypassed.
- **Clean:** limiters defined once and referenced by name; no duplicated throttle config.
- **UX:** throttled responses return a plain "try again shortly" message, not a raw 429.
- **No guessing:** limiter thresholds chosen against §14.3 expectations; document them.

### Definition of Done
Middleware groups + named limiters documented and applied; security headers verified; CSRF on
all mutations; no public file routes; throttle UX is friendly; tests cover a throttled endpoint.
