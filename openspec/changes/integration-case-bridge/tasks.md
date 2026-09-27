# Tasks: integration-case-bridge

## 1. Leaf case scope

- [ ] 1.1 Add a case scope to `scopeParams` and `guardRows` in `src/integrations/projectScope.js`, chosen from the host schema. Verify: vitest spec for client and case scopes.
- [ ] 1.2 Mirror the scope in `lib/Listener/RegisterProjectsLeafListener.php` and the "New project" link with `case` and `title`. Verify: `node scripts/check-integration-parity.js` exits 0; PHPUnit test on the link template.

## 2. New project from a link

- [ ] 2.1 `src/views/ProjectList.vue` opens `ProjectCreationDialog` from `?new=1` with `case`, `client` and `title` prefilled; the dialog saves `caseReference`. Verify: Playwright e2e opens /apps/planninq/projects?new=1&case=<uuid>&title=Case%2012 and creates a linked project.

## 3. Handover

- [ ] 3.1 `lib/Service/CaseHandoverService.php` copying project and task files and the metadata file with before and after checksums, as the current user. Verify: PHPUnit tests in `tests/Unit/Service/` with a mocked file service, including a checksum mismatch.
- [ ] 3.2 Route `POST /api/projects/{id}/case-handover` with an owner check, and the `caseHandovers` property on the project schema. Verify: PHPUnit controller test for owner and non-owner; `npm run check:schema-l10n` exits 0.
- [ ] 3.3 "Hand over to case" in the project settings sidebar with the result list, hidden without the case app. Verify: vitest mount test for the hidden state; live check on an instance with Dossiq installed.

## 4. Cross-project and verification

- [ ] 4.1 Open an issue in ConductionNL/dossiq to add the `planninq-projects` leaf to the case schema's linked types. Verify: the issue link is recorded in this change's proposal.
- [ ] 4.2 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 4.3 `openspec validate integration-case-bridge --type change --strict` passes.
