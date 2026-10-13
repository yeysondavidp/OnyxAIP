# EPIC-20 — Agent Access (MCP endpoint)

> Lets an AI agent (Claude Code or any MCP client) do routine PM data work — read the register,
> register assets from a visit report, attach evidence to a job — through an authenticated,
> audited, ability-scoped MCP endpoint instead of SSH + tinker. Raised 2026-10-13 after the
> Pandora Miranda data entry had to be done over SSH. Design: **ADR-004**.

Related: US-01.2/US-01.3 (roles, policies, scope), US-00.5 (audit), US-01.5 (rate limiting),
EPIC-04, EPIC-08, EPIC-16 (settings), EPIC-17 (audit viewer). Sprint: 12 (see
`sprints/sprint-plan.md`).

---

## US-20.1 — Agent service accounts and access tokens

**As** Yeis (PM)
**I want** to create an agent account and issue it an expiring, ability-scoped token
**So that** an agent can work in the platform under its own name, and I can cut it off at once.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-01.2, US-16.1 · **Status:** ✅ Done (PR #14)
(after ADR-004 is accepted)

### Acceptance criteria
- **Given** Settings → "Agent access", **when** a PM creates an agent (name, client scope: one
  client or all, abilities from `register:read`, `assets:write`, `jobs:write`,
  `attachments:write`, expiry ≤ 90 days), **then** a service-account user with role `agent` and
  a Sanctum token are created and the token is shown **once** with copy instructions.
- **Given** the list, **then** each token shows abilities, scope, expiry, created by, last used;
  **Revoke** takes effect on the next request.
- **Given** an `agent` user, **then** it cannot log in to the web UI (no password, login
  refused) and can never reach users, tokens, settings or email templates.

### Engineering Bar checklist
- **Secure:** PM-only (policy + FormRequest); `UserRole::Agent` added to the enum **and** DB
  enum/check; tokens hashed (Sanctum), `expires_at` NOT NULL; abilities validated against an
  allow-list; issuance and revocation audited.
- **Clean:** Sanctum `HasApiTokens` on `User`; no custom token table.
- **UX:** one-screen form, plain-language ability descriptions, "shown once" warning.
- **No guessing:** check every `UserRole` match/switch and policy for the new case.

### Definition of Done
Create/list/revoke works, agent users cannot use the web UI, tests for expiry, revocation,
ability allow-list and non-PM denial; Pint + Larastan + Pest green.

---

## US-20.2 — MCP endpoint with read tools

**As** an agent
**I want** to read stores, assets, jobs and display groups through MCP tools
**So that** I can check the register before proposing or making changes.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-20.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a valid token with `register:read`, **when** the agent calls `search_stores`,
  `get_store` (with assets and display groups), `search_assets` (code, serial, MAC, store) or
  `get_job` (assets, technicians, attachments metadata), **then** results are limited to the
  token's client scope and paginated.
- **Given** a missing, expired, revoked or under-privileged token, **then** the call is refused
  with a clear MCP error and nothing is returned.
- **Given** any call, **then** it is rate-limited per token and audit-logged (user, role, token,
  tool).
- Secrets (router passwords, `.env` values, signed URLs) are never returned.

### Engineering Bar checklist
- **Secure:** `auth:sanctum` + ability middleware on `/mcp`; every tool calls the existing
  Policy `view`/`viewAny`; `ClientScoped` applies through the authenticated user; output
  excludes encrypted fields.
- **Clean:** `laravel/mcp` server class + one class per tool; route in `routes/ai.php`.
- **UX:** tool descriptions written for an agent: what it returns, what it never returns.
- **No guessing:** confirm `laravel/mcp` v1 auth middleware integration with Sanctum.

### Definition of Done
Endpoint live with read tools, scoped, limited, audited; tests for scope, expiry and ability
denial; Claude Code connection documented in `DEPLOYMENT.md`; Pint + Larastan + Pest green.

---

## US-20.3 — Write tools for visit data entry (dry-run first)

**As** an agent
**I want** to register assets, attach them to a job, create display groups and attach evidence
**So that** a visit like Miranda can be recorded from its report without SSH.

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-20.2 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** `assets:write`, **then** `create_asset` and `update_asset` validate with
  `CreateAssetRequest`/`UpdateAssetRequest` rules and save through `Asset::createWithDetail` /
  `updateWithDetail`; `change_asset_status` goes through `AssetTransitionService` with a reason.
- **Given** `jobs:write`, **then** `attach_assets_to_job` uses the same logic as the job form
  (status-before capture + auto Under Maintenance), and `create_display_group` uses
  `DisplayGroupService`.
- **Given** `attachments:write`, **then** `add_job_attachment` accepts a base64 file within the
  UI's size limit, detects MIME server-side against `UploadJobAttachmentRequest::ALLOWED_MIMES`
  and stores it like the UI.
- **Given** `dry_run: true` on any write tool, **then** it validates and returns the planned
  change without writing.
- **Then** there is no delete, force-complete, validate, secret-reveal or settings tool.

### Engineering Bar checklist
- **Secure:** ability + Policy check per tool; same FormRequest rules; DB constraints unchanged
  and still authoritative; every write audited with the agent user.
- **Clean:** first move `syncAffectedAssets` and `storeAttachment` from `ServiceJobController`
  into services used by both the controller and the tools (no duplicated logic).
- **UX:** validation errors returned field by field, in plain language.
- **No guessing:** reuse the exact rule arrays; do not hand-copy them into tool schemas.

### Definition of Done
Write tools work with dry-run, share services with the web UI, audited and scoped; tests per
tool incl. cross-tenant denial and dry-run no-write; Pint + Larastan + Pest green. Proven by
registering the Miranda screens and display groups through MCP (not SSH).
