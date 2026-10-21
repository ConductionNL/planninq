---
kind: code
---

# My work: my tasks across projects, task figures on the dashboard, and my own project order

## Why

Nobody can see their own tasks in one place. Tasks are only ever fetched per project
(`src/store/projects.js:775`), no page filters them on the current user, and the menu has no "My
work" entry (`src/manifest.json`, menu). The main spec `openspec/specs/dashboard-my-work.md`
describes a My Work view grouped by urgency and names a change `dashboard-my-work` that does not
exist; the matrix marks the row `specified` on that claim.

The dashboard counts projects only: active, "Projects I am in" and archived
(`src/manifest.json:72-140`), plus up to five active projects in a fixed order
(`src/components/DashboardPanels.vue:102-106`). It shows no tasks at all, although the same main
spec asks for open, overdue, in-progress and completed-today counts. "Projects I am in" has no
member filter, so an admin sees every project counted. ADR-001 rule 6 makes Mijn werk the daily
home "optimised for the 80% of users who are not managers".

A Kanboard user asked to arrange the projects on their dashboard in their own order
(https://github.com/orgs/kanboard/discussions/5394).

Parity rows: `prt-my-work`, `prt-dashboard`, `prt-dashboard-order` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `prt-my-work` was marked specified with no change directory and has five
competitors yes; `prt-dashboard` is rated partial with four competitors yes on the missing half
(task figures); `prt-dashboard-order` has a feature request plus one competitor yes.

## What changes

- A "My tasks" page lists every open task assigned to me, or shared with me, across my projects,
  grouped as Overdue, Due this week and Later, sorted by priority, with a status change in place.
- The dashboard shows task figures for me: open, overdue, in progress and completed today, each
  opening My tasks with that filter.
- "Projects I am in" counts only projects I am a member of.
- I pin projects to the top of "My projects" and put them in my own order.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prt-my-work`, `prt-dashboard`, `prt-dashboard-order`.

### `prt-my-work`: See the tasks assigned to you across all projects.

- Area `portfolio`. Planninq is rated `no`, built.state `specified`, owner `ConductionNL/planninq`.
- Built evidence: "openspec/specs/dashboard-my-work.md (status in-progress) describes a My Work view; no page in src/manifest.json and no component filters tasks by assignedTo == current user"
- Note: "The only board filter is by label (src/views/ProjectBoard.vue:66-95); tasks are only ever fetched per project (src/store/projects.js:775)."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/M1-column.md 3.5 'Upcoming cards (/upcoming/) lists cards assigned to me across boards' ; source read at v1.19.0: src/router.js:36-38 /upcoming; src/components/overview/Overview.vue:111 'Upcoming cards', :57-83 due-date columns; lib/Controller/OverviewApiController.php:29 upcomingCards, lib/Service/OverviewService.php:26-41 cards assigned to me or unassigned across all my boards"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 3.5 'default queries ship and modules/my_page/ renders personal ones'; global 'Work packages' module across projects (corpus: openproject/round4/menu-tree.md) ; source read at v17.8.0: app/services/work_packages/default_query_generator_service.rb:41 'assigned_to_me' among the default queries, built at :115; app/menus/work_packages/menu.rb:40-50 lists them in the global Work packages menu across all projects; modules/my_page/lib/my_page/grid_registration.rb:72 'work_packages_assigned' widget"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/menu-tree.md workspace 'Your work' ; M1-column.md 5.4 'a workspace member's profile page aggregates their assigned and created work items' ; source: filter.ts profile_issues list and kanban grouped by project ; source read at v1.4.2: apps/web/app/(all)/[workspaceSlug]/(projects)/profile/[userId]/[profileViewId]/page.tsx assigned, created and subscribed tabs; packages/constants/src/issue/filter.ts:113-131 profile_issues list and kanban grouped by project; apps/api/plane/app/urls/workspace.py:153 user-issues route, apps/api/plane/app/views/workspace/user.py:98 WorkspaceUserProfileIssuesEndpoint"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 3.5 'the dashboard shows "My tasks" and "My subtasks"' ; source read at v1.2.54: app/Template/dashboard/sidebar.php:10 'My tasks', :13 'My subtasks'; app/Controller/DashboardController.php:35-41 tasks via getDashboardPaginator for the user across projects; app/Template/dashboard/tasks.php:2"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/searching-for-issues-939938681.html): "corpus: jira-data-center/round4/M1-column.md 3.5 'predefined system filters for common queries, such as My Open Issues' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/searching-for-issues-939938681.html 'predefined system filters for common queries, such as My Open Issues, Reported by Me, Recently Viewed, and All Issues' (read 2026-09-26)"

### `prt-dashboard`: See a personal dashboard of your projects and tasks.

- Area `portfolio`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/manifest.json:72-140 (Dashboard page, three project-count stat widgets + project-panels) -> src/components/DashboardPanels.vue:16-44 'My projects' list via src/store/projects.js:170 fetchProjects()"
- Defect: "src/manifest.json:97-110 'Projects I am in' KPI has no member filter; it counts every project the reader can see, which for an admin (project read grants group admin, lib/Settings/planninq_register.json project.authorization) is every project (code reading, needs a live check)"
- Note: "The dashboard is about projects only: active/member/archived project counts and up to five active projects. It shows no tasks at all, although openspec/specs/dashboard-my-work.md asks for open, overdue and in-progress task KPIs."
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'Dashboard. Three widgets, Upcoming cards, Today and Tomorrow' ; 'Upcoming cards cards assigned to me across boards' ; source read at v1.19.0: lib/AppInfo/Application.php:138-140 registers dashboard widgets DeckWidgetUpcoming, DeckWidgetToday, DeckWidgetTomorrow, lib/Dashboard/DeckWidgetUpcoming.php:53 'Upcoming cards'; inside Deck src/router.js:36-38 /upcoming renders src/components/overview/Overview.vue:57-83 my cards by due date"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md 'My page personal dashboard, grid of widgets'; corpus: openproject/round4/M1-column.md 10.2 'modules/my_page/, the same grid machinery per user' ; source read at v17.8.0: modules/my_page/config/routes.rb:3 /my/page; modules/my_page/lib/my_page/grid_registration.rb:72 'work_packages_assigned' and :87 'work_packages_created' default widgets on a per user grid"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/M1-column.md 10.2 'the dashboard is fixed: projects, my tasks, my subtasks, calendar, activity (DashboardController)' ; source read at v1.2.54: app/Template/dashboard/sidebar.php:4-13 'Overview', 'My projects', 'My tasks', 'My subtasks'; app/Controller/DashboardController.php:18 show, :35 tasks, :51 subtasks; app/Template/dashboard/layout.php:18 'My activity stream'"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-dashboards-939939002.html): "corpus: jira-data-center/round4/M1-column.md 10.2 'you can create a personal dashboard and add gadgets to keep track of assignments and issues you're working on' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-dashboards-939939002.html 'you can create a personal dashboard and add gadgets to keep track of assignments and issues you're working on' (read 2026-09-26)"

### `prt-dashboard-order`: Arrange the projects on your dashboard in your own order.

- Area `portfolio`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/components/DashboardPanels.vue:18-44 renders recentProjects [:102-106, a filter and slice of src/store/projects.js:170 fetchProjects]. No stored per-user order, no drag handle, no favourite or pin."
- Note: "Demand row mined from kanboard (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://github.com/orgs/kanboard/discussions/5394
- Competitors rated yes (1):
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source read at v1.4.2: apps/web/core/components/workspace/sidebar/projects-list-item.tsx:307 'drag to rearrange'; apps/web/core/components/workspace/sidebar/projects-list.tsx:102 saves sort_order; apps/api/plane/db/models/project.py:223 sort_order per member"

## Scope

### In scope

- A `MyWork` page and its manifest route and menu entry.
- Task KPI widgets on the Dashboard page and the member filter on "Projects I am in".
- Pin and order for "My projects", stored per user.

### Out of scope

- The five-menu relabel of Dashboard to Mijn werk: `adopt-five-menu-navigation-ia` (open) owns
  the menu structure; this change adds the page it will group.
- A cross-project board: `boards-cross-project-board`.
- The timer on Mijn werk: `time-timer-and-work-type`.

## Impact

- `src/manifest.json` (Dashboard widgets, new MyWork page and menu entry), a new
  `src/views/MyWork.vue`, `src/registry.js`, `src/store/projects.js` (my-tasks query),
  `src/components/DashboardPanels.vue`, `lib/Service/SettingsService.php` (project order
  preference), `l10n/`.
- Depends on: `tasks-assignment-priority-labels` (`sharedWith`), `tasks-dates` (due dates to
  group by), `boards-configurable-columns` (`completedAt` for completed today).

## Risks

### Risk 1: A cross-project query is slow for a heavy user
**Severity**: Medium
**Mitigation**: the query asks Open Register for `assignedTo = me` and open statuses only, with a
limit and paging; closed work never loads.

### Risk 2: Two definitions of "my task"
**Severity**: Low
**Mitigation**: one helper decides (`assignedTo` is me, or `sharedWith` contains me) and both the
page and the KPIs use its filters.
