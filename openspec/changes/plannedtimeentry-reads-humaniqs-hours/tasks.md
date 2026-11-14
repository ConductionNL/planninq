# Tasks

## 0. Prerequisite

- [x] 0.1 humaniq's `TimeEntry` accepts a day booking (humaniq#323). Without it
      the owner refuses the shape this app records.

## 1. The schema

- [x] 1.1 `timeEntry` -> `plannedTimeEntry`, key AND `slug`, in the register
      descriptor.
- [x] 1.2 It keeps `contractorRef` and `hourlyRate` — the two the owner has no
      column for — and gains a reference to the humaniq `TimeEntry`.
- [ ] 1.3 `date`, `duration`, `user`, `description`, `billable`, `project` and
      `task` move to the owner. `task` maps onto the existing
      `domainObjectType`/`domainObjectRef` pair rather than a new field.
      (New writes put the hours, date and note on humaniq only, and
      `duration`/`date` are no longer required on `plannedTimeEntry`. The
      properties themselves stay until 4.1 has run: OpenRegister keeps a
      property's values in its column, so dropping them in the same release
      would drop what the move step reads. `task` and `user` stay as keys,
      not data: the server derives the project and its members from `task`,
      a task with logged time refuses deletion by it, and the access rules
      match on `user`. Drop the rest together with 4.2.)
- [x] 1.4 A repair step renames the row, scoped to `(application, slug)`. The
      import's not-found branch CREATES a second schema, so a descriptor change
      alone strands every existing row.
      (1.1, 1.2 and 1.4 were already on `development`: the `plannedTimeEntry`
      key and slug, its `timeEntry` reference, and `RenameTimeEntrySchemaSlug`.)

## 2. The widgets, which is the part that bites

- [x] 2.1 The four dashboard widgets reading `duration`/`user`/`date` repoint at
      humaniq's register and declare `requiredApp: humaniq`, so they HIDE when
      humaniq is absent instead of rendering empty. Same pattern pipelinq uses
      to read this app's `project`.
      (`TimeSpentReport`: the four widgets read humaniq's `TimeEntry`, filtered
      to `domainObjectType` `planninq:task`, sum `hours`, and declare
      `requiredApp: humaniq` plus `visibleWhen.appInstalled: humaniq`, which is
      what makes the dashboard drop the cell rather than show a set-up card.)

## 3. The stores

- [x] 3.1 `src/store/timeEntries.js` and `src/store/projects.js` resolve the
      owner's schema for the hours and this app's for the rate.
- [x] 3.2 Degradation when humaniq is absent, stated rather than discovered.
      (`src/utils/loggedHours.js` joins the two halves; without humaniq the
      stores read nothing and refuse a write with "Time logging needs the
      humaniq app", and the Log time dialog shows that sentence instead of a
      save.)

## 4. The rows

- [x] 4.1 Migrate existing entries: one humaniq `TimeEntry` per row, one
      `plannedTimeEntry` referencing it, `duration` minutes converted to hours.
      (`MoveLoggedHoursToHumaniq`. The row renamed in place IS the
      `plannedTimeEntry`; it gains the reference. The humaniq entry's uuid is
      derived from the row, so a run that stops between the two writes does
      not book the hours twice.)
- [ ] 4.2 `occ openregister:schemas:prune-retired --app=planninq --slug=timeEntry`
      once the rows are moved. It refuses while the schema still owns objects,
      which is the order it enforces.

## 5. Tests

- [x] 5.1 Unit test for the store: logging 90 minutes writes one humaniq `TimeEntry` (`hours` 1.5, `domainObjectType` `task`) and one `plannedTimeEntry` referencing it, validated against the real schemas in both register files.
      (`tests/vitest/loggedHours.spec.js`. `domainObjectType` is `planninq:task`,
      the `<app>:<schema>` form humaniq documents and the repair step writes;
      humaniq's schema is a copy in `tests/vitest/fixtures/`.)
- [x] 5.2 Unit test for the repair step: 45 minutes becomes 0.75 hours, and a second run creates nothing.
- [ ] 5.3 e2e: the timesheet shows "1h 30m" after logging; on an instance without humaniq the time widgets are absent and "Log time" names humaniq.
