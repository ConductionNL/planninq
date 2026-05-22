# Tree Visualization Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds BBV programma tree visualization

## Purpose

Render a collapsible vertical tree of programma's → doelen → activiteiten → indicatoren with color coding, status icons, and counts per node. The tree is the primary navigational structure for the BBV hierarchy.

## ADDED Requirements

### Requirement: Programma Tree Rendering [MVP]
The system MUST render a collapsible tree of all programma's in a gemeente's coalition period, with each programma as a root node and child nodes for doelen, activiteiten, and indicatoren.

**Must display on each node:**
- `nummer` and `titel` (e.g. "03 Veiligheid")
- Color band matching `programma.kleur` (hex color from design)
- Status icon reflecting `status` (concept=blue outline, vastgesteld=green checkmark, in_uitvoering=orange gear, afgesloten=grey)
- Count badges: "4 doelen" "11 activiteiten"
- Expand/collapse chevron if node has children

**Tree structure:**
- Root: all programma's for gemeente + coalition_period, sorted by `volgorde`
- Level 1: doelen under each programma, sorted by `volgorde`
- Level 2: activiteiten under each doel, sorted by `volgorde`
- Level 3: indicatoren under each activiteit, sorted by `volgorde`

#### Scenario: Open programma overview
- **GIVEN** a gemeente has 12 programma's for coalition_period 2026-2030
- **WHEN** a user navigates to `/planix/bbv/`
- **THEN** all 12 programma's render as collapsed root nodes with nummer, titel, portefeuillehouder name, color band, and child count

#### Scenario: Expand to indicator level
- **GIVEN** programma "03 Veiligheid" with 4 doelen and 11 activiteiten
- **WHEN** user clicks expand on programma "03" and then on doel "3.2 Veilige openbare ruimte"
- **THEN** 3 activiteiten appear below doel, each with status and timeline; 5 indicatoren display under the doel with current measured value and sparkline trend

#### Scenario: Status icon visibility
- **GIVEN** a tree with mix of programma statuses: concept (1), vastgesteld (8), in_uitvoering (2), afgesloten (1)
- **WHEN** tree renders
- **THEN** each programma displays its corresponding status icon (blue outline, green check, orange gear, grey)

### Requirement: Node Context Menu [MVP]
Each node in the tree MUST have a three-dot menu allowing inline actions.

**Menu actions by node type:**

| Node Type | Actions |
|-----------|---------|
| Programma | Edit, Delete, Add Doel, Archive, Change Status, Link Raadsbesluit |
| Doel | Edit, Delete, Add Activiteit, Duplicate |
| Activiteit | Edit, Delete, Link Project/Zaaktype, View Budget, Duplicate |
| Indicator | Edit, Delete, Add Meting, View Trend, Duplicate |

#### Scenario: Edit programma via context menu
- **GIVEN** programma "07 Mobiliteit" rendered in tree
- **WHEN** user clicks three-dot menu → "Edit"
- **THEN** a modal opens with form fields for titel, omschrijving, portefeuillehouder, taakgebied codes, etc.

#### Scenario: Add child node
- **GIVEN** programma "05 Onderwijs" with 2 doelen
- **WHEN** user clicks three-dot menu → "Add Doel"
- **THEN** a form modal opens with auto-populated `nummer` (next in sequence, e.g. "5.3") and empty `titel`

### Requirement: Search and Filter [MVP]
The tree view MUST support search-as-you-type and filtering by status.

**Search:** Searches across `nummer`, `titel`, `omschrijving` of all node types; highlights matches in tree; collapses non-matching branches.

**Filter:** Dropdown or checkbox group to show/hide programma by status (concept, vastgesteld, in_uitvoering, afgesloten).

#### Scenario: Search for activity by keyword
- **GIVEN** a tree with 50+ nodes
- **WHEN** user types "fiets" in search box
- **THEN** tree collapses to show only nodes matching "fiets" (e.g. activiteit "3.2.1 Aanleg fietsstraat" and its parents)

#### Scenario: Filter by status
- **GIVEN** all 12 programma's visible
- **WHEN** user selects filter "vastgesteld" only
- **THEN** tree shows only the 8 vastgesteld programma's

### Requirement: Drag-and-Drop Reordering [MVP]
Nodes within same parent MUST be drag-and-drop reorderable; reordering updates `volgorde` field.

#### Scenario: Reorder doelen within programma
- **GIVEN** programma "03" with 4 doelen
- **WHEN** user drags doel "3.4" above "3.1"
- **THEN** `volgorde` values update; tree re-renders with new order; change is persisted to backend

### Requirement: Inline Summary Data [MVP]
On each programma node, display inline summary stats without expanding:
- Number of vastgesteld doelen
- Number of overdue activiteiten (eind_datum < today, status != gereed)
- Total budget (sum of budget_koppeling.bedrag_begroot for all activiteiten)
- Budget variance (sum of (bedrag_realisatie - bedrag_begroot))

#### Scenario: Programma summary on collapsed node
- **GIVEN** programma "07 Mobiliteit" collapsed in tree
- **WHEN** tree renders
- **THEN** programma node shows inline: "3/4 doelen vastgesteld | 2 activiteiten verlate | Budget 4.25M EUR | -180K EUR variance"

## Non-Functional Requirements

- **Performance:** Tree with 100+ programma's and 1000+ total nodes MUST render within 2 seconds and support scroll lag-free
- **Accessibility:** Expand/collapse actions accessible via keyboard (Enter/Space on focused node); tree navigable with arrow keys; WCAG 2.2 AA
- **Responsive:** Tree MUST be readable on desktop and 10"+ tablet; collapsible menu accessible on mobile (out of scope for initial MVP)
- **Memory:** Lazy-load child nodes only on expand (virtual scrolling for large lists)

## Acceptance Criteria

- [ ] Programma tree renders with all 12 programma's as collapsed root nodes
- [ ] Color bands and status icons display correctly per design
- [ ] Child count badges show accurate "X doelen" / "Y activiteiten" / "Z indicatoren"
- [ ] Expand/collapse toggles load and display children without page reload
- [ ] Context menu actions (edit, delete, add child) trigger modals/updates
- [ ] Search highlights matching nodes and collapses non-matching branches
- [ ] Status filter shows/hides programma's correctly
- [ ] Drag-and-drop reorders nodes and updates `volgorde` on backend
- [ ] Inline summary stats (doelen count, overdue count, budget, variance) display on each programma node
- [ ] Tree renders 100 nodes within 2 seconds
- [ ] Keyboard navigation works (arrow keys, Enter/Space to expand)

## Notes

- Initial MVP focuses on desktop tree view; mobile-optimized sidebar nav may be added in future change
- Programma árenden are filtered by `gemeente_orgaan_id` (logged-in user's org) and `coalition_period` (configurable per view, default current year)
- Tree state (expanded/collapsed nodes) stored in browser localStorage per user
