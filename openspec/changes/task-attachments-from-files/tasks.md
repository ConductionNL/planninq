# Tasks: task-attachments-from-files

## 1. Pick from Files

- [ ] 1.1 Confirm the Files tab of `src/views/TaskDetail.vue` offers "Pick from Files" through `CnFilesTab`, and that the picked file is linked, not copied. If it copies, build the link in nextcloud-vue first. Verify: Playwright e2e "a member attaches a file that is already in Files".

## 2. Verification

- [ ] 2.1 `openspec validate task-attachments-from-files --type change --strict` passes.
