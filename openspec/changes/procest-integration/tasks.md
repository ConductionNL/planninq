# Tasks: Procest Integration

## Deduplication Check

- [ ] Verify no existing `BridgeController` or case-reference logic exists in `lib/Controller/` or `lib/Service/` — document findings even if "no overlap found"
- [ ] Confirm `caseReference` and `zaakUuid` are not already present in `lib/Settings/planix_register.json` schemas before adding them

---

## MVP Tasks

### Schema

- [ ] **task-1** Add `caseReference` property to the Project schema in `lib/Settings/planix_register.json`:
  - Type: `string`, required: false
  - Pattern: `^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`
  - Description: "UUID van de gekoppelde Procest zaak"
  - Add `@spec openspec/changes/procest-integration/tasks.md#task-1` to schema entry comment

- [ ] **task-2** Add `zaakUuid` property to the Task schema in `lib/Settings/planix_register.json`:
  - Type: `string`, required: false
  - Pattern: same UUID pattern as above
  - Description: "UUID van de Procest zaak waaraan deze taak is gekoppeld"

### Seed Data

- [ ] **task-3** Add 3 seed Project objects with `caseReference` set to `lib/Settings/planix_register.json` under `components.objects[]`:
  - Use `@self` envelope: `{ "@self": { "register": "planix", "schema": "project", "slug": "..." }, ... }`
  - Slugs: `project-grachtenbuurt-aanvraag`, `project-bezwaar-parkeervergunning`, `project-herziening-bestemmingsplan`
  - Dutch realistic titles, fictional but plausible case UUIDs

- [ ] **task-4** Add 3 seed Task objects with `zaakUuid` set (linked to the seeded projects above):
  - Slugs: `task-advies-brandweer`, `task-buurtbewoners-informeren`, `task-juridische-toets`
  - Dutch assignee names, plausible due dates

### Frontend — Case Badge on Project

- [ ] **task-5** Update `src/views/ProjectCard.vue` to show a `CnStatusBadge` when `project.caseReference` is set:
  - Import `CnStatusBadge` from `@conduction/nextcloud-vue`
  - Register it in `components: {}`
  - Label: `t('planix', 'Case: {caseNumber}', { caseNumber: project.caseReference })`
  - Use `var(--color-primary-element)` or appropriate Nextcloud CSS variable for colour
  - Badge MUST be accessible (text label present, not colour-only — WCAG 1.4.1)
  - Add Dutch translation key to `l10n/nl.json`

- [ ] **task-6** Update `src/views/ProjectDetail.vue` to show a case link row when `project.caseReference` is set:
  - Add a `CnDetailGrid` row with label "Zaak" and a hyperlink to the Procest case
  - Link opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`)
  - If `procest_base_url` is not configured in settings, show the UUID as plain text only
  - Add Dutch translation key to `l10n/nl.json`

### Frontend — Case Link on Task

- [ ] **task-7** Update `src/views/TaskDetail.vue` to show a read-only case link when `task.zaakUuid` is set:
  - Add a `CnDetailGrid` row with label "Zaak UUID" and a hyperlink to the Procest case
  - Link opens in a new tab
  - If `procest_base_url` is not configured, show the UUID as plain text
  - Add Dutch translation key to `l10n/nl.json`

### Edit Forms

- [ ] **task-8** Verify that `CnFormDialog` auto-generates inputs for `caseReference` (on Project) and `zaakUuid` (on Task) from the updated schemas — no custom form code should be needed
  - If the auto-generated input is not user-friendly, add a custom label/hint via schema `title` and `description` properties

---

## V1 Tasks

### Backend — Bridge Controller

- [ ] **task-9** Create `lib/Controller/BridgeController.php`:
  - Route: `POST /planix/api/bridge/project` (add to `appinfo/routes.php` BEFORE any wildcard routes)
  - Auth annotation: `#[PublicPage]` (public endpoint; token validation done in service layer)
  - Body: thin — extract `caseUuid` and `caseNumber` from request, call `BridgeService::createProjectFromCase()`, return JSON response
  - On `BridgeService` throwing `UnauthorizedException`: return `Http::STATUS_UNAUTHORIZED`
  - Add `@spec openspec/changes/procest-integration/tasks.md#task-9` to class docblock

