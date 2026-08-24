# Kanban Board

The primary visual interface for a project — tasks as cards organized into configurable columns.

## Overview

Each project has exactly one kanban board. Columns represent stages in the workflow (e.g., To Do, In Progress, Review, Done). Users drag task cards between columns to update their status. WIP limits on columns provide visual warnings when a stage is overloaded. The board can be filtered by assignee, label, or priority to focus on relevant work.

## Key Capabilities

- **Columns** — create, rename, reorder, and delete columns per project; column order is configurable
- **Default columns** — new projects are initialized with four columns (To Do, In Progress, Review, Done); defaults are configurable by admins
- **WIP limits** — set a work-in-progress limit per column; column header shows a warning indicator when the limit is exceeded (soft limit — cards are never blocked)
- **Task cards** — each card shows title, assignee avatar, due date, priority indicator, and label chips
- **Overdue highlight** — tasks with a past due date show a red border/badge on the card
- **Drag-and-drop** — drag a card to another column to update its column assignment and position
- **Column task count** — column header shows the current task count alongside the WIP limit
- **Board filter** — filter visible cards by assignee, label, or priority
- **View toggle** — switch between kanban (card grid) and list view for the same project tasks
- **Backlog access** — tasks without a column are in the backlog; a "View Backlog" link is available from the board view

## Standards

- Schema.org ItemList — kanban board (ordered list of columns)
- Schema.org DefinedTerm — column (controlled vocabulary term within the board)
- Kanban Guide (kanban.university) — WIP limit and flow practices

## Spec

- [kanban-board spec](https://codeberg.org/Conduction/planninq/src/branch/development/openspec/specs/kanban-board.md)
