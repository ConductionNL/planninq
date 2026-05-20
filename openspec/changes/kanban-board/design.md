# Design: Kanban Board

## Context

Planix stores Projects and Tasks in OpenRegister. Tasks have no column assignment yet — there is no schema property linking a Task to a board stage, and no Vue view renders tasks in a board layout. This change introduces a new Column entity and a kanban board view that becomes the primary project interface.

The kanban board is explicitly **not** a standard `CnIndexPage` use-case: it requires custom drag-and-drop layout, column-scoped card groups, and WIP limit visualisation that the generic list page does not support. The board view is built as a custom Vue component that uses `useObjectStore` for data operations and delegates detail navigation to the existing `CnDetailPage` pattern.

---

## Goals / Non-Goals

**Goals:**
- Render task cards in configurable column lanes, ordered by `columnOrder`
- Drag-and-drop moves tasks between columns with optimistic UI and failure rollback
- WIP limit exceeded shows a visual warning (soft limit — task is accepted)
- Filters (assignee, label, priority) applied in-place without full page reload; state in URL hash
- Column management CRUD from project settings; safe delete dialog when tasks exist
- View toggle (kanban ↔ list) with active view and filter persisted in URL hash
- Empty states for no-columns and empty-column scenarios with appropriate CTAs
- Board is keyboard-navigable (WCAG AA)

**Non-Goals (V1 deferred):**
- Swimlanes (group cards by assignee or priority within a column)
- Card quick-edit (inline title/due-date/assignee editing on hover)
- Collapsed columns (horizontal space saving)
- Blocked task indicators (`blocks` dependency lock icon)

---

## Decisions

### Decision 1: Column as a first-class OpenRegister entity

Columns are stored as OpenRegister objects under the Column schema, not as embedded JSON on the Project. This means columns participate in the full OpenRegister lifecycle: RBAC, audit trail, change history, and API access. The trade-off is one extra API call to fetch columns per board load — acceptable given columns change infrequently and are cached in the store.

