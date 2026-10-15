---
kind: code
---

# Portfolios, subprojects and custom project fields

## Why

Projects are one flat list. `src/views/ProjectList.vue:86-92` renders every project the user is on as one list, with status chips and a search field, and `src/views/Boards.vue` does the same as cards. The `project` schema has no category, folder, portfolio or parent (`lib/Settings/planninq_register.json:445-580`). An organisation with forty projects cannot sort them into portfolios, and cannot put a subproject under the programme it belongs to.

Projects cannot carry your own fields. The only open-ended property is `metadata`, described as a catch-all for source-system and provenance keys (`planninq_register.json:576-581`), and no screen edits it. A municipality that tracks a budget holder, a policy area or a contract number per project has nowhere to put it.

Nextcloud Deck users ask for board grouping (https://github.com/nextcloud/deck/issues/2006). OpenProject nests subprojects and lets admins define project attributes. Jira Data Center groups projects into categories. Tender 365739 of gemeente Sittard-Geleen asks for status and money rolled up per portfolio and for a risk table whose format can differ per portfolio (requirement 64441), which needs a portfolio to exist first.

Parity rows: `prj-grouping`, `prj-hierarchy`, `prj-custom-fields` in planninq's `openspec/parity/capabilities.json`.
Decision: build. Grouping has a feature request and two competitors rated yes. Hierarchy and custom fields are core to the projects area.

## What changes

- An admin or portfolio manager creates portfolios. A project manager puts a project in one.
- The project list and the board picker group projects by portfolio, with sections you can fold, and a portfolio filter.
- A portfolio's managers can read every project in it, so a portfolio office sees its whole portfolio.
- A portfolio can use its own risk scale, overriding the app-wide one from `projects-overview-logs-risks`.
- A project manager puts a project under a parent project. The parent's overview lists its subprojects and adds their progress to its own.
- An admin defines custom fields for projects: text, number, date, choice, person or yes/no. They show on the project's Details tab and overview.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `prj-grouping`, `prj-hierarchy`, `prj-custom-fields`.

### `prj-grouping`: Group projects into folders or categories in the navigation.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "Projects are a flat list on src/views/ProjectList.vue:86-92 with status chips :10-17 and free text search :33-37. The project schema has no category, folder or group property, and the menu src/manifest.json:48-58 lists pages, not projects."
- Note: "Demand row mined from nextcloud-deck (featureRequest) on 2026-09-26."
- Demand (featureRequest, via origin): https://github.com/nextcloud/deck/issues/2006
- Competitors rated yes (2):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source read at v17.8.0: config/locales/en.yml:3696 'Add subproject'; app/models/projects/hierarchy.rb:104 nested set; frontend/src/app/shared/components/searchable-project-list/project-data.ts:38 children render as a tree in the project picker"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/adminjiraserver/defining-a-project-938847066.html): "https://confluence.atlassian.com/adminjiraserver/defining-a-project-938847066.html project categories are created 'via Administration > Projects > Project Categories', and Jira 'can display projects sorted by the project category' (read 2026-09-26)"

### `prj-hierarchy`: Nest projects as subprojects under a parent project or programme.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "the project schema (lib/Settings/planninq_register.json) has no parent/programme reference property; grep for parentProject/programme in src/ and lib/ finds nothing."
- Demand: none recorded on the row.
- Competitors rated yes (1):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "docs: opf/openproject HEAD 27a58131 docs/user-guide/projects/project-settings/project-information/README.md:42 'add a subproject'; subprojects free, portfolio and program workspace types Enterprise gated (corpus: openproject/round4/open-core.md portfolio_management; code-census #19 'Creating a project whose workspace_type is portfolio or program is rejected') (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/components/projects/row_actions_component.rb:64-65 'New subproject' needs add_subprojects; app/contracts/projects/base_contract.rb:45-46 parent attribute validated as assignable; app/contracts/projects/create_contract.rb:43-44 portfolio and ... (shortened; full text in the matrix row)"

### `prj-custom-fields`: Add your own fields to projects.

- Area `projects`. Planninq is rated `no`, built.state `none`, owner `ConductionNL/planninq`.
- Built evidence: "the project schema's only catch-all is `metadata` (lib/Settings/planninq_register.json, described as "Declared catch-all for source-system fields and provenance keys (e.g. jiraProjectId, migratedAt)"), which is for import provenance, not a user-facing custom-fields feature; no UI exposes it for editing."
- Demand: none recorded on the row.
- Competitors rated yes (1):
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "corpus: openproject/round4/menu-tree.md Administration 'Projects > Project attributes'; corpus: openproject/round4/M1-column.md 5.6 'app/models/project_custom_field.rb:59-68 ... admin-only fields' ; source read at v17.8.0: config/routes.rb:789 admin project_custom_fields resources; config/initializers/menus.rb:777 project settings 'Project attributes'; app/models/project_custom_field.rb:58-66 visibility with admin_only fields; config/locales/en.yml:3575 'Project attributes'"

## Scope

### In scope

- A `portfolio` schema, `project.portfolio`, grouping in the project list and board picker, portfolio read rights, and a per-portfolio risk scale.
- `project.parent`, a guard against cycles and depth, and subprojects on the parent's overview.
- A `fieldDefinition` schema for project fields and `project.customFields` for their values.

### Out of scope

- Portfolio roll-ups of status, money and time: `portfolio-status-overview` and `portfolio-finance`.
- Custom fields on tasks (Enterprise in FEATURES.md). `fieldDefinition.appliesTo` only accepts `project` in this change.
- Personal folders for your own boards. Your own project order belongs to `portfolio-my-work-dashboard` (lane A).

## Impact

- Schema: new `portfolio` and `fieldDefinition` schemas; `project` gains `portfolio`, `parent`, `customFields` and `portfolioReaders`.
- Listener: `ProjectHierarchyGuardListener` on OpenRegister's `ObjectCreatingEvent` and `ObjectUpdatingEvent` for the parent and custom field rules, and an update of `portfolioReaders` when a portfolio's managers change.
- Views: `ProjectList.vue`, `Boards.vue`, the project settings sidebar, the overview from `projects-overview-logs-risks`, and a portfolios and custom fields section on the admin page.
- Extends the flat specs `openspec/specs/projects.md` and `openspec/specs/portfolio-dashboard-pmo.md`.
- Depends on: `projects-overview-logs-risks` (the overview and the risk scale). `projects-members-and-roles` (the manager role and task 1.1's answer on `$lookup`).

## Risks

### Risk 1: portfolio read rights through a lookup

**Severity**: High
**Mitigation**: Letting portfolio managers read the projects in their portfolio needs a rule that follows `project.portfolio` to the portfolio's managers. Until `projects-members-and-roles` task 1.1 shows `$lookup` scopes, the rights ride on a denormalised `portfolioReaders` list on each project, kept in step by the listener when a project moves or a portfolio's managers change.

### Risk 2: custom field values bypass their definition

**Severity**: Medium
**Mitigation**: The creating and updating listener rejects a value whose type does not match its definition, and a required field left empty, with the field's label in the error.

### Risk 3: two more schemas change the schema count

**Severity**: Low
**Mitigation**: Task 1.5 updates `testRegisterDeclaresExactlySevenSchemas` and `openspec/specs/project-delivery/spec.md:68-72`.
