# Tasks — Dashboard & My Work MVP

## Task 1: Dashboard KPI cards with real data
**spec_ref**: dashboard-my-work.md → Scenario: Dashboard KPI cards
**files_likely_affected**: src/views/Dashboard.vue, src/store/modules/object.js
**acceptance_criteria**:
- [ ] Dashboard fetches tasks assigned to current user from OpenRegister on mount
- [ ] Shows 4 KPI cards: Open, Overdue, In Progress, Completed Today
- [ ] KPI counts are computed from real task data (not sample/placeholder)
- [ ] Each KPI card is clickable (navigates to My Work with filter — wire route, actual filtering in Task 3)

## Task 2: Recent projects and tasks due this week
**spec_ref**: dashboard-my-work.md → Scenario: Recent projects list + Tasks due this week
**files_likely_affected**: src/views/Dashboard.vue
**acceptance_criteria**:
- [ ] Shows 5 most recently active projects the user is a member of
- [ ] Each project shows: title, task count, progress bar (done/total)
- [ ] Shows tasks due within 7 days, sorted by due date ascending
- [ ] Due date highlighted if today or tomorrow

## Task 3: My Work view
**spec_ref**: dashboard-my-work.md → Scenario: Display My Work
**files_likely_affected**: src/views/MyWork.vue (new), src/router/index.js
**acceptance_criteria**:
- [ ] New route /my-work with MyWork.vue component
- [ ] Tasks grouped into: Overdue, Due this week, Everything else
- [ ] Within each group, sorted by priority (urgent → high → normal → low)
- [ ] Each task shows: project name badge, title, due date, priority dot
- [ ] Empty state: CnEmptyState with "No tasks assigned to you" and "Browse projects" action
