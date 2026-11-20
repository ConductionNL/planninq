# Design: colour cards and split the board into swimlanes

## Context

Read at development `de35541`:

- `src/components/TaskCard.vue:229-237` `.task-card` uses `var(--color-surface)` and
  `var(--color-border)`; label chips carry a swatch (`:44-58`); priority is a chip (`:32-36`).
- `src/views/ProjectBoard.vue:103-175` renders one horizontal row of `<section>` lanes; drag and
  drop and the keyboard move menu work per lane.
- Schema `task`: `epic` (uuid of an epic-level task, unused in `src/`), `priority`, `labels`,
  `assignedTo`.
- Per-user preferences are stored through `SettingsController::updateUser`
  (`lib/Controller/SettingsController.php:147`) and `SettingsService::updateUserSettings`.

## Goals / non-goals

Goals: colour by label or priority, swimlanes by assignee, priority or epic, remembered per user.
Non-goals: custom colour rules, editing epics by drag.

## Decisions

### Decision 1: a coloured edge, not a coloured card

The card gets a 4 pixel inline-start border in the first label's colour, or in a priority token
(urgent `--color-error`, high `--color-warning`, normal and low none). Alternative: tint the card
background. Rejected: label colours are admin-chosen hex values and a tinted background breaks text
contrast in one of the two themes.

### Decision 2: swimlanes are rows over the same lanes

With grouping on, the board renders one row per group value (plus "No assignee", "No priority" or
"No epic"), each row holding the full set of lanes, with a collapsible header "{value} ({count})".
Grouping is a pure helper `groupTasksBySwimlane(tasks, field)` that returns ordered groups.
Epic names are read from the epic tasks' titles.

### Decision 3: dragging across swimlanes changes the field

A drop in another row PATCHes the grouped field together with the column move: `assignedTo` for
assignee rows, `priority` for priority rows. The assignee change goes through the existing `responsiblePatch`, so
the new responsible person also leaves `sharedWith`. A card added with a row's quick add gets that
row's value. Epic rows refuse a cross-row drop with a short
message. The keyboard move menu offers the same targets.

### Decision 4: remember the view per user and project

The choice is stored through the existing user settings endpoint and applied on mount. Amended
while building (29 Sep): one user value `board_views` holds a map of project id to view, kept by
`BoardViewPreferenceService` (at most 100 boards per person, the least recently saved dropped
first), because a key per project cannot be listed back by `getSettings()`.

## Risks / trade-offs

- [Many swimlanes times many lanes is a large DOM] -> collapsed rows render only their header;
  the default is no grouping.
