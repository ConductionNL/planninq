# Tasks

## 0. Prerequisite

- [x] 0.1 humaniq's `TimeEntry` accepts a day booking (humaniq#323). Without it
      the owner refuses the shape this app records.

## 1. The schema

- [x] 1.1 `timeEntry` -> `plannedTimeEntry`, key AND `slug`, in the register
      descriptor. (Already on development.)
- [x] 1.2 (Already on development: `timeEntry` uuid reference on the schema.) It keeps `contractorRef` and `hourlyRate` — the two the owner has no
      column for — and gains a reference to the humaniq `TimeEntry`.
- [ ] 1.3 `date`, `duration`, `user`, `description`, `billable`, `project` and — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
      `task` move to the owner. `task` maps onto the existing
      `domainObjectType`/`domainObjectRef` pair rather than a new field.
- [x] 1.4 (Already on development: `RenameTimeEntrySchemaSlug` with `RenameTimeEntrySchemaSlugTest`.) A repair step renames the row, scoped to `(application, slug)`. The
      import's not-found branch CREATES a second schema, so a descriptor change
      alone strands every existing row.

## 2. The widgets, which is the part that bites

- [ ] 2.1 The four dashboard widgets reading `duration`/`user`/`date` repoint at — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
      humaniq's register and declare `requiredApp: humaniq`, so they HIDE when
      humaniq is absent instead of rendering empty. Same pattern pipelinq uses
      to read this app's `project`.

## 3. The stores

- [ ] 3.1 `src/store/timeEntries.js` and `src/store/projects.js` resolve the — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
      owner's schema for the hours and this app's for the rate.
- [ ] 3.2 Degradation when humaniq is absent, stated rather than discovered. — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme

## 4. The rows

- [ ] 4.1 Migrate existing entries: one humaniq `TimeEntry` per row, one — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
      `plannedTimeEntry` referencing it, `duration` minutes converted to hours.
- [ ] 4.2 `occ openregister:schemas:prune-retired --app=planninq --slug=timeEntry` — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
      once the rows are moved. It refuses while the schema still owns objects,
      which is the order it enforces.

## 5. Tests

- [ ] 5.1 Unit test for the store: logging 90 minutes writes one humaniq `TimeEntry` (`hours` 1.5, `domainObjectType` `task`) and one `plannedTimeEntry` referencing it, validated against the real schemas in both register files. — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
- [ ] 5.2 Unit test for the repair step: 45 minutes becomes 0.75 hours, and a second run creates nothing. — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
- [ ] 5.3 e2e: the timesheet shows "1h 30m" after logging; on an instance without humaniq the time widgets are absent and "Log time" names humaniq. — not run: needs humaniq (the owner register and its widgets) and a live instance; the hours move is a cross-repo programme
