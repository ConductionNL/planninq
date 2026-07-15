---
kind: code
---

# Proposal: Adopt nc-vue Live Updates in Planix UI

## Why

`@conduction/nextcloud-vue` 1.0.0-beta.212 installs `liveUpdatesPlugin` default-on in `createObjectStore` (lazy until the first `subscribe()`), and OpenRegister pushes `or-collection-{register-slug}-{schema-slug}` / `or-object-{uuid}` events for all OR-backed objects. Planix, however, consumed the package's **shared** `useObjectStore` instance (`'conduction-objects'`), which is created with an empty plugin list and therefore has no `subscribe()` — so Planix views could not receive live updates at all, and users had to refresh manually to see changes made in other sessions.

## What Changes

- Bump `@conduction/nextcloud-vue` to `^1.0.0-beta.212`.
- New `src/store/objectStore.js`: a Planix-owned `createObjectStore('planix-objects')` instance (liveUpdatesPlugin default-on). All four former consumers of the package's shared store (`store/projects.js`, `store/timeEntries.js`, `store/store.js`, `views/Timesheet.vue`) now import from this module, so CRUD state and live refetches land in one store.
- `registerObjectType` calls pass the canonical slugs (`planix` register; `project`/`column`/`task`/`timeEntry` schemas — they already equal the type constants) as slug hints, so collection event keys resolve without a lazy register/schema fetch.
- `ProjectList.vue` subscribes to the `or-collection-planix-project` collection event; a bridge watcher re-applies the member filter into `projectsStore.projects`.
- `TaskDetail.vue` subscribes to `or-object-{uuid}` for the open task; a bridge watcher copies the refetched object into `projectsStore.activeTask`. Re-scopes on route change, releases on destroy.
- Both views copy the reference guards from OpenRegister's ObjectsList/ObjectDetails: pending-scope marker, epoch counter invalidating in-flight subscribes, `beforeDestroy` release.

## Impact

- Frontend only — no PHP changes; OpenRegister already pushes the events.
- New canonical spec: `openspec/specs/realtime-updates.md`.
- Behaviour without notify_push degrades to visibility-gated polling; without any transport the views behave exactly as before (subscribe failures are warn-logged, never rendered as errors).
