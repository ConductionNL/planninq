---
kind: code
---

# Attach a file that is already in Files

## Why

`openspec/specs/task-collaboration` says how a member uploads a file to a task. It does not
say how a member attaches a file that is already in the organisation's Nextcloud Files. That
is what the parity row asks, and what board PlTaakHistorie draws ("Bestand uit Bestanden
kiezen"). The Files tab (`CnFilesTab` in nextcloud-vue) has a Files browser, but no scenario
or test pins that behaviour.

Parity row: `tsk-attachments` in `openspec/parity/capabilities.json`.

## What changes

- A member picks a file from Files on the task's Files tab. The task points at the file and
  the file stays where it is.
