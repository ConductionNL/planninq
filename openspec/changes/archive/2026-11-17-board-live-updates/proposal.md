---
kind: code
---

# Update the project board live

## Why

The project list and an open task page refresh when a colleague changes something
(`openspec/specs/realtime-updates`). The project board does not. `src/views/ProjectBoard.vue`
holds no live subscription, so a card a colleague moves stays in its old lane until you
reload the page.

Parity row: `col-live-updates` in `openspec/parity/capabilities.json`, rated built until
12 Nov 2026 and set back to specified because the board half is not built.

## What changes

- The board subscribes to the tasks of its project and moves, adds and removes cards when a
  colleague changes them.
- A card you are dragging is not moved under you.
