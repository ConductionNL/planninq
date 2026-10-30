---
kind: code
---

# Proposal: platform-baseline

## Summary

Eight capability rows describe planninq as a product rather than as a page in it: it works with a keyboard and a screen reader, it takes its look from the theme, it mirrors for a right-to-left language, it runs on your own server, it signs in through Nextcloud with the second factor Nextcloud enforces, its security overview is Nextcloud's own, and nothing it stores can make the server call an address on the internal network. All eight are built, but no requirement says so. Nothing can cite them, and nothing stops a later change from breaking one. This change writes them down as a `platform-baseline` spec and lists the tags and checks that keep them true.

## The rows this covers

Source: planninq `openspec/parity/capabilities.json`.

- **plt-accessibility** Use the app to WCAG AA with a keyboard and a screen reader (built: `src/views/ProjectBoard.vue` card keys and the "Move to" menu). REQ-PLB-001.
- **plt-nldesign** Apply a government house style through NL Design System tokens (built: Nextcloud CSS variables throughout `src/`). REQ-PLB-002.
- **plt-rtl** Use the app in a right-to-left language such as Arabic (built: logical CSS; the timeline still positions with `left`). REQ-PLB-003.
- **plt-self-host** Run it on your own servers with no vendor cloud involved (built: `appinfo/info.xml`, OpenRegister as the only dependency). REQ-PLB-004.
- **plt-sso** Sign in with the organisation's single sign-on (built: no login of its own). REQ-PLB-005.
- **plt-2fa** Protect sign-in with a second factor (built: Nextcloud two-factor providers run before any planninq page). REQ-PLB-006.
- **plt-security-insights** See an admin overview of security risks with suggested fixes (built: Nextcloud Administration overview). REQ-PLB-007.
- **plt-outbound-guard** Stop webhooks and link lookups from reaching addresses on the internal network (built: OpenRegister `WebhookService::assertSafeWebhookUri`; planninq holds no HTTP client). REQ-PLB-008, with the guard itself in `openregister/notificatie-engine`.

## What changes

1. A new main capability `platform-baseline` with one requirement per row.
2. `@spec` tags on the files that carry each property.
3. Checks that keep the properties true: a unit test over `appinfo/info.xml` and `lib/` (only dependency, no HTTP client, no login code), a stylelint rule against literal colours (six `.vue` files carry one today), and an axe pass on the board, task page and dashboard.
4. Two defects the rows' evidence names, fixed as tasks: the timeline positions bars, ticks and the today marker with `left`, so it does not mirror; and the column card count is `aria-hidden`, so a screen-reader user never hears it.

## Screens

None. These are properties of the whole app; see `screens.md`.

## Out of scope

- Theming itself (thematiq and Nextcloud own it).
- Login, single sign-on and second-factor providers (Nextcloud server owns them).
- The outbound address guard itself (OpenRegister owns it).
