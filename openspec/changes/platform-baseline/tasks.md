# Tasks: platform-baseline

## 1. Tags
- [ ] 1.1 Add `@spec openspec/specs/platform-baseline/spec.md#REQ-PLB-001` to the script docblocks of `src/views/ProjectBoard.vue` and `src/components/TaskCard.vue` (V1)
- [ ] 1.2 Add an XML comment `<!-- @spec openspec/specs/platform-baseline/spec.md#REQ-PLB-004 -->` above `<dependencies>` in `appinfo/info.xml`, and `#REQ-PLB-005, #REQ-PLB-006` to the class docblock of each controller in `lib/Controller/` (V1)
- [ ] 1.3 Add `#REQ-PLB-007` to the class docblock of `lib/Settings/AdminSettings.php` (V1)

## 2. Checks that keep the properties true
- [ ] 2.1 `tests/unit/Platform/PlatformBaselineTest.php`: reads `appinfo/info.xml` and asserts OpenRegister is the only app dependency (REQ-PLB-004) (V1)
- [ ] 2.2 Same test: asserts no file under `lib/` injects `OCP\Http\Client\IClientService`, uses `curl_` or `GuzzleHttp` (REQ-PLB-008), and none defines a login, password or session handler of its own (REQ-PLB-005) (V1)
- [ ] 2.3 Same test: parses `appinfo/routes.php` and the controllers and asserts every routed method carries a Nextcloud access attribute, and every `#[PublicPage]` method is on an allow-list in the test with the token check it relies on (REQ-PLB-006) (V1)
- [ ] 2.4 `stylelint.config.js`: add `color-no-hex` (literal colours allowed only as `var()` fallbacks) and the `liberty/use-logical-spec` rule from the `stylelint-use-logical-spec` package (npm, 5.0.1) or an equivalent rule against physical `margin-left`, `padding-right`, `left` and `right` in `src/**/*.vue` (REQ-PLB-002, REQ-PLB-003) (V1)
- [ ] 2.5 Replace the literal hex colours in the six `.vue` files `grep -rlE '#[0-9a-fA-F]{6}\b' src --include=*.vue` finds on development (`LabelEditDialog`, `ProjectSettingsSidebar`, `ProjectCreationDialog`, `ColumnEditDialog`, `TaskDependencies`, `BlockedBadge`) with theme variables, keeping a hex only as a `var()` fallback or as a user-picked colour (REQ-PLB-002) (V1)

## 3. Defects the evidence names
- [ ] 3.1 `src/views/ProjectTimeline.vue`: apply tick, today-marker and bar positions as `insetInlineStart` instead of `left` (lines 93, 103 and the bar style at 526), and flip the keyboard arrows in a right-to-left page (REQ-PLB-003) (V1)
- [ ] 3.2 `src/views/ProjectBoard.vue:118`: drop `aria-hidden` from the column count, or add the count to the column section's `aria-label` (REQ-PLB-001) (V1)

## 4. Verification
- [ ] 4.1 Playwright: run axe-core (already a dependency, `axe-core` ^4.10.0) on the dashboard, a project board and a task page with the e2e seed, asserting no WCAG 2.1 A or AA violation (REQ-PLB-001) (V1)
- [ ] 4.2 Live check: with thematiq on, the dashboard and a board take the configured colours; with the user language set to Arabic, the timeline mirrors (REQ-PLB-002, REQ-PLB-003) (V1)

## 5. Matrix
- [ ] 5.1 When the tasks are done, archive this change and repoint the eight rows in `openspec/parity/capabilities.json` from `built.change` to `built.spec: openspec/specs/platform-baseline`
