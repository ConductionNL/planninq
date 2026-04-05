# Tasks — Time Tracking MVP

## Task 1: Backend — TimeEntry CRUD endpoints
**spec_ref**: time-tracking.md → Requirement: Time Entry Logging
**files_likely_affected**: lib/Controller/TimeEntryController.php (new), lib/Service/TimeEntryService.php (new), appinfo/routes.php
**acceptance_criteria**:
- [x] `POST /api/time-entries` creates a time entry (taskId, hours, date, description)
- [x] `GET /api/time-entries?taskId={id}` lists time entries for a task
- [x] `DELETE /api/time-entries/{id}` deletes a time entry (owner only)
- [x] Validation: hours > 0, date required, taskId must exist
- [x] Returns 403 for non-authenticated users

## Task 2: Frontend — Time log component on task detail
**spec_ref**: time-tracking.md → Scenario: View time log
**files_likely_affected**: src/components/TimeLog.vue (new), src/views/TaskDetail.vue
**acceptance_criteria**:
- [x] TimeLog.vue shows list of time entries for the current task
- [x] Each entry shows: date, hours, description, delete button (if owner)
- [x] "Log time" form with hours input, date picker, description field
- [x] Form validates hours > 0 before submission
- [x] Empty state: "No time logged yet" with prompt to log first entry
