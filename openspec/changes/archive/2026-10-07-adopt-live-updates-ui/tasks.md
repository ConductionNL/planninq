# Tasks: adopt-live-updates-ui

- [x] 1. Bump `@conduction/nextcloud-vue` to `^1.0.0-beta.212` and reinstall.
- [x] 2. Create `src/store/objectStore.js` exporting a Planninq-owned `createObjectStore('planninq-objects')` instance; swap the four shared-store import sites (`store/projects.js`, `store/timeEntries.js`, `store/store.js`, `views/Timesheet.vue`) to it.
- [x] 3. Pass canonical slug hints in every `registerObjectType` call so collection event keys resolve without a lazy fetch.
- [x] 4. Wire the `or-collection-planix-project` subscription in `ProjectList.vue` (subscribe on mount, bridge watcher → `applyLiveProjects`, release on destroy, pending/epoch guards).
- [x] 5. Wire the `or-object-{uuid}` subscription in `TaskDetail.vue` (subscribe per route task, bridge watcher → `activeTask`, re-scope on route change, release on destroy, pending/epoch guards).
- [x] 6. Add `applyLiveProjects` to `store/projects.js` re-applying the member filter on live refetches.
- [x] 7. Add canonical spec `openspec/specs/realtime-updates.md`; annotate touched methods with `@spec`.
- [x] 8. Verify: `npm run lint` clean on touched files, `npm run build` green.
