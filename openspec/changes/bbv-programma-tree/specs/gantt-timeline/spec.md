# Gantt Timeline Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds BBV Gantt timeline visualization of activiteiten across coalition period

## Purpose

Render a Gantt-style timeline where activiteiten are horizontal bars spanning their `start_datum` to `eind_datum` across the coalition period (e.g. 2026-2030). Timeline groups activiteiten by programma → doel; color indicates status; overlays mark delays.

## ADDED Requirements

### Requirement: Gantt Timeline Rendering [MVP]
The system MUST render activiteiten as horizontal bars on a timeline spanning the coalition period.

**Display:**
- Y-axis: grouped by programma (collapsed/expandable) → doel → activiteit
- X-axis: months from coalition start to end (e.g. Jan 2026 → Dec 2029)
- Each activiteit: horizontal bar from `start_datum` to `eind_datum` with status color
- Status colors: green (gereed) | orange (in_uitvoering, gepland) | red (vertraagd, uitgesteld) | grey (geannuleerd)
- Hover card: titel, verantwoordelijke, % done (from linked projects), end date
- Overdue overlay: red hatching if `eind_datum < today` and status != gereed

#### Scenario: Programma-level Gantt
- **GIVEN** programma "07 Mobiliteit" with 18 activiteiten spanning 2026-2030
- **WHEN** user navigates to programma detail and selects "Timeline" tab
- **THEN** 18 horizontal bars render grouped by doel, spanning their respective dates, with status colors and hover cards

#### Scenario: Delay detection
- **GIVEN** activiteit "7.3.1 Aanleg fietsstraat" with `eind_datum=2026-09-01`, `status="in_uitvoering"`, current date 2026-11-01
- **WHEN** Gantt renders
- **THEN** bar shows red overlay with label "61 dagen over de planning" and tooltip listing linked projects with their overdue tasks

#### Scenario: Organisatie-level Gantt
- **GIVEN** logged-in user is concern controller
- **WHEN** user selects menu "BBV" → "Timeline (All Programs)"
- **THEN** Gantt renders with ALL programma's and activiteiten from active coalition period; Y-axis grouped by programma → doel; many bars but comprehensible via grouping

### Requirement: Gantt Interactions [MVP]
Users MUST be able to interact with Gantt timeline.

**Features:**
- Scroll/pan horizontally across timeline (months)
- Click on bar → opens activiteit detail view (or drill into doel)
- Expand/collapse programma and doel groups
- Highlight bar on hover (darken color, show tooltip)
- Zoom controls to adjust timeline granularity (weeks, months, quarters)
- Export as PNG/PDF

#### Scenario: Drill from bar to activiteit detail
- **GIVEN** Gantt showing 100+ activiteiten
- **WHEN** user clicks on a bar "5.1.3 Vervangen klimaatinstallatie"
- **THEN** right-side panel opens (or modal) showing full activiteit details: linked projects, zaaktypes, budget, indicatoren, etc.

#### Scenario: Zoom timeline to weeks
- **GIVEN** Gantt at monthly granularity
- **WHEN** user clicks "Zoom in" or slider to weeks
- **THEN** X-axis redraws with weeks; bars are more granular; useful for short-term planning view

### Requirement: Progress Rollup from Projects [MVP]
Activiteit progress (%) MUST be calculated as weighted average of linked planix projects' task completion.

If activiteit has multiple linked projects, roll up their percent-done. If no projects linked, leave blank or show manual progress entry.

#### Scenario: Multi-project activiteit
- **GIVEN** activiteit "5.1.3" linked to 2 planix projects:
  - PRJ-2026-0142 (85% done, 10 tasks)
  - PRJ-2026-0203 (60% done, 6 tasks)
- **WHEN** Gantt renders
- **THEN** activiteit shows "(85% × 10 + 60% × 6) / 16 = 75% done" on bar and in tooltip

#### Scenario: Status pessimism
- **GIVEN** activiteit with 2 linked projects, one status "in_uitvoering", one status "vertraagd"
- **WHEN** Gantt renders
- **THEN** activiteit gets pessimistic status "vertraagd" (red) and tooltip "1 of 2 projects vertraagd"

### Requirement: Dependency Visualization (Optional for MVP)
If activiteit has predecessor/successor relationships (defined in linked projects), MUST show connector arrows between bars.

#### Scenario: Task dependencies
- **GIVEN** activiteit "7.3.1 Aanleg fietsstraat" must complete before "7.3.2 Verkeersregelingen instellen"
- **WHEN** Gantt renders
- **THEN** an arrow connects the end of 7.3.1 bar to the start of 7.3.2 bar

### Requirement: Legend and Status Indicators [MVP]
Gantt MUST include a legend explaining colors and status indicators.

**Legend items:**
- Green bar = gereed (completed)
- Orange bar = in_uitvoering or gepland (underway or planned)
- Red bar = vertraagd or uitgesteld (delayed or postponed)
- Grey bar = geannuleerd (cancelled)
- Red hatching overlay = overdue (eind_datum < today, status != gereed)
- Milestone diamond (optional) = key milestones (linked raadsbesluiten, gate reviews)

## Non-Functional Requirements

- **Performance:** Gantt with 200+ bars MUST render within 3 seconds; smooth horizontal scroll
- **Accessibility:** Legend text visible; color not sole indicator (text labels on bars); keyboard navigation (arrow keys to move selection)
- **Responsive:** Gantt responsive on 16:9 desktop and 4:3 tablet; horizontal scroll on mobile (acceptable)
- **Data freshness:** Timeline data updates within 1 min of project status change (refresh via webhook from planix-projects)

## Acceptance Criteria

- [ ] Gantt renders activiteiten as horizontal bars spanning start_datum to eind_datum
- [ ] Status colors (green/orange/red/grey) display correctly based on activiteit.status
- [ ] Overdue activiteiten show red hatching overlay and "X days late" label
- [ ] Hover card displays titel, verantwoordelijke, % done, eind_datum
- [ ] Click on bar drills to activiteit detail
- [ ] Expand/collapse works on programma and doel groups
- [ ] Zoom controls adjust timeline granularity (weeks, months)
- [ ] Progress % calculated correctly from linked projects
- [ ] Status pessimism applies (one project vertraagd → activiteit vertraagd)
- [ ] Legend displays and explains all status colors
- [ ] Gantt with 200+ bars renders within 3 seconds
- [ ] Dependency arrows display between linked activiteiten (if dependencies exist)

## Notes

- Timeline granularity defaults to months; can zoom to weeks or quarters
- "Today" marker (vertical line) shows current date for context
- Programma/doel grouping is collapsible to reduce visual clutter
- Future iteration may add Gantt "critical path" highlighting to identify bottleneck activiteiten
