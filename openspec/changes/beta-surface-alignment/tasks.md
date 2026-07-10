# Tasks: Planix Beta Cross-Surface Alignment

## 1. Code metadata

- [x] 1.1 Rewrite `appinfo/info.xml` EN + NL `<description>` "Key Features" bullets to name the shipped feature set (kanban+WIP, backlog, time tracking, dependencies, Procest bridge, OpenRegister integration, NL Design System) instead of generic scaffold internals
- [x] 1.2 Add a `<!-- App dependency ... -->` comment inside `<dependencies>` plus "**Requires:**"/"**Vereist:**" OpenRegister line in both descriptions (matches `procest`/`pipelinq` convention; `openspec/app-config.json` already declares `requiresOpenRegister: true`)
- [x] 1.3 Confirm `img/app.svg` / `img/app-store.svg` match the brand icon convention (white fill, cobalt background) — no change needed

## 2. Product page (new)

- [x] 2.1 Author `conduction-website/src/pages/apps/planix.mdx` from the `shillinq.mdx` template: DetailHero (Beta, v0.2, cobalt), 4-item FeatureList, PairRow (OpenRegister, Procest), PartnersForApp, CtaBanner
- [x] 2.2 Author the Dutch mirror `conduction-website/i18n/nl/docusaurus-plugin-content-pages/apps/planix.mdx`
- [x] 2.3 Add the missing `planix` row to `conduction-website/src/data/apps-catalog.js` PRESENTATION (tagline, href, Processes category, AppGlyph) so the page surfaces on `/apps`
- [x] 2.4 Add an explicit `planix: 'PX'` entry to `AppGlyph.jsx` MONOGRAMS

## 3. Docs (`planix/docs/`)

- [x] 3.1 Fix `docs/features/register-schemas.md` schema count (5 → 6) and add the missing `dependency` schema
- [x] 3.2 Fix `docs/features/README.md` summary table: schema count, add `dependency` to Tasks summary, remove unverified "subtasks" claim
- [x] 3.3 Add the missing "Task dependencies" capability bullet to `docs/features/tasks.md`

## 4. Verification

- [x] 4.1 WebFetch live `https://www.conduction.nl/apps/planix/` (confirmed 404, matching the stated gap) and `https://planix.conduction.nl/` (confirmed live, v0.2.7, feature claims consistent with local `docs/` — no further drift found)
- [x] 4.2 Validate `appinfo/info.xml` is well-formed XML after edits
- [ ] 4.3 (Follow-up, out of scope here) request a `planix` entry in the published `@conduction/docusaurus-preset` apps-registry so `DetailHero`'s JSON-LD/`applicationCategoryFor` lookups resolve
