# Dashboard & My Work Specification (Delta)

**Status**: in-progress
**Scope**: planix
**OpenSpec changes**:
- [dashboard-my-work](../../) — adds dashboard landing page, KPI cards, and My Work view

## Purpose

Implements the Planix personal dashboard (route `/`) and My Work view (route `/my-work`). Both views are pure frontend aggregation patterns over existing Task and Project queries — no new entities required. The dashboard is the default landing page of Planix.

## Data Model

No new entities. Aggregates from:
- `Task` — filtered by `assignedTo == currentUser`
- `Project` — filtered by `members contains currentUser`

See [tasks.md](../../specs/tasks.md) and [projects.md](../../specs/projects.md) for entity definitions.

---

## ADDED Requirements

### Requirement: Personal Dashboard [MVP]

The system MUST show a personal dashboard when a user opens Planix.

---

#### REQ-DSH-001: Dashboard default route

The Planix dashboard MUST be the default application route.

##### Scenario: Dashboard renders at route `/`
- GIVEN a user navigates to Planix
- WHEN the app mounts and the current route is `/`
- THEN `DashboardView.vue` MUST render
- AND the sidebar navigation item "Dashboard" MUST be highlighted as active

---

#### REQ-DSH-002: Dashboard KPI cards

The dashboard MUST display four KPI cards showing task counts for the current user.

| Card key | Label | Filter |
|---|---|---|
| `open` | Open | `status === 'open' OR status === 'in_progress'`, `assignedTo == currentUser` |
| `overdue` | Overdue | `dueDate < today AND status !== 'done'`, `assignedTo == currentUser` |
| `in_progress` | In progress | `status === 'in_progress'`, `assignedTo == currentUser` |
| `completed_today` | Completed today | `completedAt` date part === today, `assignedTo == currentUser` |

##### Scenario: KPI cards show correct counts
- GIVEN a user has tasks assigned to them across multiple projects
- WHEN the user opens the Planix dashboard
- THEN the system MUST show four KPI cards, each displaying the correct count matching its filter

##### Scenario: KPI card click navigates to My Work with filter
- GIVEN the dashboard is displaying KPI cards
- WHEN the user clicks a KPI card
- THEN the system MUST navigate to `/my-work?filter={cardKey}`
- AND the My Work view MUST open with the corresponding group pre-selected or scrolled into view

##### Scenario: KPI cards show 0 for user with no tasks
- GIVEN a user has no tasks assigned to them
- WHEN the user opens the dashboard
- THEN all four KPI cards MUST display `0`
- AND the cards MUST be rendered — they MUST NOT be hidden or removed from the DOM

##### Scenario: KPI cards keyboard accessible
- GIVEN the dashboard is rendered
- WHEN the user tabs to a KPI card and presses Enter or Space
- THEN the system MUST navigate to `/my-work?filter={cardKey}` (same as click)

---

#### REQ-DSH-003: Recent projects list

The dashboard MUST display the 5 most recently active projects the current user is a member of.

##### Scenario: Recent projects render with progress
- GIVEN a user is a member of multiple projects
- WHEN the dashboard loads
- THEN the system MUST show at most 5 projects, ordered by `updatedAt` descending
- AND each project entry MUST show: color swatch, icon, title, task count, progress bar (done tasks / total tasks)

##### Scenario: Fewer than 5 projects
- GIVEN a user is a member of fewer than 5 active projects
- WHEN the dashboard loads
- THEN the system MUST show all projects the user is a member of (no padding with empty slots)

##### Scenario: Recent projects empty state
- GIVEN a user is not a member of any project
- WHEN the dashboard loads
- THEN the system MUST show a `CnEmptyState` in the recent projects section
- AND the message MUST read "No projects yet"
- AND a "Create project" button MUST navigate to the new project form
- AND KPI cards MUST still render with count `0` — they MUST NOT be hidden

---

#### REQ-DSH-004: Tasks due this week

The dashboard MUST display all tasks assigned to the current user that are due within the next 7 days.

##### Scenario: Due-this-week tasks render sorted by date
- GIVEN a user has tasks with due dates within the next 7 days
- WHEN the dashboard loads
- THEN the system MUST show those tasks sorted by `dueDate` ascending
- AND each task row MUST show: task title, project name badge, due date
- AND the due date MUST be highlighted (warning color) if it is today or tomorrow

##### Scenario: Due date today/tomorrow highlighted
- GIVEN a task is due today
- WHEN the dashboard due-this-week section renders
- THEN the due date label MUST be visually highlighted
- AND the highlight MUST convey urgency via text or icon — not colour alone (WCAG AA)

##### Scenario: Due-this-week empty state
- GIVEN the user has no tasks due within 7 days
- WHEN the dashboard loads
- THEN the system MUST show a `CnEmptyState` with message "No tasks due this week"

