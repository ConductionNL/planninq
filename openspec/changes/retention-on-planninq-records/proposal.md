---
kind: code
---

# Give planninq records a retention category

## Why

OpenRegister builds destruction lists from the retention data on each object, destroys the
listed records after an archivist approves the list, and keeps a destruction certificate
(openregister `openspec/specs/archival-destruction-workflow`). None of planninq's schemas
declares a selectielijst category (`lib/Settings/planninq_register.json` has no `archive`
block), so no project, task or time entry ever gets a retention period, and none of them can
reach a destruction list.

Parity rows: `plt-destruction-list` in planninq's `openspec/parity/capabilities.json`, rated
built until 12 Nov 2026 and set back to specified because no planninq record carries a retention
period.

## What changes

- The `project`, `task` and `plannedTimeEntry` schemas declare a selectielijst category, which
  the admin can change in the planninq admin settings.
- A closed or archived project and its records get an archive action date from that category,
  so OpenRegister's daily sweep can put them on a destruction list.
