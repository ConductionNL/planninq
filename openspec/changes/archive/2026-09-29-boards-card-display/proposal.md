---
kind: code
---

# Colour cards and split the board into swimlanes

## Why

Every card on a planninq board looks the same. The card background is a fixed surface token
(`src/components/TaskCard.vue:229-237`), and a label only shows as a 12 pixel swatch inside a chip,
so a team cannot see its legal work or its urgent work at a glance. Kanboard and Jira colour cards
by category or label.

The board is also a single row of lanes (`src/views/ProjectBoard.vue:103-175`). With twenty cards
per lane, nobody can see who is carrying what. Plane and Jira split the board into horizontal
swimlanes by assignee, priority or epic. `docs/FEATURES.md` lists "Column color coding" as MVP
and "Swimlanes (group cards by assignee or priority)" as V1.

Parity rows: `brd-card-colours`, `brd-swimlanes` in planninq's
`openspec/parity/capabilities.json`.
Decision: build. `brd-card-colours` has three competitors yes and `brd-swimlanes` two; both are
in the boards core area.

## What changes

- A board "View" menu: colour cards by first label or by priority, shown as a coloured edge on the
  card with the value also in text.
- The same menu: group the board into swimlanes by assignee, priority or epic. Each swimlane can
  be collapsed and shows its card count.
- Dragging a card into another swimlane changes the grouped field (assignee or priority).
- The choice is remembered per person and per project.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `brd-card-colours`, `brd-swimlanes`.

### `brd-card-colours`: Colour cards by label, type or category.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/components/TaskCard.vue:229-237 the card background is a fixed `var(--color-surface)`; labels only render as a small circular swatch inside a chip (:56-59, 12px), not as the card's own colour, and there is no colour-by-type or colour-by-category option anywhere."
- Demand: none recorded on the row.
- Competitors rated yes (3):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source: opf/openproject HEAD 27a58131 (18.0.0-dev): highlighting-mode.const.ts:31 CardHighlightingMode 'priority'|'type'; board configuration modal highlighting-tab.component (openproject-boards.module.ts:38); attribute highlighting is not among the 32 Enterprise symbols (corpus: openproject/round4/open-core.md) (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/boards/board/configuration-modal/tabs/highlighting-tab.component.html:13 'entire card by' with :26 type and :29 priority options; app/contracts/queries/base_contract.rb:42 highlighting_mode saved; searched app and lib for an Enterprise gate on highlighting: none, ... (shortened; full text in the matrix row)"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/menu-tree.md 'Categories · Tags' ; source: app/Action/TaskAssignColorCategory.php and task color_id; tags coloured in app/Template/board/task_footer.php:28 ; source read at v1.2.54: app/Template/task_creation/show.php:18 colour field; app/Template/board/task_private.php:5 card class color-id; app/Action/TaskAssignColorCategory.php:23 'Assign automatically a color based on a category'; app/Action/TaskAssignColorPriority.php:23; app/Template/board/task_footer.php:28 coloured tags"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/customizing-cards-938845307.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/customizing-cards-938845307.html 'One color per issue type', 'One color per priority', 'One color per assignee', 'One color per JQL query' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/customizing-cards-938845307.html 'Base your card colors on ... Issue types One color per issue type', also per priority, assignee or JQL query (read 2026-09-26)"

### `brd-swimlanes`: Split the board into swimlanes by assignee, priority or epic.

- Area `boards`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectBoard.vue has a single grid of column sections (:102-175), no swimlane grouping dimension; task.epic exists as a schema property but is never read in src/ (grep confirmed)."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source: apps/web/core/components/issues/issue-layouts/kanban/swimlanes.tsx ; packages/constants/src/issue/filter.ts:237 sub_group_by options state, priority, cycle, module, labels, assignees, created_by. No epic swimlane because epics are unreachable (corpus: plane/round4/journeys.md epics 404) ; source read at v1.4.2: apps/web/core/components/issues/issue-layouts/kanban/swimlanes.tsx:62 SubGroupSwimlaneHeader; packages/constants/src/issue/filter.ts:237 sub_group_by state, priority, cycle, module, labels, assignees, created_by; apps/api/plane/app/views/issue/base.py:159-162 server groups by sub_group_by. No epic option"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845294.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845294.html (redirects to Configuring swimlanes) 'In the Base Swimlanes on drop-down, select either Queries, Stories, Assignees, or No Swimlanes'; epics via a query swimlane ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/configuring-quick-filters-938845294.html 'In the Base Swimlanes on drop-down, select either Queries, Stories, Assignees, or No Swimlanes'; a JQL query swimlane covers priority or epic (read 2026-09-26)"

## Scope

### In scope

- View menu, card colour edge, swimlane rendering with collapse, cross-swimlane drag for
  assignee and priority, per-user per-project memory of the choice.

### Out of scope

- Custom colour rules. Colours come from labels (admin-managed) and fixed priority tokens.
- Moving a card between epics by drag: epic swimlanes are read-only groupings.

## Impact

- `src/views/ProjectBoard.vue`, `src/components/TaskCard.vue`, a new
  `src/components/BoardViewMenu.vue`, `src/utils/` (grouping helper), `lib/Service/SettingsService.php`
  (per-user board view preference), `l10n/`.
- Depends on: `boards-configurable-columns` (lanes), `tasks-assignment-priority-labels` (values to
  group by).

## Risks

### Risk 1: Colour as the only signal
**Severity**: Medium
**Mitigation**: the edge is decoration; the label chip or priority chip on the card carries the same
information in text (WCAG 1.4.1), and priority colours use NL Design tokens.

### Risk 2: Low contrast in dark mode
**Severity**: Low
**Mitigation**: the edge is a 4 pixel border, never the background, so text contrast does not
depend on the label colour.
