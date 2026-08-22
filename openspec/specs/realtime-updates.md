# Realtime Updates Specification

**Status**: done

**Feature tier**: MVP

**OpenSpec changes:**
- [adopt-live-updates-ui](../changes/adopt-live-updates-ui/) — wires the nc-vue liveUpdatesPlugin subscriptions into the project list and task detail views

## Purpose

Planninq views that render OpenRegister-backed objects stay fresh without manual refresh. The shared `@conduction/nextcloud-vue` object store (>= 1.0.0-beta.212) installs `liveUpdatesPlugin` default-on in `createObjectStore`, exposing `subscribe(type, id?)` / `unsubscribe(handle)` backed by `@nextcloud/notify_push` with a visibility-gated polling fallback. OpenRegister pushes `or-collection-{register-slug}-{schema-slug}` events for collection changes and `or-object-{uuid}` events for per-object changes; events are refetch hints only — the plugin re-runs the last fetch through the same store, and thin bridge watchers copy the fresh data into Planninq's own Pinia state.

## Requirements

### REQ-Live-Project-List

WHEN the project list view (`ProjectList.vue`) is mounted, the app SHALL subscribe to the `or-collection-planix-project` event via `objectStore.subscribe('project')`, and on each event the refetched collection SHALL be re-filtered through the same member filter `fetchProjects` applies and rendered without user interaction.

#### Scenario: project created elsewhere appears in the list

- **GIVEN** a user has the Planninq project list open
- **WHEN** another session creates a project that includes the user as member
- **THEN** the project appears in the list without a manual refresh

@e2e exclude requires notify_push (or timed polling) infrastructure plus a second concurrent session; not exercisable deterministically in CI e2e

### REQ-Live-Task-Detail

WHEN the task detail view (`TaskDetail.vue`) shows a task, the app SHALL subscribe to the `or-object-{uuid}` event via `objectStore.subscribe('task', uuid)`, and on each event the refetched object SHALL be bridged into `projectsStore.activeTask` so the rendered fields refresh. The subscription SHALL be re-scoped when the route's task changes and released on destroy.

#### Scenario: task edited elsewhere refreshes the open detail view

- **GIVEN** a user has a task detail view open
- **WHEN** another session updates that task (e.g. changes its status)
- **THEN** the visible task fields refresh without a manual reload

@e2e exclude requires notify_push (or timed polling) infrastructure plus a second concurrent session; not exercisable deterministically in CI e2e

### REQ-Subscription-Lifecycle

Subscriptions SHALL be guarded against leaks: an in-flight subscribe is marked pending (no double-subscribe for the same scope), an epoch counter invalidates in-flight resolutions after a release (scope change / component destroy unsubscribes the stale handle on resolution), and `beforeDestroy` releases the active handle and bridge watcher.

#### Scenario: navigating away releases the subscription

- **GIVEN** a view holds a live subscription
- **WHEN** the component is destroyed (navigation away)
- **THEN** the handle is unsubscribed and no further refetches occur

@e2e exclude subscription bookkeeping is not observable through the UI; covered by the shared library's unit tests for liveUpdatesPlugin
