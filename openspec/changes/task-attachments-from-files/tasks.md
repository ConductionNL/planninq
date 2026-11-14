# Tasks: task-attachments-from-files

## 1. Pick from Files

- [ ] 1.1 Confirm the Files tab of `src/views/TaskDetail.vue` offers "Pick from Files" through `CnFilesTab`, and that the picked file is linked, not copied. If it copies, build the link in nextcloud-vue first. Verify: Playwright e2e "a member attaches a file that is already in Files".
      Built: `CnFilesTab` has no Files picker, so the task page gained a "Files from Files" section on
      `CnRelatedFiles` (the Nextcloud file picker, references only, no copy), stored in the new
      `linkedFiles` property of `task`. Left unchecked: the Playwright e2e needs a running instance.

## 2. Verification

- [ ] 2.1 `openspec validate task-attachments-from-files --type change --strict` passes.