---

### Requirement: My Work View [MVP]

The system MUST provide a "My Work" view showing all tasks assigned to the current user, grouped by urgency.

---

#### REQ-MWK-001: Task urgency grouping

My Work MUST display tasks in three named groups, in fixed display order.

| Group | Label | Filter |
|---|---|---|
| 1 | Overdue | `dueDate < today AND status !== 'done'` |
| 2 | Due this week | `dueDate >= today AND dueDate <= today + 7 days AND status !== 'done'` |
| 3 | Everything else | non-done tasks with `dueDate > 7 days` OR `dueDate === null` |

Tasks with `status === 'done'` or `status === 'cancelled'` MUST NOT appear in any group.
Within each group, tasks MUST be sorted by `priority` descending: `urgent → high → normal → low`.

##### Scenario: My Work renders three groups
- GIVEN a user has tasks in all three urgency categories
- WHEN the user opens the My Work view
- THEN the system MUST render three section headings: "Overdue", "Due this week", "Everything else"
- AND each task MUST appear in exactly one group

##### Scenario: Empty groups are hidden
- GIVEN the user has no overdue tasks
- WHEN My Work renders
- THEN the "Overdue" group heading MUST NOT be shown
- AND the remaining non-empty groups MUST render normally

##### Scenario: Tasks within group sorted by priority
- GIVEN a group contains tasks with priorities urgent, high, normal, and low
- WHEN the group renders
- THEN tasks MUST appear in order: urgent first, then high, normal, low last

##### Scenario: Overdue group uses danger styling
- GIVEN the Overdue group contains tasks
- WHEN My Work renders
- THEN the "Overdue" section heading MUST use the error/danger color token
- AND overdue task rows MUST have a visible overdue indicator
- AND the indicator MUST convey urgency via text or icon — not color alone (WCAG 1.4.1)

---

#### REQ-MWK-002: Task row display

Each task row in My Work MUST display a consistent set of fields.

