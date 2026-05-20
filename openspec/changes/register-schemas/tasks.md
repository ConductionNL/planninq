# Tasks: Register Schemas

## Tasks

### Phase 1 — Schema Definitions [MVP]

- [ ] Open `lib/Settings/planix_register.json` and verify `components/schemas` contains exactly the keys `task`, `project`, `column`, `timeEntry`, `label` — no `example` key
- [ ] If `example` schema is present, remove it from `components/schemas`
- [ ] Verify `task` schema: `required: ["title","status"]`, `status.enum: ["open","in_progress","blocked","done","cancelled"]`, `status.default: "open"`
- [ ] Verify `project` schema: `required: ["title","status"]`, `status.enum: ["active","archived","completed"]`, `status.default: "active"`
- [ ] Verify `column` schema: `required: ["title","project","order"]`, `type.enum: ["active","done"]`, `type.default: "active"`
- [ ] Verify `timeEntry` schema: `required: ["task","user","duration","date"]`
- [ ] Verify `label` schema: `required: ["title","color"]`, `color.default: "#4376FC"`, `color.pattern: "^#[0-9A-Fa-f]{6}$"`
- [ ] Set `info.version` to `"0.2.0"` in `planix_register.json`

### Phase 2 — Seed Data [MVP]

- [ ] Add or verify `components/objects` array exists in `planix_register.json`
- [ ] Add seed labels (5 objects with `@self.slug`, `title`, `color`):
  - [ ] `bug` — `#E74C3C` — "Bug"
  - [ ] `feature` — `#4376FC` — "Feature"
  - [ ] `docs` — `#27AE60` — "Docs"
  - [ ] `design` — `#9B59B6` — "Design"
  - [ ] `infrastructure` — `#F39C12` — "Infrastructure"
- [ ] Add seed projects (3 objects):
  - [ ] `client-portal-v2` — "Client Portal v2" — status: active
  - [ ] `infrastructure-migration` — "Infrastructure Migration" — status: active
  - [ ] `onboarding-automation` — "Onboarding Automation" — status: active
- [ ] Add seed columns (4 per project = 12 total), each with `title`, `project` (UUID ref), `order`, `type`:
  - [ ] `portal-todo`, `portal-in-progress` (wipLimit: 3), `portal-review` (wipLimit: 2), `portal-done` (type: done)
  - [ ] `infra-todo`, `infra-in-progress` (wipLimit: 3), `infra-review` (wipLimit: 2), `infra-done` (type: done)
  - [ ] `onboard-todo`, `onboard-in-progress` (wipLimit: 3), `onboard-review` (wipLimit: 2), `onboard-done` (type: done)
- [ ] Add seed tasks (5 objects):
  - [ ] `fix-login-redirect` — status: in_progress — project: client-portal-v2 — column: portal-in-progress — assignedTo: jdoe
  - [ ] `design-dashboard-widgets` — status: open — project: client-portal-v2 — column: portal-todo
  - [ ] `write-api-docs` — status: open — project: client-portal-v2 — column: portal-todo
  - [ ] `k8s-namespace-setup` — status: open — project: infrastructure-migration — column: infra-todo
  - [ ] `onboarding-n8n-workflow` — status: open — project: onboarding-automation — column: onboard-todo
- [ ] Add seed time entries (3 objects):
  - [ ] `te-fix-login-2026-04-01` — task: fix-login-redirect — user: jdoe — duration: 90 — date: 2026-04-01
  - [ ] `te-fix-login-2026-04-02` — task: fix-login-redirect — user: jdoe — duration: 60 — date: 2026-04-02
  - [ ] `te-k8s-2026-04-01` — task: k8s-namespace-setup — user: ksmits — duration: 45 — date: 2026-04-01
- [ ] Verify cross-references: `fix-login-redirect.project` UUID matches `client-portal-v2` UUID; `fix-login-redirect.column` UUID matches `portal-in-progress` UUID

### Phase 3 — Import Logic [MVP]

- [ ] Read `lib/Service/SettingsService.php::loadConfiguration()` — confirm `configVersion` is extracted from `configData['info']['version']` and passed to `ConfigurationService::importFromApp()`
- [ ] Read OpenRegister's `ConfigurationService::importFromApp()` — confirm it uses slug as idempotency key for seed objects; document the behaviour in a comment in `SettingsService` if non-obvious
- [ ] Test version-skip manually: set stored version to `0.2.0`, call `loadConfiguration()`, confirm no import occurs (check logs for "skipping" or verify object count unchanged)
- [ ] Test version-bump manually: set stored version to `0.1.0`, call `loadConfiguration()` with file at `0.2.0`, confirm import runs and stored version updates to `0.2.0`
- [ ] Test idempotency: import once, record Label/Project/Column/Task/TimeEntry counts via OpenRegister API, trigger re-import (bump to `0.2.1` then back), confirm counts unchanged

### Phase 4 — Cleanup [MVP]

- [ ] Search `lib/Listener/DeepLinkRegistrationListener.php` for the string `"example"` — remove any reference to the `example` schema slug
- [ ] Search entire `lib/` directory for any remaining `example` schema references and remove them

### Phase 5 — Verification [MVP]

- [ ] POST `{"description":"missing title"}` to the task endpoint → expect HTTP 400 with validation error mentioning `title`
- [ ] POST `{"title":"Test","status":"unknown"}` to the task endpoint → expect HTTP 400 with validation error mentioning `status`
- [ ] POST `{"title":"Urgent"}` to the label endpoint (no `color`) → expect HTTP 200/201 with `color: "#4376FC"` in response
- [ ] After fresh install on a clean Nextcloud, query OpenRegister API and assert:
  - [ ] ≥ 3 Label objects exist in the `planix` register
  - [ ] ≥ 3 Project objects exist
  - [ ] ≥ 4 Column objects exist
  - [ ] ≥ 5 Task objects exist
  - [ ] ≥ 3 TimeEntry objects exist
- [ ] Retrieve the `Bug` label via API — assert `color === "#E74C3C"`
- [ ] Retrieve the `fix-login-redirect` task via API — assert `project` references `client-portal-v2` and `column` references `portal-in-progress`
