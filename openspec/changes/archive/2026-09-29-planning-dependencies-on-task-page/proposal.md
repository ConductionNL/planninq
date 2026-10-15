---
kind: code
---

# Put the dependency editor and the blocked badge where people can reach them

## Why

Task dependencies were specified and built in June (archived change
`2026-06-14-task-dependencies`), and no user can use them. The editor
`src/components/TaskDependencies.vue` (picker, blocked banner, remove buttons) is imported by no
view, and `src/views/TaskDetail.vue` has no Dependencies section. The badge
`src/components/BlockedBadge.vue` and the helpers that derive it (`isBlocked`,
`deriveBlockedTaskIds` in `src/utils/taskHelpers.js:105,134`) have no caller either. The server
side is complete: `lib/Service/DependencyService.php` refuses self, duplicate, cross-project and
cyclic links. The timeline draws arrows for stored links (`src/views/ProjectTimeline.vue:86`),
but only seeded demo data ever has one.

The main spec still excludes every end-to-end test with "task-detail render layer not yet built"
(`openspec/specs/task-dependencies/spec.md`). The change shipped without the capability, so this
change finishes it.

Teams also need a link that does not block: "relates to" and "duplicates". The schema has the
types (`blocks`, `relates`, `duplicates`, `clones`, `splits`, `causes`), but the store only posts
`blocker` and `blocked` (`src/store/dependencies.js:81-100`), so every link is a block.

Parity rows: `pln-dependencies`, `pln-dep-validation`, `pln-blocked-badge`,
`pln-deps-on-timeline`, `tsk-related-links` in planninq's `openspec/parity/capabilities.json`.
Decision: build. `pln-dependencies` has five competitors yes and `tsk-related-links` four;
`pln-deps-on-timeline` is rated partial with two competitors yes on the missing half (creating a
link); `pln-dep-validation` and `pln-blocked-badge` were specified by the archived change and
never reached a user, which the pass rule treats as build.

## What changes

- The task page shows a Dependencies section: blocked by, blocks, and related links, with a
  picker to add and a button to remove.
- A refused link (itself, a duplicate, another project, a cycle) shows the server's reason inline.
- Board cards and backlog rows show a Blocked badge while any blocker is unfinished.
- A member adds a non-blocking link: "relates to" or "duplicates".
- A link made on the task page shows as an arrow on the project timeline.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `pln-dependencies`, `pln-dep-validation`, `pln-blocked-badge`, `pln-deps-on-timeline`, `tsk-related-links`.

### `pln-dependencies`: Mark a task as blocked by another task.

- Area `planning`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/components/TaskDependencies.vue (full picker/add/remove UI) -> src/store/dependencies.js:81 createEdge -> lib/Service/DependencyService.php:112 create(); but src/views/TaskDetail.vue never imports or mounts TaskDependencies (grep of src/ finds no importer)"
- Defect: "src/components/TaskDependencies.vue: complete component (picker, blocked banner, remove buttons) with no importer anywhere in src/"
- Defect: "src/views/TaskDetail.vue: has no Dependencies section and does not import TaskDependencies.vue"
- Note: "the UI component and backend are fully built, but the component is never wired into any page, so a user has no way to mark a dependency."
- Demand: none recorded on the row.
- Competitors rated yes (5):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md card Details 'dependent cards' ; _round4/compare/promoted-rows-batch6.md 2.26 'card dependencies (card_api#assignDependentCard, appinfo/routes.php:111, table oc_deck_dependent_cards)' ; source read at v1.19.0: src/components/card/DependentCardsSelector.vue:6 'Assign dependent cards', :56 'Add dependent card', mounted at src/components/card/CardSidebarTabDetails.vue:29-32; lib/Controller/CardController.php:136 assignDependentCard, lib/Service/CardService.php:688, lib/Db/CardMapper.php:673 table deck_dependent_cards"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 2.10 relation.rb eleven typed entries; 8.9 'a follows relation with lag ... moves a successor'; blocks/blocked by among them ; source read at v17.8.0: app/models/relation.rb:36-39 precedes, follows, blocks, blocked types; app/components/work_package_relations_tab/index_component.rb:160-162 add relation menu per type; app/contracts/relations/base_contract.rb:39-43 relation validation"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 IssueRelationChoices includes blocked_by with pair 'blocking' (issue.py:272-290) ; source read at v1.4.2: apps/web/core/components/relations/index.tsx:27 blocked_by option in the relation menu; apps/api/plane/app/urls/issue.py:236 issue-relation route; apps/api/plane/db/models/issue.py:275 BLOCKED_BY and :284 blocked_by paired with blocking"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/journeys.md 4 'createTaskLink(3, 2, link 2 "blocks") and getAllTaskLinks(2) returned the same link as "is blocked by" from the other side' ; source read at v1.2.54: app/Schema/Sqlite.php:962-963 'blocks' and 'is blocked by' link pair; app/Template/task/sidebar.php:48 'Add internal link'; app/Model/TaskLinkModel.php:166 create writes both sides"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html, https://confluence.atlassian.com/jirasoftwareserver/linking-issues-939938934.html): "corpus: jira-data-center/round4/M1-column.md 2.10 'An issue may block another' (Linking issues); docs: https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html 'Dependencies in Advanced Roadmaps indicate which issues are contingent on others being completed first' (corpus 8.8) ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/linking-issues-939938934.html 'An issue may block another'; https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html 'Dependencies in Advanced Roadmaps indicate which issues are contingent on others being completed first' (read 2026-09-26)"

