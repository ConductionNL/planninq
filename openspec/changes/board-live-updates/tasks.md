# Tasks: board-live-updates

## 1. Board subscription

- [ ] 1.1 Subscribe `src/views/ProjectBoard.vue` to the tasks of its project through the live updates plugin of `src/store/objectStore.js`, and unsubscribe on leave. Verify: vitest mount test that a pushed task update moves the card to its new lane.
- [ ] 1.2 Hold incoming updates for a card while the user drags it, and apply them on drop. Verify: vitest test "a dragged card is not moved by a live update".
- [ ] 1.3 Playwright e2e "a colleague's move shows on the board without a reload", with two browser contexts.

## 2. Verification

- [ ] 2.1 `openspec validate board-live-updates --type change --strict` passes.
- [ ] 2.2 On archive, set `col-live-updates` in `openspec/parity/capabilities.json` back to built with the new evidence.
