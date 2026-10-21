# Design: load example data that a user can actually see and try

## Context

What exists at de35541:

- The setup wizard's steps "Load example data?" and "Load the example data" (`src/manifest.json:3-37`) run the `load-demo-data` action through `SetupController`, which calls `DemoDataService::install()` (`lib/Service/DemoDataService.php:202-250`). It reads `lib/Settings/planninq_mock_register.json`, decodes it, and hands it to OpenRegister's `importFromApp` under its own identity `planninq.demo` with `force: true` (`:222-233`). The header states the rule: on demand only, never on install (hydra ADR-111 rule 3).
- The mock descriptor holds 21 objects, three per schema, generated from the schemas by the ADR-111 generator: titles such as "Voorbeeld Title 1", owners such as "Voorbeeld Owner 1", `members: []` on every project (`:135`, `:155`, `:175`), no `id` on any object, references to `00000000-0000-4000-8000-00000000000N` that no object carries, and dependency edges with `blocker` equal to `blocked` (`:69-90`).
- `fetchProjects` keeps only projects whose `members` include the current user (`src/store/projects.js:194-196`); `Boards.vue` and `Portfolio.vue` read the same list. A project with no members is invisible to everyone.
- `DependencyService::assertDistinctTasks` rejects a self edge (`lib/Service/DependencyService.php:153-170`); the import path does not run it.
- The real register file carries 28 objects in `components.objects` (`lib/Settings/planninq_register.json:1169` onward): five labels, three realistic projects ("Client Portal v2", "Infrastructure Migration", "Onboarding Automation") with `owner: admin` and members such as `jdoe` and `ksmits`, twelve columns, five tasks and three time entries by `jdoe` and `ksmits`, with fixed ids and consistent references. The shipped capability `register-schemas` requires these on install ("Seed data loaded on install [MVP]", `openspec/specs/register-schemas/spec.md:99-126`).
- ADR-111 rule 2: demo data is generated from the schema by `generate_mock_register.py`, and "an app that wants curated, domain-true data for a headline schema keeps it: `--keep` tops up only what is short"; `--check` re-validates.

## Goals / non-goals

Goals:
- The admin who loads the example sees it on every page and can try every flow with it.
- The example is internally consistent and passes the same rules as real data.
- A fresh install holds configuration only.

Non-goals:
- Removing example data, cleaning up earlier installs, humaniq-side demo hours.

## Decisions

### Decision 1: the realistic sample becomes the demo dataset
The three projects, their columns, tasks and time entries move from `planninq_register.json` into the mock descriptor as curated objects, extended to satisfy ADR-111 rule 1 (at least three per schema): three phases on "Client Portal v2", tasks spread over the projects with `issueType`, priorities and labels, three dependency edges, three time entries. Every object has a fixed `id` and a `demo-` slug, and every reference uses those ids. The generator runs with `--keep` so it keeps these and tops up any schema that is short; `--check` runs in the gates.

### Decision 2: the loading admin is the example's person
The descriptor writes the placeholder `@operator` wherever a user id belongs: project `owner` and `members`, some tasks' `assignedTo` and `reporter`, and time entries' `user`. `DemoDataService::install()` replaces it with the uid of the admin who runs the step, in memory, before `importFromApp`. Invented accounts are not used, because they do not exist on a customer's instance; a few tasks stay unassigned to show that state.

### Decision 3: dates are relative to the load day
The descriptor's `x-openregister` block names an `anchorDate`. `install()` shifts every `date` and `date-time` value in the objects by the difference between the anchor and today, so a task due "anchor plus three days" is due three days after loading, one is overdue by two days, and time entries fall in the current week.

Amended at build: the ADR-111 generator rewrites `info` and `x-openregister` when it runs with `--keep`, so a regeneration drops `anchorDate`. `install()` then keeps the dates as written rather than guessing, and `DemoDatasetTest::testDescriptorNamesItsAnchorAndNoAccount` fails, so the loss cannot ship unnoticed. Without a signed-in user (`install()` called outside a request) the import is refused rather than written with no owner.

### Decision 4: the demo edges are valid edges
The three dependency objects join different tasks of the same project and form no cycle, the rules `DependencyService` applies on the normal path. A PHPUnit test runs the descriptor through a copy of those rules, because the import path does not.

### Decision 5: a fresh install seeds only the default labels
`planninq_register.json` keeps the five labels (Bug, Feature, Docs, Design, Infrastructure) in `components.objects` as install defaults: they are configuration a team uses from day one, like the default columns. The requirement "Seed data loaded on install [MVP]" in `register-schemas` is removed and replaced by one that says only these labels are created on install. Instances that already received the sample projects keep them; nothing is deleted.

## Risks / trade-offs

- [Existing instances] -> Nothing deleted; only new installs change.
- [Curated data drift] -> `--check` plus the reference test.
- [Placeholder leaking] -> A PHPUnit test asserts no `@operator` survives `install()`'s transformation.