### `pln-dep-validation`: Be stopped from creating a circular, duplicate or self dependency.

- Area `planning`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "lib/Service/DependencyService.php:112-236 assertDistinctTasks/assertEdgeIsValid/DependencyGraph::cyclePath fully implement self/duplicate/cross-project/cycle checks, surfaced by src/components/TaskDependencies.vue:73 (errorMessage)"
- Defect: "src/components/TaskDependencies.vue: the only caller of dependencies.js createEdge/deleteEdge, and it is never imported (see pln-dependencies)"
- Note: "server-side validation is real and thorough, but since the only UI that calls createEdge is never mounted, no user can trigger it."
- Demand: none recorded on the row.
- Competitors rated yes (1):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source: opf/openproject HEAD 27a58131 (18.0.0-dev): config/locales/en.yml:555 'circular_dependency: This relation would create a circular dependency.' and :851 'The relationship creates a circle of relationships'; corpus: openproject/round4/M1-column.md 2.10 relations normalised by 'before_validation :reverse_if_needed' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/contracts/relations/base_contract.rb:67-68 adds 'circular_dependency' when :99-100 the target is not in WorkPackage.relatable; app/models/work_packages/scopes/relatable.rb:178 relatable excludes cycles; app/models/relation.rb:137 uniqueness of to per from blocks duplicates; ... (shortened; full text in the matrix row)"

### `pln-blocked-badge`: See at a glance which tasks are blocked by unfinished work.

- Area `planning`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/components/BlockedBadge.vue (renders isBlocked() result) and src/utils/taskHelpers.js:105 isBlocked() / :135 blockedTaskIds() exist, but grep across src/ finds no importer of BlockedBadge.vue and no caller of isBlocked()/blockedTaskIds() anywhere"
- Defect: "src/components/BlockedBadge.vue: defined, never imported by any view or component"
- Defect: "src/utils/taskHelpers.js:105 isBlocked() and :135 blockedTaskIds(): exported, never called outside their own file"
- Note: "ProjectBoard.vue and TaskCard.vue import taskHelpers.js for other things (groupTasksByStatus, dueDateStatus) but never isBlocked/blockedTaskIds, so no card or list anywhere shows a blocked badge."
- Demand: none recorded on the row.
- Competitors rated yes: none.

### `pln-deps-on-timeline`: See dependency links drawn on the timeline.

- Area `planning`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "src/views/ProjectTimeline.vue:71-95 (edgeLines svg) <- src/utils/timelineHelpers.js buildLayout() consumes payload.dependencies from src/api/timeline.js:35"
- Defect: "src/components/TaskDependencies.vue: not mounted anywhere, so no real edge ever reaches the timeline outside seeded demo data"
- Note: "the timeline correctly draws arrows for whatever dependency edges exist, but the only UI that creates edges (TaskDependencies.vue) is dead code (see pln-dependencies), so in practice a user cannot produce a dependency for the timeline to show."
- Demand: none recorded on the row.
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/gantt-chart/scheduling/README.md:15 'To add dependencies between work packages, you can set them as predecessor or successor in the Gantt chart' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: frontend/src/app/features/work-packages/components/wp-table/timeline/global-elements/wp-timeline-relations.directive.ts:39-43 draws TimelineRelationElement lines from WorkPackageRelationsService; app/models/relation.rb:36-37 precedes and follows"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html 'dependent issues will have numbered badges at either end of the schedule bar based on dependency type' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/dependencies-in-advanced-roadmaps-1044784190.html 'On your timeline, dependent issues will have numbered badges at either end of the schedule bar based on dependency type' (read 2026-09-26)"

