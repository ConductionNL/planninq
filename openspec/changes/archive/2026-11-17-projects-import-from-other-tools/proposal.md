---
kind: code
---

# Import a project from Jira or Trello

## Why

The parity row asks to import projects from Jira, Trello or a spreadsheet. OpenRegister's
register import covers spreadsheets (`openregister:openspec/specs/data-import-export`) and
whole bases from other products (`import-preview-and-conflict-policy`). Nothing names a Jira
or a Trello export, and nothing maps their lists, statuses and cards to planninq projects,
columns and tasks.

Parity row: `int-import-other` in `openspec/parity/capabilities.json`.

## What changes

- An admin imports a Trello board export (JSON) or a Jira project export (CSV). Each becomes
  a planninq project with columns and tasks.
- The mapping is configuration over OpenRegister's import, not a separate importer per tool.
