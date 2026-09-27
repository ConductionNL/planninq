# Tasks: backlog-list

## 1. List

- [ ] 1.1 Replace the placeholder in `src/views/ProjectBacklog.vue` with a list of the project's column-less, not-done tasks in rank order, with an empty state "Nothing in the backlog". Verify: vitest spec on a `backlogTasks(tasks)` helper; Playwright e2e opens the backlog of a seeded project.
- [ ] 1.2 "New task" on the backlog creating a task without a column at the bottom. Verify: Playwright e2e.

## 2. Rank, sort and filter

- [ ] 2.1 Drag handle and "Move up" and "Move down" writing sparse `columnOrder`. Verify: vitest spec on the rank helper; Playwright e2e reorders and reloads.
- [ ] 2.2 Sort by rank, priority, due date or created, kept in the query string; dragging disabled when not sorted by rank. Verify: vitest spec on the comparators.
- [ ] 2.3 The board filter bar from `boards-filters` on the backlog, plus a "Cancelled" filter. Verify: Playwright e2e filters by priority.

## 3. Moving

- [ ] 3.1 "Move to board" with a column menu on a backlog row, and "Move to backlog" in the board card menu. Verify: Playwright e2e moves a task both ways.

## 4. Spec hygiene, copy and verification

- [ ] 4.1 At archive, retire the "Placeholder until task management is implemented" scenario of `openspec/specs/projects.md`. Verify: the archived spec no longer mentions the placeholder.
- [ ] 4.2 New strings in `l10n/en.json` and `l10n/nl.json`. Verify: `npm run check:l10n` exits 0.
- [ ] 4.3 `openspec validate backlog-list --type change --strict` passes.