### `tsk-related-links`: Link related tasks to each other without one blocking the other.

- Area `tasks`. Planninq is rated `no`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "The dependency schema declares a non-blocking 'type' enum (blocks/relates/duplicates/clones/splits/causes, lib/Settings/planninq_register.json dependency.properties.type), but src/store/dependencies.js:81-100 createEdge() only ever POSTs {blocker, blocked} with no type, so the server always defaults to 'blocks'. Worse, the only component that could create any edge, src/components/TaskDependencies.vue, is never imported by any other file in src/ (grep across src/ finds it only in its own file), ... (shortened; full text in the matrix row)"
- Defect: "src/components/TaskDependencies.vue is never imported/mounted by any view or component in src/, a full dependency-editing UI with no caller"
- Defect: "src/components/BlockedBadge.vue is likewise never imported/mounted anywhere in src/"
- Defect: "src/store/dependencies.js:81 createEdge() has no `type` parameter, so even if wired up it could never create the non-blocking 'relates'/'duplicates'/etc edges the schema declares"
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/M1-column.md 2.10 'Relations are app/models/relation.rb:31 with eleven typed entries' (relates to among them) ; source read at v17.8.0: app/models/relation.rb:35 TYPE_RELATES and :55-57 'related to' among the typed relations, non-scheduling; app/components/work_package_relations_tab/index_component.rb:160-162 add relation menu per type; config/locales/en.yml:3609 'related to'"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "corpus: plane/round4/code-census.md §2 IssueRelationChoices 'duplicate, relates_to, blocked_by, ...' ; source read at v1.4.2: apps/web/core/components/issues/issue-detail-widgets/relations/quick-action-button.tsx:38-40 opens the relation modal; apps/web/core/components/relations/index.tsx:13-14 relates_to option; apps/api/plane/app/urls/issue.py:236 issue-relation route; apps/api/plane/db/models/issue.py:272-274 IssueRelationChoices RELATES_TO"
  - Kanboard 1.2 (no URL in the cell; source or corpus citation only): "corpus: kanboard/round4/_round4/compare/promoted-rows-batch7.md 2.26 'links holds label pairs with opposite_id (11 shipped: relates to, blocks / is blocked by ...)' ; source read at v1.2.54: app/Template/task/sidebar.php:48 'Add internal link'; app/Template/task_internal_link/create.php:10-13 label and task; app/Schema/Sqlite.php:961 seeds 'relates to'; app/Controller/TaskInternalLinkController.php:48 save"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/linking-issues-939938934.html): "corpus: jira-data-center/round4/M1-column.md 2.10 'An issue may relate to another. An issue may duplicate another. An issue may block another' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/linking-issues-939938934.html 'An issue may relate to another. An issue may duplicate another. An issue may block another' (read 2026-09-26)"

## Scope

### In scope

- Mounting `TaskDependencies.vue` on TaskDetail and `BlockedBadge.vue` on TaskCard.
- Loading the project's edges once per board and per task page.
- `relates` and `duplicates` link types through the existing endpoint.
- End-to-end tests for the scenarios the main spec excludes today.

### Out of scope

- `clones`, `splits` and `causes`: the schema keeps them for imports; the picker offers the two
  types the rows ask for.
- Merging duplicates (row `tsk-merge`, deferred).
- Moving dependent dates automatically: `planning-timeline-editing`.

## Impact

- `src/views/TaskDetail.vue`, `src/views/ProjectBoard.vue`, `src/components/TaskCard.vue`,
  `src/components/TaskDependencies.vue`, `src/store/dependencies.js`,
  `lib/Controller/DependencyController.php` and `lib/Service/DependencyService.php` (accept
  `type`), `tests/e2e/`, `l10n/`.
- Depends on: nothing. `tasks-create-edit-delete` makes it easier to create the tasks to link.

## Risks

### Risk 1: A non-blocking link counts as blocking
**Severity**: Medium
**Mitigation**: the blocked derivation filters on `type === 'blocks'` (and a missing type, for
old edges); a vitest case covers a `relates` edge.

### Risk 2: The cycle check runs on related links
**Severity**: Low
**Mitigation**: cycle and ordering checks apply to `blocks` only; `relates` and `duplicates` get the
self and duplicate checks only.
