# Tasks: project-events-in-activity

## 1. Listener

- [x] 1.1 Add `lib/Listener/ProjectActivityListener.php` bound to the `project` schema in `lib/AppInfo/Application.php`, publishing created, archived, restored, deleted, member added and member removed through `IActivityManager`, skipping the acting user. Verify: `tests/Unit/Listener/ProjectActivityListenerTest.php`.
- [x] 1.2 Render the new subjects in the activity provider in English and Dutch. Verify: unit test on the provider. (Also every one of the 36 required locales, which the parity gate demands; the Planninq filter lists the new `planninq_project` type.)

## 2. Verification

- [ ] 2.1 `openspec validate project-events-in-activity --type change --strict` passes.
- [ ] 2.2 On archive, set `col-activity` in `openspec/parity/capabilities.json` back to built with the new evidence.
