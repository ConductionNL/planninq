---
kind: code
---

# Load example data that a user can actually see and try

## Why

The setup wizard offers "Load example data?", and loading it works (`src/manifest.json:3-37`, `lib/Controller/SetupController.php`, `lib/Service/DemoDataService.php:202-250` importing `lib/Settings/planninq_mock_register.json`). What it loads cannot be used:

- The three demo projects have `"members": []` (`lib/Settings/planninq_mock_register.json:135`, `:155`, `:175`), and the projects store keeps only projects whose members include the current user (`src/store/projects.js:194-196`). So the example projects appear on no Projects list, no Boards page and no Portfolio, for anyone.
- Every value is a placeholder: "Voorbeeld Title 1", owner "Voorbeeld Owner 1" (`:123-140`).
- Every reference between demo objects points at `00000000-0000-4000-8000-00000000000N`, an id no demo project or task carries, so columns, phases, tasks and time entries hang off nothing.
- The three demo dependency edges have the same task as blocker and blocked (`:69-70`, `:79-80`, `:89-90`), a self-dependency that `DependencyService` refuses on the normal path (`lib/Service/DependencyService.php:153-170`).

Meanwhile the realistic sample data (three projects with the admin as a member, twelve columns, five tasks, three time entries, five labels) sits in the real register file (`lib/Settings/planninq_register.json:1169` onward), where the ordinary register import seeds it on install. Hydra ADR-111 rule 3 says demo data never installs itself, and `DemoDataService` says the same in its own header.

Deck, OpenProject, Plane and Jira show a new user a populated example to try.

Parity rows: `plt-demo-data` in planninq's `openspec/parity/capabilities.json`.
Decision: build, because the loader works but the data it loads is invisible and broken, and four competitors give a new user a working example.

## What changes

- Loading the example data gives the admin who loads it three realistic projects they are a member and owner of, with columns, phases, tasks assigned to them, dependencies between different tasks, and logged time.
- Every reference in the example data points at an object the example data contains, and every example object passes its schema and planninq's own rules.
- Due dates and time entries are placed around the day the data is loaded, so boards and My tasks look current instead of months overdue.
- A new install gets the five default labels and nothing else. The sample projects move from the install to the example data.

## Evidence from the parity matrix

Matrix: `openspec/parity/capabilities.json` in ConductionNL/planninq (compared on 2026-09-26). Rows in this change: `plt-demo-data`.

### `plt-demo-data`: Load demo data to try the app out.