##### Scenario: Task row renders required fields
- GIVEN a task is displayed in the My Work list
- WHEN the row renders
- THEN the row MUST show all of the following:
  - Project name as a colored badge (using the project's `color` property)
  - Task title (rendered as a clickable link)
  - Due date formatted as `D MMM` (e.g. "20 mei"), highlighted red if overdue, orange if today or tomorrow
  - Status indicator showing the current `status` value
  - Priority indicator (dot or icon reflecting the `priority` value)
- AND the due date highlight MUST NOT use colour as the sole indicator (text or icon also required)

---

#### REQ-MWK-003: Inline status update

My Work task rows MUST support inline status updates without navigating away from the view.

##### Scenario: Status dropdown opens on indicator click
- GIVEN a task row is displayed in My Work
- WHEN the user clicks the status indicator
- THEN the system MUST show a dropdown listing all valid statuses: `open`, `in_progress`, `blocked`, `done`, `cancelled`
- AND the dropdown MUST be accessible via keyboard

##### Scenario: Status update saved without navigation
- GIVEN the status dropdown is open
- WHEN the user selects a new status
- THEN the system MUST send a PATCH request to update the task status
- AND the task row MUST update immediately to reflect the new status
- AND the user MUST remain on the My Work view (no route change)

##### Scenario: Status update error shows toast
- GIVEN the status dropdown is open and the user selects a new status
- WHEN the PATCH request fails
- THEN the system MUST show an `NcToast` error notification with a generic message
- AND the task row MUST revert to the previous status
- AND the user MUST remain on My Work

##### Scenario: Setting status to done removes task from My Work
- GIVEN an overdue task is displayed in the Overdue group
- WHEN the user sets its status to `done`
- THEN the task MUST be removed from the Overdue group immediately
- AND the task MUST NOT appear in any other group (done tasks are excluded from all groups)

---

#### REQ-MWK-004: Task detail navigation

##### Scenario: Click task title navigates to detail view
- GIVEN a task row is shown in My Work
- WHEN the user clicks the task title
- THEN the system MUST navigate to the task detail view at `/tasks/:id`
- AND the browser URL MUST update to reflect the task detail route

##### Scenario: Back button returns to My Work
- GIVEN the user navigated from My Work to a task detail view
- WHEN the user clicks the browser back button
- THEN the system MUST return to `/my-work`
- AND the My Work view MUST restore its previous scroll position (best-effort)

---

#### REQ-MWK-005: Empty My Work state

##### Scenario: No assigned tasks shows empty state
- GIVEN the user has no tasks assigned to them
- WHEN the user opens My Work
- THEN the system MUST show a `CnEmptyState` with:
  - Message: "No tasks assigned to you"
  - Action button: "Browse projects" (navigates to `/projects`)
- AND no group headings MUST be rendered

---

### Requirement: Dashboard Empty State [MVP]

The system MUST guide new users who have no projects or tasks.

---

#### REQ-DSH-005: New user with no projects

##### Scenario: New user empty state — no projects
- GIVEN a user is authenticated and is not a member of any project
- WHEN the user opens the Planix dashboard
- THEN the system MUST show a `CnEmptyState` in place of the recent projects section
- AND the message MUST read "No projects yet"
- AND a "Create project" button MUST navigate to the new project form

##### Scenario: KPI cards show 0 — not hidden — for new users
- GIVEN a user is not a member of any project and has no tasks
- WHEN the dashboard renders
- THEN all four KPI cards MUST display `0`
- AND the KPI cards MUST be rendered in the DOM (not conditionally hidden)

---

#### REQ-DSH-006: Member of projects but no assigned tasks

##### Scenario: Projects present, no assigned tasks
- GIVEN a user is a member of at least one project but has no tasks assigned to them
- WHEN the user opens the Planix dashboard
- THEN all four KPI cards MUST display `0`
- AND the "Due this week" section MUST show `CnEmptyState` with message "No tasks due this week"
- AND the recent projects section MUST render the user's projects normally (not replaced by an empty state)

---

## Non-Functional Requirements

- **Performance**: Dashboard MUST render a loading skeleton within 100 ms of mount. Data MUST be populated within 2 seconds on a standard Nextcloud instance. API calls MUST be parallelised.
- **Accessibility (WCAG AA)**: All interactive elements keyboard-navigable via tab/enter/space. All urgency indicators convey meaning via text or icon — not colour alone (WCAG 1.4.1). Status dropdowns operable via keyboard.
- **Responsive**: Dashboard and My Work MUST be usable from 768 px to 1920 px. KPI cards MUST stack vertically on viewports narrower than 768 px (ADR-010).
- **Internationalisation**: All user-visible strings use `t('planix', '...')` in English (sentence case). Dutch translations in `nl.json`. Both files MUST be in sync (ADR-007).
- **SPDX**: All new `.vue` files begin with `<!-- SPDX-License-Identifier: EUPL-1.2 -->`. All new `.js` files begin with `// SPDX-License-Identifier: EUPL-1.2` (ADR-014).
- **Style scoping**: All `<style>` blocks in new components MUST use the `scoped` attribute (ADR-010).
- **No raw fetch**: All API calls MUST use `@nextcloud/axios` — NEVER raw `fetch()` (ADR-015, CSRF requirement).

---

## Acceptance Criteria

- [ ] Dashboard is the default route (`/`) and loads on Planix open
- [ ] Dashboard shows 4 KPI cards: Open, Overdue, In progress, Completed today
- [ ] Each KPI card is clickable (mouse and keyboard) and navigates to My Work with the corresponding filter pre-applied
- [ ] Dashboard shows the 5 most recently active projects with color swatch, icon, task count, and progress bar
- [ ] Dashboard shows tasks due within 7 days, sorted by due date ascending
- [ ] Tasks due today or tomorrow are highlighted in the due-this-week section
- [ ] "Due this week" section shows `CnEmptyState` ("No tasks due this week") when empty
- [ ] New user with no projects sees `CnEmptyState` with "Create project" button; KPI cards show 0
- [ ] Member with no assigned tasks sees KPI cards at 0 and "No tasks due this week" empty state; recent projects render normally
- [ ] My Work groups tasks into Overdue, Due this week, Everything else — empty groups are hidden
- [ ] Within each group, tasks are sorted by priority: urgent → high → normal → low
- [ ] Tasks in My Work show: project badge, title, due date, status indicator, priority indicator
- [ ] Status can be updated inline from My Work without full page navigation
- [ ] Status update error shows `NcToast` and reverts the row
- [ ] Clicking a task title in My Work navigates to `/tasks/:id`; back button returns to `/my-work`
- [ ] Empty My Work state shows `CnEmptyState` with "No tasks assigned to you" and "Browse projects" button
- [ ] All user-visible strings translated in `l10n/en.json` and `l10n/nl.json` (sentence case keys)
- [ ] All new `.vue` and `.js` files have correct SPDX header on line 1
- [ ] No raw `fetch()` calls in new files — all API calls via `@nextcloud/axios`
- [ ] All `<style>` blocks in new files use `scoped` attribute
- [ ] `npm run lint` passes with zero errors

## Notes

- No new OpenRegister entities — dashboard and My Work are pure frontend aggregation
- The composable derives due-this-week from the already-fetched task list to avoid a redundant API call
- Activity feed (V1): shows recent task updates across my projects via the Nextcloud Activity API — deferred
- Nextcloud Dashboard widget `OCP\Dashboard\IWidget` (V1): surfaces overdue task count in the NC Dashboard — deferred
