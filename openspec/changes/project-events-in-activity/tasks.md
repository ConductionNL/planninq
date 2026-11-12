# Tasks: project-events-in-activity

## 1. Listener

- [ ] 1.1 Add `lib/Listener/ProjectActivityListener.php` bound to the `project` schema in `lib/AppInfo/Application.php`, publishing created, archived, restored, deleted, member added and member removed through `IActivityManager`, skipping the acting user. Verify: `tests/Unit/Listener/ProjectActivityListenerTest.php`.
- [ ] 1.2 Render the new subjects in the activity provider in English and Dutch. Verify: unit test on the provider.

## 2. Verification

- [ ] 2.1 `openspec validate project-events-in-activity --type change --strict` passes.
- [ ] 2.2 On archive, set `col-activity` in `openspec/parity/capabilities.json` back to built with the new evidence.