- [ ] **task-10** Register new route in `appinfo/routes.php`:
  - `['name' => 'Bridge#createProject', 'url' => '/api/bridge/project', 'verb' => 'POST']`
  - Ensure it appears BEFORE any `{slug}` wildcard route

### Backend — Bridge Service

- [ ] **task-11** Create `lib/Service/BridgeService.php`:
  - Constructor injection: `ObjectService $objectService`, `IAppConfig $appConfig`, `LoggerInterface $logger`, `IClientService $clientService`
  - `validateToken(string $headerToken): void` — reads `bridge_token` from `IAppConfig`; uses `hash_equals()` for constant-time comparison; throws `UnauthorizedException` on mismatch
  - `createProjectFromCase(string $caseUuid, string $caseNumber): array` — creates Project via `ObjectService::saveObject()` with `title = "Case {$caseNumber}"`, `caseReference = $caseUuid`; returns `['id' => $projectId]`
  - `mirrorTaskCompletion(string $zaakUuid, string $completedAt): void` — sends PATCH to `{procest_base_url}/internettaken/{zaakUuid}` with `afhandelingsdatum`; wraps in try/catch; on failure: `$this->logger->warning(...)`, never rethrows
  - `isBridgeEnabled(): bool` — reads `bridge_enabled` from `IAppConfig`
  - Add `@spec openspec/changes/procest-integration/tasks.md#task-11` to all public method docblocks

### Backend — Task Completion Hook

- [ ] **task-12** In `lib/Service/TaskService.php`, add mirroring side-effect to the task-update method:
  - After persisting the task update, if `status === 'done'` AND `task.zaakUuid` is set AND `BridgeService::isBridgeEnabled()` returns true:
    - Call `BridgeService::mirrorTaskCompletion($task->zaakUuid, $task->completedAt)`
  - Mirroring failure MUST NOT fail the task update — the call is wrapped in try/catch inside `BridgeService`
  - Add `@spec openspec/changes/procest-integration/tasks.md#task-12` to the modified method

### Admin Settings — Bridge Configuration

- [ ] **task-13** Add bridge configuration fields to `lib/Settings/AdminSettings.php`:
  - `bridge_enabled` (boolean) — stored via `IAppConfig` (non-sensitive)
  - `procest_base_url` (string) — stored via `IAppConfig` (non-sensitive; used for deep-links and PATCH URL)
  - `bridge_token` (string) — stored via `IAppConfig` with `sensitive: true`
  - Expose via existing `GET /api/settings` and `POST /api/settings` endpoints (no new controller needed)
  - Add `@spec openspec/changes/procest-integration/tasks.md#task-13` to modified methods

- [ ] **task-14** Create `src/components/AdminSettings/BridgeSection.vue`:
  - Import as `CnSettingsSection` wrapper (from `@conduction/nextcloud-vue`)
  - Three fields: enable toggle (`NcCheckboxRadioSwitch`), base URL input, token input (type="password")
  - All labels via `t('planix', '...')` — no hardcoded strings
  - Register in `AdminRoot.vue`
  - Add Dutch translations to `l10n/nl.json`

---

## Testing Tasks

- [ ] **task-15** Write unit tests for `BridgeService`:
  - `validateToken()`: valid token passes, invalid token throws, empty token throws
  - `mirrorTaskCompletion()`: Procest unreachable — no exception propagated, warning logged
  - `isBridgeEnabled()`: returns correct value from `IAppConfig`

- [ ] **task-16** Write unit tests for `ProjectCard.vue` and `TaskDetail.vue` badge/link rendering:
  - Badge shown when `caseReference` is set; hidden when null/empty
  - Case link shown when `zaakUuid` is set; hidden when null/empty

- [ ] **task-17** Verify seed data loads idempotently:
  - Run `importFromApp()` twice; confirm no duplicate objects are created (matched by slug)
