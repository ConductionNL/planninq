---
kind: code
---

# Set and show the case link on projects and tasks

## Why

A project or a task can point at a case in the case app, through `caseReference` on the
project and `zaakUuid` on the task (`lib/Settings/planninq_register.json`). Nobody can set
either from a screen. The project settings sidebar shows `caseReference` as plain read-only
text (`src/components/ProjectSettingsSidebar.vue:121-125`), without a badge or a link. The
task page does not show `zaakUuid` at all. The only screen that writes a case link is the New
project dialog opened from a case page (`int-project-from-case`).

`openspec/specs/procest-integration/spec.md` already asks for an edit field, a badge and a
link. This change builds them.

Parity rows: `int-case-ref` in planninq's `openspec/parity/capabilities.json`, rated built
until 12 Nov 2026 and set back to specified because the code only displays the raw value.

## What changes

- A project owner or manager picks, changes and clears the linked case on the Details tab of
  the project settings sidebar.
- The project list and the project overview show a case badge that links to the case.
- A project member picks or clears the linked case of a task on the task page, which shows it
  as a link.
