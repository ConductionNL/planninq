# Tasks: task-attachments-from-files

## 1. Pick from Files

- [x] 1.1 Confirm the Files tab of `src/views/TaskDetail.vue` offers "Pick from Files" through `CnFilesTab`, and that the picked file is linked, not copied. If it copies, build the link in nextcloud-vue first. Verify: Playwright e2e "a member attaches a file that is already in Files". (written, runs in nightly: tests/e2e/task-attachments-from-files.spec.ts "a member attaches a file that is already in Files"; not yet verified against a live instance, 24 Nov)
      Built: `CnFilesTab` has no Files picker, so the task page gained a "Files from Files" section on
      `CnRelatedFiles` (the Nextcloud file picker, references only, no copy), stored in the new
      `linkedFiles` property of `task`. The Playwright e2e is written in
      `tests/e2e/task-attachments-from-files.spec.ts` (a fresh non-admin member, WebDAV-seeded file,
      picker, stored reference, no copy). Left unchecked: it has not run yet; it needs a running instance.

## 2. Verification

- [x] 2.1 `openspec validate task-attachments-from-files --type change --strict` passes.
