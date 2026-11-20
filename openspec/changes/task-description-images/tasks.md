# Tasks: task-description-images

## 1. Description images

- [x] 1.1 Accept pasted and dropped images in the task description editor of `src/views/TaskDetail.vue`, upload them through the OpenRegister object-files API and insert a Markdown image link. Verify: vitest test "a pasted image becomes a file and a link".
- [ ] 1.2 Playwright e2e "a member pastes a screenshot into the description".
  - Written 17 Nov: `tests/e2e/task-description-images.spec.ts` (a regular user who owns the project pastes a PNG into the description of "Bewonersavond voorbereiden" in a fresh context, saves, and sees the image in the description and the file on the Attachments tab and in the object's files). Open: the run against a live instance.

## 2. Verification

- [x] 2.1 `openspec validate task-description-images --type change --strict` passes.
