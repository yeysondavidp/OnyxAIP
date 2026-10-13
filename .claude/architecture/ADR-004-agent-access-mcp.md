# ADR-004 — Agent access: an authenticated MCP endpoint instead of SSH + tinker

- **Status:** Accepted (ONYX, 2026-10-13)
- **Date:** 2026-10-13
- **Deciders:** ONYX Visual (engineering)
- **Context source:** Recording the Pandora Miranda visit (12 Oct 2026, JOB-2026-004) on
  2026-10-13 required an AI agent (Claude Code) to SSH into the production VM and run a tinker
  script. Feeds EPIC-20. Related: §14.3 (security, rate limiting), §14.5 (audit), ADR-002
  (deployment, Cloudflare Tunnel), US-01.2/US-01.3 (roles, policies, `client_id` scope).

## Context

PM work that an agent can do well (registering assets from a visit report, attaching evidence
to a job, reading the register to answer a question) currently has only two doors: the PM web
UI (built for a human with a browser session) or a shell on the server. The shell is the wrong
door:

- **Too much power.** SSH + tinker can do anything: bypass policies, FormRequests and the asset
  and job state machines, hard-delete rows, read `.env` secrets. Every guard the Engineering
  Bar puts in the UI/Request/Policy layers is skipped unless the script author re-implements it
  by hand (which is what happened on 2026-10-13).
- **No actor.** Audit rows written from tinker have no user. §14.5 requires actor + role.
- **Fragile.** Each operation is a one-off script, copied by `scp`, run blind.

We want the agent to use the **same rules as the UI**, under a **named, revocable identity**,
with **only the operations it needs**, and without network access to the server.

## Decision

1. **Expose a Model Context Protocol (MCP) server from the app**, using the official
   `laravel/mcp` package (v1.x, supports Laravel 13), at `POST /mcp` on the public HTTPS host
   (through the existing Cloudflare Tunnel). MCP gives agents typed tools with JSON schemas, so
   Claude Code (and any MCP client) can call them directly. A REST client could reuse the same
   service layer later; it is not built now (YAGNI).
2. **Authentication: Laravel Sanctum personal access tokens**, issued by a PM from Settings →
   "Agent access" and bound to a dedicated **service-account user** per agent (e.g. "Claude
   (agent)") with a new `agent` role. The token is shown once, stored hashed, has a mandatory
   expiry (max 90 days), can be revoked instantly and records `last_used_at`.
   - The service account has `client_id = null` (all clients) only if the issuing PM chooses so;
     otherwise it is scoped to one client exactly like a client-scoped user (US-00.4).
3. **Authorisation: abilities + the existing policies.** Each token carries explicit abilities
   (`register:read`, `assets:write`, `jobs:write`, `attachments:write`). Each tool checks its
   ability **and** then calls the same Policy (`$user->can(...)`) the web controller uses. The
   `agent` role is treated like `pm` by policies only for the abilities granted; it can never
   manage users, tokens, settings or email templates.
4. **Mutations go through the existing domain code, never raw queries.** Tools validate input
   with the same FormRequest rules (`CreateAssetRequest`, `UploadJobAttachmentRequest`, …) and
   call the same services (`Asset::createWithDetail`, `AssetTransitionService`,
   `DisplayGroupService`, `JobTransitionService`). The controller-only logic these tools need
   (e.g. `ServiceJobController::syncAffectedAssets`, `storeAttachment`) is moved into services
   first so the web UI and the MCP tools share one implementation.
5. **No destructive tools.** No delete, no force-complete, no validate, no secret reveal, no
   user or settings management. Those stay human-only in the UI. Every mutating tool supports
   `dry_run: true`, which runs validation and returns what would change without writing.
6. **Audit and limits.** Every tool call is audit-logged with the service-account user, its
   role, the token id/name and the tool name (§14.5). Calls are rate-limited per token
   (`throttle:agent`, e.g. 60/min). Uploads are capped (same MIME allow-list and size limit as
   the UI) and sent base64 in the tool call; larger evidence stays a UI upload.
7. **Defence in depth at the edge (recommended).** Put `/mcp` behind a Cloudflare Access
   service-token policy as well, so an attacker needs both the Cloudflare service token and the
   Sanctum token. The app does not depend on it.

## Consequences

- **+** No SSH needed for routine PM data work; the agent cannot bypass the Engineering Bar.
- **+** Every agent change has an actor and is reviewable in the audit log (EPIC-17 viewer).
- **+** Tokens are cheap to revoke; one leaked token is limited to its abilities and client.
- **−** New attack surface on the public host: mitigated by hashed, expiring, ability-scoped
  tokens, rate limits, no destructive tools and (recommended) Cloudflare Access.
- **−** Two new dependencies (`laravel/sanctum`, `laravel/mcp`) to keep patched; covered by the
  CI `composer audit` gate.
- **−** Some controller logic must move to services first (a refactor with tests).

## Alternatives considered

- **Keep SSH + tinker.** Rejected: bypasses every guard, no actor, needs server access.
- **Agent drives the PM web UI through a browser.** Works for reads, but brittle, slow and still
  needs a human PM password in the agent's hands. Rejected as the main door.
- **REST API only.** Fine, but every agent would need hand-written client calls; MCP gives the
  same security with typed tools for free. Can be added later over the same services.
