# Tasks: projects-import-from-other-tools

## 1. Mappings

- [ ] 1.1 Add import mappings for a Trello board export and a Jira project CSV to OpenRegister's register import, as configuration in `lib/Settings/`. Verify: PHPUnit test that each sample export maps to a project, columns and tasks.
- [ ] 1.2 Offer the import on the planninq admin page. Verify: Playwright e2e "an admin imports a Trello board".

## 2. Verification

- [ ] 2.1 `openspec validate projects-import-from-other-tools --type change --strict` passes.
