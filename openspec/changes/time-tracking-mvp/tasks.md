# Tasks: Time Tracking MVP

## Tasks

- [ ] 1. Add TimeEntry schema to OpenRegister configuration
  - [ ] 1.1 Add TimeEntry schema definition to `lib/Settings/planix_register.json` with properties: task (reference), user (string), duration (integer), date (date), description (string)
  - [ ] 1.2 Add `estimatedDuration` (integer, minutes) property to existing Task schema in `planix_register.json`
  - [ ] 1.3 Verify repair step imports the updated register config

- [ ] 2. Create DurationInput component
  - [ ] 2.1 Create `src/components/DurationInput.vue` that parses human-friendly input ("2h 30m", "2.5h", "150m", "150") into minutes
  - [ ] 2.2 Display formatted duration (e.g., 150 → "2h 30m")
  - [ ] 2.3 Support both input and display modes

- [ ] 3. Add time estimate to tasks
  - [ ] 3.1 Add estimate input field to task detail view using DurationInput component
  - [ ] 3.2 Save estimatedDuration to task object via useObjectStore
  - [ ] 3.3 Create `src/components/TimeEstimateBadge.vue` showing estimate on kanban cards (e.g., "⏱ 2h 30m")
  - [ ] 3.4 Add badge to kanban card component

- [ ] 4. Create time logging form
  - [ ] 4.1 Create `src/components/TimeEntryForm.vue` with fields: duration (DurationInput), date (date picker, default today), description (text)
  - [ ] 4.2 On submit, create TimeEntry object via useObjectStore linked to current task
  - [ ] 4.3 Show success feedback and reset form

- [ ] 5. Add time tracking section to task detail
  - [ ] 5.1 Add collapsible "Time Tracking" section to task detail page
  - [ ] 5.2 Show estimated vs logged time with progress bar (logged/estimated as percentage)
  - [ ] 5.3 List all TimeEntry objects for this task (date, duration, description, user)
  - [ ] 5.4 Add "Log Time" button that opens TimeEntryForm
  - [ ] 5.5 Allow deleting own time entries

- [ ] 6. Create Timesheet view
  - [ ] 6.1 Create `src/views/Timesheet.vue` showing current user's time entries
  - [ ] 6.2 Group entries by date with daily subtotals
  - [ ] 6.3 Show weekly total at the top
  - [ ] 6.4 Each entry links to its parent task
  - [ ] 6.5 Add date range selector (this week / last week / custom)

- [ ] 7. Add navigation and routing
  - [ ] 7.1 Add `/timesheet` route to `src/router/index.js`
  - [ ] 7.2 Add "Timesheet" item to `src/navigation/MainMenu.vue` with clock icon
