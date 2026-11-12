---
kind: docs
---

# Proposal: Planninq Beta Cross-Surface Alignment

## Problem

Planninq ships real code — a kanban board with WIP limits, a backlog, task dependencies with server-side cycle detection, and a time-tracking MVP — none of which was visible outside `lib/` and `docs/features/`:

- **No product page existed.** `conduction-website/src/pages/apps/planninq.mdx` (served at conduction.nl/apps/planninq) was missing entirely, and so was its Dutch mirror. Planninq also had no row in `conduction-website/src/data/apps-catalog.js`, so even after authoring the page it would not have surfaced on the `/apps` grid.
- **`appinfo/info.xml`'s "Key Features" list was generic boilerplate** (Vue 2 + Pinia, admin settings, quality pipeline) inherited from the app-template scaffold — it never mentioned the kanban board, backlog, time tracking, or dependencies that are the app's actual value proposition, in either English or Dutch.
- **No app dependency was declared.** `openspec/app-config.json` says `requiresOpenRegister: true` and the app is a documented OpenRegister thin client, but `info.xml`'s `<dependencies>` block only listed `nextcloud`/`php` — no note that OpenRegister must be installed first (the fleet convention, per `procest/appinfo/info.xml` and `pipelinq/appinfo/info.xml`, is an XSD-comment + a "**Requires:**" line in both language descriptions).
- **`docs/features/` had two verified drifts** from the actual register schema (`lib/Settings/planninq_register.json`): the schema count/list omitted the `dependency` schema (6 schemas shipped, docs said 5), and `tasks.md` didn't mention the shipped task-dependency capability at all. `docs/features/README.md`'s Tasks summary also claimed "subtasks" as shipped — no subtask UI exists in `src/` (only an unused `parent` field on the task schema) — that claim was removed.

## Canonical Feature List (derived from shipped code, reconciled across all 4 surfaces)

Verified against `lib/Settings/planninq_register.json` (task/project/column/timeEntry/label/dependency schemas), `lib/Controller/*`, `src/views/*`, `src/store/*`, and `docs/features/*.md`:

1. **Kanban board with WIP limits** — configurable columns, drag-and-drop, per-column WIP limit with soft visual warning, filter by assignee/label/priority, kanban↔list view toggle
2. **Backlog management** — unscheduled (no-column) tasks in a sortable, filterable backlog
3. **Time tracking** *(MVP, just added)* — per-task time estimate, manual time-log entries, personal timesheet grouped by date with daily/weekly totals
4. **Task dependencies** — blocked-by edges between tasks in the same project, server-side self/duplicate/cycle validation (`DependencyController`/`DependencyService`), blocked badge on the card
5. **Projects** — CRUD, members, archive, color/icon, case reference
6. **Dashboard & My Work** — KPI cards, tasks due this week, recently updated, my work queue
7. **Labels** — colour-coded app-wide tags with admin-side label management
8. **Procest integration** — optional `caseReference` (project) / `zaakUuid` (task) fields bridging to Procest cases, VNG InterneTaak field mapping
9. **Admin & user settings** — default columns, label management, notification toggles, default view

## Reconciliation (per surface)

1. **`planninq/appinfo/info.xml`** — rewrote the EN + NL "Key Features" bullet lists in the `<description>` blocks to name items 1–8 above (dropped the generic Vue2/Pinia/quality-pipeline bullets, which describe internals, not user-facing features); added a `<!-- App dependency ... -->` XSD-comment inside `<dependencies>` plus a "**Requires:** OpenRegister" / "**Vereist:** OpenRegister" line in both descriptions, matching the `procest`/`pipelinq` convention. Version (`0.2.9`) and `<category>organization</category>` left as-is (both are truth sources, not touched).
2. **`conduction-website/src/pages/apps/planninq.mdx`** (new) + **`i18n/nl/docusaurus-plugin-content-pages/apps/planninq.mdx`** (new) — authored from the `shillinq.mdx` template: `DetailHero` (Beta status, `v0.2`, cobalt background), a 4-item `FeatureList` (kanban+WIP, backlog, time tracking, dependencies), a `PairRow` naming OpenRegister and Procest, `PartnersForApp`, `CtaBanner`. Feature wording matches the info.xml bullets 1-to-1 where scoped (4 of 8 — the highest-signal, most differentiating items; the rest are covered in the intro paragraph and docs).
3. **`conduction-website/src/data/apps-catalog.js`** — added the missing `planninq` entry to `PRESENTATION` (tagline, `/apps/planninq` href, `Processes` category, `AppGlyph`) — without this the new product page would not appear on the `/apps` grid or `AppsPreview`, even though `data/app-downloads.json` already tracks the repo.
4. **`conduction-website/src/components/AppGlyph/AppGlyph.jsx`** — added an explicit `planninq: 'PX'` monogram (the fallback logic would have produced the same result, but every other cataloged app has an explicit entry).
5. **`planninq/docs/features/register-schemas.md`** + **`planninq/docs/features/README.md`** — corrected schema count 5 → 6 and added the missing `dependency` schema to both the prose list and the summary table.
6. **`planninq/docs/features/tasks.md`** — added the missing "Task dependencies" capability bullet (code + register schema + FEATURES.md all confirm it ships; the feature doc alone omitted it).
7. **`planninq/docs/features/README.md`** — removed "subtasks" from the Tasks summary row (unverified — no subtask UI in `src/`, only an unused `parent` field on the task schema; `FEATURES.md` correctly tiers it as V1/not-yet-built).

## Claims verified vs removed

- **Verified and kept**: kanban board + WIP limits, backlog, time tracking (estimates + manual log + timesheet), task dependencies with cycle detection, Procest case-reference bridge, labels, admin/user settings, OpenRegister thin-client architecture, EUPL-1.2 license, NC 28–34 / PHP 8.3 compatibility range, NL Design System / WCAG AA readiness (CSS-variable based, no hardcoded colors found in a spot-check).
- **Removed/corrected**: "subtasks" as a shipped Tasks feature (docs/features/README.md) — no subtask UI exists; register-schema count (5 → 6, `dependency` was omitted); info.xml's stale internals-only feature bullets replaced with user-facing ones.
- **No compliance/standard claims found that needed removal** — Planninq makes no Peppol/SEPA/DigiD/BBV-style claims anywhere; its VNG InterneTaak / Schema.org / iCalendar VTODO mappings are internal field-mapping references, not compliance certifications, and are accurately scoped to "Procest bridge field mapping," not standalone compliance assertions.

## Icon status

`img/app.svg` (nav icon, `#fff` fill, 24×24 viewBox) and `img/app-store.svg` (512×512, cobalt `#21468B` hexagon background with the same white glyph scaled/centered) both match the app-icon convention (white fill, brand cobalt background for the store variant). No mismatch found; no change made.

## Still misaligned (needs a decision, out of scope here)

- `node_modules/@conduction/docusaurus-preset/src/data/apps-registry.js` (the *published* preset package, not this repo's source) has no `planninq` entry — `DetailHero`'s `APPS_REGISTRY[appId]` lookup (JSON-LD `SoftwareApplication` emission, `applicationCategoryFor`) will silently no-op for planninq until the preset itself is updated and republished. This lives in a separate package repo and is out of scope for a local edit here.
- `planix.conduction.nl` (the docs site) was not fetched/verified live in this pass (offline verification against local `docs/` source only, per the align-brief fallback instruction); the local docs are otherwise substantially complete and were the primary source for the canonical feature list.
