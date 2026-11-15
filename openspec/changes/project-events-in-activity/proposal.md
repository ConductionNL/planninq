---
kind: code
---

# Publish project events to the Nextcloud activity stream

## Why

Planninq publishes task events to the Nextcloud activity stream through
`lib/Listener/TaskActivityListener.php`, which `lib/AppInfo/Application.php:366` binds to the
`task` schema only. When someone creates a project, archives it, restores it or changes who is
on it, the project's people read nothing about it in their activity stream. The capability row
asks for task and project events.

Parity rows: `col-activity` in planninq's `openspec/parity/capabilities.json`, rated built until
12 Nov 2026 and set back to specified because only task events reach the stream.

## What changes

- Project created, archived, restored and deleted, and a person added to or removed from a
  project, appear in the activity stream of the project's people, under the "Planninq" filter.
- The person who made the change gets no entry for it.
