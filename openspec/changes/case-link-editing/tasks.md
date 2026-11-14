# Tasks: case-link-editing

## 1. Project case link

- [x] 1.1 Add a "Case" picker to the Details tab in `src/components/ProjectSettingsSidebar.vue` (owners and managers only), searching cases through the OpenRegister objects API, writing `caseReference` through the projects store. Verify: vitest mount test for owner and member. Built as `src/components/CaseLinkField.vue` over `src/utils/caseLink.js`; `tests/vitest/caseLink.spec.js` covers who may edit (owner, manager, admin; not member or viewer), the edit control only when editable, the search, and the picked/cleared value. The repo's vitest runs in node without a DOM, so the component is read with `support/sfc.js` rather than mounted.
- [ ] 1.2 Show the case badge with a link on `src/views/ProjectList.vue` and the project overview. Verify: Playwright e2e "An owner links a project to a case". Badge built (`src/components/CaseBadge.vue` on `ProjectListItem` and `ProjectOverview`, unit-tested); the e2e needs a live instance with the case app and is left open.

## 2. Task case link

- [ ] 2.1 Add a "Case" field to `src/views/TaskDetail.vue` that writes `zaakUuid`, shown only when the case app is installed. Verify: vitest mount test; Playwright e2e "A member links a task to a case". Field built on `TaskDetail` (shown only when `OC.appswebroots` lists dossiq); the e2e needs a live instance and is left open.

## 3. Copy and verification

- [x] 3.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0. Five strings in all 37 catalogues; `check:l10n` exits 0.
- [ ] 3.2 `openspec validate case-link-editing --type change --strict` passes.
- [ ] 3.3 On archive, set `int-case-ref` in `openspec/parity/capabilities.json` back to built with the new evidence.
