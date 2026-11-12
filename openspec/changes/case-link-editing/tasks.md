# Tasks: case-link-editing

## 1. Project case link

- [ ] 1.1 Add a "Case" picker to the Details tab in `src/components/ProjectSettingsSidebar.vue` (owners and managers only), searching cases through the OpenRegister objects API, writing `caseReference` through the projects store. Verify: vitest mount test for owner and member.
- [ ] 1.2 Show the case badge with a link on `src/views/ProjectList.vue` and the project overview. Verify: Playwright e2e "An owner links a project to a case".

## 2. Task case link

- [ ] 2.1 Add a "Case" field to `src/views/TaskDetail.vue` that writes `zaakUuid`, shown only when the case app is installed. Verify: vitest mount test; Playwright e2e "A member links a task to a case".

## 3. Copy and verification

- [ ] 3.1 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 3.2 `openspec validate case-link-editing --type change --strict` passes.
- [ ] 3.3 On archive, set `int-case-ref` in `openspec/parity/capabilities.json` back to built with the new evidence.