- Area `platform`. Planninq is rated `partial`, built.state `built`, owner `ConductionNL/planninq`.
- Built evidence: "setup step 'load-demo-data' -> lib/Controller/SetupController.php runAction -> lib/Service/DemoDataService.php:46,202 imports lib/Settings/planninq_mock_register.json"
- Defect: "lib/Settings/planninq_mock_register.json:135,155,175 demo projects have "members": [], so src/store/projects.js:195 filters them out of Projects, Boards and Portfolio for every user (code reading, needs a live check)"
- Defect: "lib/Settings/planninq_mock_register.json:123-140 demo objects are placeholder values ('Voorbeeld Title 1', owner 'Voorbeeld Owner 1') and their project/task references are 00000000-0000-4000-8000-00000000000N, which no demo project carries as its id"
- Defect: "lib/Settings/planninq_mock_register.json:69-70 demo dependency edges have blocker == blocked, a self-dependency lib/Service/DependencyService.php:113 rejects on the normal path"
- Note: "The loader works, but the dataset it loads is generated placeholder data that no page can show: the three projects have members [] so the member filter hides them from everyone, and every task/column points at nil uuids no project carries. The realistic sample data (3 projects with admin as member, 5 tasks, time entries) sits in lib/Settings/planninq_register.json components.objects instead and probably arrives with the ordinary register import, which is the opposite of opt-in (needs a live ... (shortened; full text in the matrix row)"
- Demand: none recorded on the row.
- Competitors rated yes (4):
  - Nextcloud Deck 1.18 (no URL in the cell; source or corpus citation only): "corpus: nextcloud-deck/round4/menu-tree.md 'All boards Vergunningen r4b6 · Welcome to Nextcloud Deck!' ; source: lib/Service/DefaultBoardService.php and lib/Service/fixtures/default-board.json create a sample board per user ; source read at v1.19.0: lib/Middleware/DefaultBoardMiddleware.php:29-30 on a user's first visit creates 'Welcome to Nextcloud Deck!' from lib/Service/DefaultBoardService.php:66 createDefaultBoard and lib/Service/fixtures/default-board.json:5-16 sample lists and cards, translated at lib/Service/DefaultBoardService.php:78-83; one sample per user, not an admin loader"
  - OpenProject 16 Community (no URL in the cell; source or corpus citation only): "source: opf/openproject HEAD 27a58131 (18.0.0-dev): app/seeders/demo_data_seeder.rb with demo_data/projects_seeder.rb, project_seeder.rb, global_query_seeder.rb; corpus: openproject/round4/install.md 'It self-migrates and self-seeds against an empty database' (cited source paths checked present at the released v17.8.0 tag.) ; source read at v17.8.0: app/seeders/demo_data_seeder.rb:29-30 DemoDataSeeder with its data seeder classes; app/seeders/demo_data/global_query_seeder.rb and overview_seeder.rb seed demo projects, queries and overviews on a fresh instance"
  - Plane Community 1.4 (no URL in the cell; source or corpus citation only): "source: apps/api/plane/app/views/workspace/base.py:137 workspace_seed.delay on workspace creation, seeds from apps/api/plane/seeds/data (projects, issues, cycles, modules, pages, views, labels, states); corpus: plane/round4/code-census.md §5 cites the seeded dummy data (workspace_seed_task.py:160,163). Automatic, not opt-in ; source read at v1.4.2: apps/api/plane/app/views/workspace/base.py:48,139 workspace create queues workspace_seed; apps/api/plane/bgtasks/workspace_seed_task.py:505 seeds from apps/api/plane/seeds/data/projects.json with issues, cycles, modules, pages, views, labels and states files beside it. Runs on every new workspace, not an opt-in button"
  - Jira Software Data Center 11 (https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html): "docs: https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html 'you can also choose to Create a Scrum board with sample data ... This will base your board on a new project pre-populated with sample data' ; docs read 2026-09-26: https://confluence.atlassian.com/jirasoftwareserver/creating-a-board-938845220.html 'you can also choose to Create a Scrum board with sample data ... This will base your board on a new project pre-populated with sample data' (read 2026-09-26)"


## Scope

### In scope

- A curated demo dataset in `lib/Settings/planninq_mock_register.json` for the headline schemas, kept by the ADR-111 generator's `--keep` and topped up by it.
- Two import-time adjustments in `DemoDataService::install`: the loading admin becomes owner, member and assignee, and dates shift to the load day.
- Moving the sample projects, columns, tasks and time entries out of `lib/Settings/planninq_register.json`, keeping the five labels there as install defaults.
- A test that every demo reference resolves and every demo dependency joins two different tasks of one project.

### Out of scope

- A "remove example data" action. Demo objects carry `demo-` slugs so they can be found; removal can follow.
- Deleting sample projects that earlier installs already received. They stay; this change only stops new installs from seeding them.
- Demo data for the humaniq side of time entries, which follows `plannedtimeentry-reads-humaniqs-hours`.

## Impact

- Data: `lib/Settings/planninq_mock_register.json` (rewritten from curated objects), `lib/Settings/planninq_register.json` (`components.objects` reduced to the five labels).
- Backend: `lib/Service/DemoDataService.php` (operator substitution and date shift before `importFromApp`).
- Specs: `register-schemas` loses the requirement "Seed data loaded on install [MVP]" and gains one for the default labels; a new capability `demo-data`.
- Tests: PHPUnit for the descriptor's references and edges and for the install-time adjustments; Playwright for the wizard path.
- Depends on: `boards-configurable-columns` (the demo columns carry the `status` mapping it adds) and `tasks-readable-keys` (the demo projects carry keys).

## Risks

### Risk 1: existing instances rely on the install seed
**Severity**: Medium
**Mitigation**: nothing is deleted. Instances that already have the sample projects keep them; only new installs start empty apart from the labels, and the wizard offers the same projects as example data.

### Risk 2: the curated data drifts from the schemas
**Severity**: Medium
**Mitigation**: the generator's `--check` validates the descriptor in the gates, and the new PHPUnit test resolves every reference, so a schema change that breaks the data fails the build.

### Risk 3: the loading admin is the only person in the example
**Severity**: Low
**Mitigation**: that is deliberate. Invented accounts such as `jdoe` do not exist on a customer's instance, so tasks assigned to them would show an unknown user. The example assigns to the admin who loaded it, and leaves some tasks unassigned.
