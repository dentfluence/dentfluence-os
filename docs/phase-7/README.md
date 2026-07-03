# Phase 7 — Integration (boundary + external systems)

Status: **code-complete, unmigrated/untested, all flags OFF.** Follows
`docs/implementation-blueprint-v1.md`, "Phase 7 — Integration" (frozen —
nothing in that section was redesigned; this doc just records what got built
against it).

## What this phase is

One anti-corruption boundary (`App\Integration\IntegrationEngine` +
per-provider connectors in `App\Integration\Connectors\`) so business engines
stop holding a vendor SDK directly. Method: strangler-fig, one provider at a
time — wrap it behind a connector, keep the existing legacy call as the
flag-off fallback, shadow-log a comparison, cut over via `integration.<provider>`.
Every flag is instantly reversible back to the legacy direct call.

## Slices

**Slice 1 — WhatsApp** (`integration.whatsapp`). `WhatsAppConnector` wraps
`WhatsAppCloudService` (already the sole vendor-touching class, so this is a
thin passthrough). `OutboundMessageService::sendText()`/`sendTemplate()`
branch on the flag; either way `IntegrationEngine` shadow-logs a payload
comparison — real byte-for-byte parity while sends are `dry_run`/`disabled`
(the current local default).

**Slice 2 — Google** (`integration.google`). `GoogleConnector` is a fresh,
independent implementation of `OAuthService`'s Google OAuth calls (there was
no pre-existing Google vendor-client class to wrap, unlike WhatsApp — see the
connector's docblock for why duplication is intentional here during the
soak). Covers `authUrl`/`exchangeCode`/`fetchAccountInfo`/`ping`/`revoke` for
Google Business Profile + Google Analytics. `authUrl` building is side-effect
free, so it's genuinely dual-computed and diffed; the network calls
(exchange/ping/revoke) run on exactly one path per call, by flag.

**Slice 3 — Meta + Website.** While wrapping Meta, found the same job
(`ProcessScheduledPost`) also publishes to Google Business Profile and
WordPress — neither is Meta, so:
- `MetaConnector` (`integration.meta`) — Meta OAuth (mirrors GoogleConnector's
  pattern exactly) + Instagram 2-step publish + Facebook Page feed publish +
  the Meta Lead Ads webhook's `fetchLeadFields` (`MetaLeadController`).
- `GoogleConnector` extended with `publishBusinessPost()` — still
  `integration.google`, since it's a Google vendor call.
- `WebsiteConnector` (**new flag `integration.website`**, added this slice —
  the blueprint's Phase 7 deliverables paragraph names "website" as one of
  six systems to wrap, but the flag table in `config/features.php` only had
  five keys before this). Wraps WordPress publish — the clinic's own
  self-hosted site.

All publish operations have a real side effect (create a post), so — same
reasoning as Google's exchange/ping/revoke — exactly one path runs per call;
`agreed` in the shadow log records success, not a payload diff.

**Slice 4 — closing sweep (this doc).** ABDM and Payments:

- **ABDM**: `AbdmManager` delegates to a gateway contract; `NullGatewayClient`
  no-ops every call today (`config('abdm.driver')` is unset). Zero real
  vendor calls exist, so there is nothing to strangle yet — wrapping a no-op
  would be speculative code with no legacy path to dual-run against. **Out of
  scope for Phase 7** until a real ABDM sandbox/gateway client is built (a
  separate ABDM-roadmap milestone, see `docs/abdm/`). When that happens, it
  follows this exact same pattern: an `AbdmConnector`, an
  `integration.abdm` flag (already declared), a shadow-log, a parity report.
- **Payments**: no payment-gateway code exists anywhere in the app (confirmed
  by the Phase 7 recon) — only internal wallet/ledger logic. Nothing to wrap.
  `integration.payments` stays declared-but-unused until a real gateway
  (Razorpay/Stripe/PayU) is chosen and integrated.
- **Website — inbound**: `WebsiteLeadController` is a webhook *receiver*
  (validates a shared-secret token, creates a Lead) — no outbound vendor call
  to wrap. `integration.website` (Slice 3) only covers the outbound WordPress
  publish path.

Phase 7's success criterion — "no vendor SDK remains inside a business
engine" — is satisfied for every provider that currently makes a real
outbound vendor call (WhatsApp, Google, Meta, Website/WordPress). ABDM and
Payments make no such call today, so the criterion holds for them vacuously;
they get their connectors the day real code is added, not before.

## Rollout (Sumit — run these yourself, not in the sandbox)

1. `php artisan migrate` — creates `integration_shadow_log` (shared by every
   provider; one table, `provider` column distinguishes rows).
2. `php artisan config:clear`.
3. Use the app normally (WhatsApp sends, Marketing OAuth connects, scheduled
   posts, Meta Lead Ads) — every touchpoint above now shadow-logs a
   comparison regardless of flag state.
4. `php artisan integration:parity {whatsapp|google|meta|website}` — read-only
   evidence report, flips nothing.
5. Flip one `integration.<provider>` flag at a time (global or per-clinic via
   the `feature_flags` table) once you're satisfied. Flip back off any time —
   instant rollback, no data migration involved.

## Files touched/added this phase

- New: `app/Integration/` (`IntegrationEngine`, `Contracts/MessagingConnectorInterface`,
  `Contracts/OAuthConnectorInterface`, `Connectors/{WhatsApp,Google,Meta,Website}Connector`)
- New: `integration_shadow_log` migration + `IntegrationShadowLog` model +
  `integration:parity` command
- Edited (additive, flag-off = unchanged behaviour): `WhatsAppCloudService`,
  `OutboundMessageService`, `OAuthService`, `ProcessScheduledPost`,
  `MetaLeadController`, `config/features.php` (added `integration.website`)