Schema alignment: `schema:DefinedTerm` (a term defined within a `DefinedTermSet` — i.e. a column defined within a project's workflow). The `project` property is an OpenRegister relation (register + schema + objectId), not a foreign key.

### Decision 2: Drag-and-drop via vue-draggable (SortableJS)

The kanban board uses `vue-draggable` (the Vue 2 wrapper around SortableJS) for column and card reordering. This is called out explicitly in the context-brief as the intended library. `vue-draggable` is a well-maintained library compatible with Vue 2 Options API. No custom drag implementation is needed.

### Decision 3: Optimistic UI with rollback

When a card is dragged to a new column, the Vue store updates the card's position immediately (optimistic). The `saveObject` call runs in the background. If it fails:
- The store reverts the card to its previous `column` and `columnOrder`.
- A Nextcloud toast notification (`showError`) is shown to the user.
This avoids the UI freezing on every drag and gives instant feedback in the common (success) case.

### Decision 4: WIP limit is a soft limit

Exceeding the WIP limit does not block the drag. The column header changes to `var(--color-error)` and a tooltip explains "WIP-limiet (N) overschreden". This matches the Kanban Guide principle that limits are advisory — teams decide when to break them. Blocking would require negotiation with backend enforcement that is out of scope for MVP.

### Decision 5: View toggle + filter in URL hash

The active view (`kanban` | `list`) and active filters (`?filter=assignee:me,label:bug`) are encoded in the URL hash so the page reloads in the same state and the URL is shareable. The Vue router `beforeEach` guard reads hash params on entry and populates store state. Switching views preserves the active filter.

### Decision 6: Column deletion safety dialog

Deleting a column that contains tasks requires a confirmation step. The dialog (`DeleteColumnDialog.vue`) shows the task count and offers two options: "Naar backlog verplaatsen" (clears `column` on all tasks) or "Naar andere kolom verplaatsen" (reassigns tasks to a user-selected column). The column is not deleted until the user confirms a destination. This is implemented as a standalone `src/dialogs/DeleteColumnDialog.vue` (NcDialog-based), per ADR-004's modal isolation rule.

### Decision 7: List view uses CnDataTable

The list view is a `CnDataTable` rendered inside a lightweight wrapper page. Columns: title (link to detail), assignee (avatar + name), due date (red if overdue), status (`CnStatusBadge`), priority (dot + label), labels (chips). Sorting is handled by `CnDataTable`'s built-in header click handler. No custom sort logic is needed.

---

## Schema: Column

Aligned to `schema:DefinedTerm` (a controlled vocabulary term within a project's workflow stages).

```json
{
  "title": "Column",
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "description": "A stage column on a project's kanban board, aligned to schema:DefinedTerm.",
  "type": "object",
  "properties": {
    "title": {
      "type": "string",
      "description": "Display label for the column (e.g. 'In uitvoering', 'Gereed').",
      "minLength": 1,
      "maxLength": 80
    },
    "project": {
      "type": "object",
      "description": "OpenRegister relation to the owning Project object.",
      "properties": {
        "id":       { "type": "string", "format": "uuid" },
        "register": { "type": "string" },
        "schema":   { "type": "string" }
      },
      "required": ["id", "register", "schema"]
    },
    "order": {
      "type": "integer",
      "description": "Zero-based display order of this column on the board.",
      "minimum": 0,
      "default": 0
    },
    "wipLimit": {
      "type": ["integer", "null"],
      "description": "Maximum number of tasks allowed in this column (soft limit). Null means no limit.",
      "minimum": 1,
      "default": null
    },
    "color": {
      "type": "string",
      "description": "Hex colour for the column header (e.g. '#2196F3'). Optional.",
      "pattern": "^#[0-9A-Fa-f]{6}$"
    },
    "type": {
      "type": "string",
      "enum": ["active", "done"],
      "description": "Column type. 'done' columns automatically resolve task status on card drop.",
      "default": "active"
    }
  },
  "required": ["title", "project", "order"]
}
```

### Task schema patch (non-breaking additions)

Two optional properties are added to the existing Task schema:

| Property | Type | Description |
|---|---|---|
| `column` | OpenRegister relation (→ Column) | Which column this task belongs to on the board. Null = backlog. |
| `columnOrder` | integer | Sort position within the column. Lower = higher on the board. |

---

## Reuse Analysis

Per ADR-001, apps MUST document existing OpenRegister/platform capabilities leveraged — and must NOT rebuild what the platform provides.

| Platform capability | Usage in this change |
|---|---|
| `createObjectStore(name)` | Column store (`src/store/modules/columns.js`) and Task store (existing). No custom Pinia store for CRUD. |
| `ObjectService.saveObject()` / `findObjects()` | Used by the column store for CRUD and for fetching tasks by column. |
| `CnEmptyState` | Empty board (no columns) and empty column states. |
| `CnStatusBadge` | Status indicator on task cards in kanban and list views. |
| `CnDetailPage` | Task detail — board navigates to existing task detail page; no custom detail page. |
| `CnDataTable` | List view table rendering with sort. No custom table. |
| `CnFormDialog` | Task creation form (column pre-selected via prop) and column creation form. No custom form. |
| `CnDeleteDialog` | Base for column deletion (extended to `DeleteColumnDialog.vue` to add destination picker). |
| `useObjectStore` | Data operations in board view for optimistic updates and rollback. |
| `CnActionsBar` / `useListView` | Filter controls in the list view; URL-hash state management composable. |
| `NcDialog` | `DeleteColumnDialog.vue` modal (per ADR-004: every dialog in its own `.vue` file). |

**Not rebuilt (confirmed absent in platform):** drag-and-drop kanban layout, WIP limit indicator, column-scoped card grouping, view toggle with URL-hash persistence. These are custom to Planix's board domain.

**Deduplication check:** Searched `openspec/specs/` and existing Vue components for any prior kanban board, column entity, or view-toggle implementation — none found. The Column schema is net-new.

---

## Seed Data

Per ADR-001, every app schema requires 3–5 realistic seed objects with Dutch values. The Column schema is introduced by this change; seed data is added to `lib/Settings/planix_register.json`.

### Column objects

```json
[
  {
    "@self": {
      "register": "planix",
      "schema":   "column",
      "slug":     "col-amsterdam-backlog"
    },
    "title":    "Backlog",
    "project":  { "id": "proj-gemeente-amsterdam", "register": "planix", "schema": "project" },
    "order":    0,
    "wipLimit": null,
    "color":    "#607D8B",
    "type":     "active"
  },
  {
    "@self": {
      "register": "planix",
      "schema":   "column",
      "slug":     "col-amsterdam-in-uitvoering"
    },
    "title":    "In uitvoering",
    "project":  { "id": "proj-gemeente-amsterdam", "register": "planix", "schema": "project" },
    "order":    1,
    "wipLimit": 3,
    "color":    "#2196F3",
    "type":     "active"
  },
  {
    "@self": {
      "register": "planix",
      "schema":   "column",
      "slug":     "col-amsterdam-review"
    },
    "title":    "Review",
    "project":  { "id": "proj-gemeente-amsterdam", "register": "planix", "schema": "project" },
    "order":    2,
    "wipLimit": 2,
    "color":    "#FF9800",
    "type":     "active"
  },
  {
    "@self": {
      "register": "planix",
      "schema":   "column",
      "slug":     "col-amsterdam-gereed"
    },
    "title":    "Gereed",
    "project":  { "id": "proj-gemeente-amsterdam", "register": "planix", "schema": "project" },
    "order":    3,
    "wipLimit": null,
    "color":    "#4CAF50",
    "type":     "done"
  },
  {
    "@self": {
      "register": "planix",
      "schema":   "column",
      "slug":     "col-conduction-in-progress"
    },
    "title":    "In progress",
    "project":  { "id": "proj-conduction-bv", "register": "planix", "schema": "project" },
    "order":    1,
    "wipLimit": 4,
    "color":    "#9C27B0",
    "type":     "active"
  }
]
```
