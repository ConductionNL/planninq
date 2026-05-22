# Activiteit-Project Linking Specification (Delta)

**Status**: in-progress  
**Scope**: planix  
**OpenSpec changes**:
- [bbv-programma-tree](../../) — adds linking of BBV activiteiten to planix projects and procest zaaktypes

## Purpose

Allow users to link a `bbv_activiteit` to one or more planix `projects` and/or `procest zaaktype` codes. Project progress automatically rolls up to activiteit progress; case closures auto-increment output indicators.

## ADDED Requirements

### Requirement: Link Activiteit to Planix Project [MVP]
A bbv_activiteit MUST support linking to one or more existing planix projects via `planix_project_ids` array.

**Link actions:**
- Add project: User clicks "Link Project" → search dialog filtered to projects from same gemeente_orgaan_id with matching status (active/planning)
- Remove project: User clicks X on project card in activiteit detail
- View all: "Linked projects" section shows cards with project title, status, % done, owner

**Project card displays:**
- Titel
- Status (planning | execution | monitoring | closed)
- % task completion
- Owner/PM name
- "View in Planix Projects" link

#### Scenario: Link activiteit to project
- **GIVEN** activiteit "5.1.3 Vervangen klimaatinstallatie zwembad De Plons" with no linked projects
- **WHEN** user clicks "Link Project" and searches for "klimaat"
- **THEN** finds project "PRJ-2026-0142 Klimaat zwembad"; user clicks "Link"
- **THEN** project card appears in activiteit detail; activiteit progress now calculated from project's task completion

#### Scenario: Multiple linked projects
- **GIVEN** activiteit "3.4.2 Opzetten jeugdpreventieprogramma" with 2 linked projects
- **WHEN** user views activiteit detail
- **THEN** sees two project cards: "PRJ-2026-0301 Mentorproject" (75% done) and "PRJ-2026-0302 Sportactiviteiten" (60% done)
- **AND** activiteit progress shown as "(75% + 60%) / 2 = 67.5%"

### Requirement: Project Progress Rollup to Activiteit [MVP]
Activiteit's progress (%) MUST be calculated as unweighted average of linked projects' task completion percentage.

If project status is:
- `planning` → 0% done
- `execution` → (completed_tasks / total_tasks * 100)
- `monitoring` → (completed_tasks / total_tasks * 100)
- `closed` → 100% done

#### Scenario: Activiteit progress from projects
- **GIVEN** activiteit with 3 linked projects:
  - PRJ-A: 40 tasks, 30 completed = 75%
  - PRJ-B: 20 tasks, 16 completed = 80%
  - PRJ-C: status=closed = 100%
- **WHEN** activiteit progress calculated
- **THEN** shows "(75 + 80 + 100) / 3 = 85% done"

#### Scenario: Status pessimism
- **GIVEN** activiteit with 2 linked projects:
  - PRJ-A: status=execution, 75% done
  - PRJ-B: status=monitoring (implies delay from plan)
- **WHEN** activiteit status derived from project states
- **THEN** activiteit gets pessimistic status "vertraagd" (orange or red) and tooltip "1 of 2 projects in monitoring (delayed)"

### Requirement: Link Activiteit to Procest Zaaktype [MVP]
A bbv_activiteit MUST support linking to one or more procest zaaktype codes via `procest_zaaktype_codes` array.

**Link actions:**
- Add zaaktype: User clicks "Link Zaaktype" → search dialog showing available zaaktypen from gemeente registry
- Remove zaaktype: User clicks X on zaaktype card
- View linked: "Output Zaken" section shows count of closed zaken per zaaktype for current year

#### Scenario: Link activiteit to zaaktype
- **GIVEN** activiteit "6.2.1 Verlenen bouwvergunningen sneller" with no zaaktype links
- **WHEN** user clicks "Link Zaaktype" and searches for "bouw"
- **THEN** finds zaaktype "Bouwen zonder vergunning vergunning"; user clicks "Link"
- **THEN** zaaktype appears in activiteit detail

#### Scenario: Output indicator from zaak closure
- **GIVEN** activiteit "6.2.1" linked to zaaktype "Bouwvergunning"
- **AND** output indicator "6.2.1 Aantal verleende bouwvergunningen" of type "output"
- **WHEN** a zaak of type "Bouwvergunning" is closed (event from procest)
- **THEN** indicator's `bbv_meting` value incremented by 1 (aggregated daily/hourly from procest event stream)

### Requirement: Bidirectional Raadsbesluit Linking [MVP]
A bbv_activiteit MUST link to one or more decidesk raadsbesluiten that authorize the work.

**Raadsbesluit card displays:**
- Raadsbesluit number (e.g. "RB-2025-189")
- Datum vaststelling
- Titel
- "View in Decidesk" link

#### Scenario: Link activiteit to raadsbesluit
- **GIVEN** activiteit "3.2.1 Vervangen straatverlichting"
- **WHEN** user clicks "Link Raadsbesluit" and searches for "verlichting"
- **THEN** finds raadsbesluit "RB-2025-189 Vaststellen programmabegroting 2026"
- **WHEN** user links
- **THEN** raadsbesluit appears in activiteit detail; Decidesk can show linked content

### Requirement: Gantt Progress Indicator [MVP]
In the Gantt timeline view, activiteit bars MUST show progress as a colored fill within the bar (e.g., 65% of bar filled in darker shade).

#### Scenario: Gantt bar fill indicates progress
- **GIVEN** Gantt timeline with activiteit "7.3.1 Aanleg fietsstraat" that is 75% done (from linked projects)
- **WHEN** Gantt renders
- **THEN** bar shows 75% filled (dark overlay) and 25% empty; hover shows "75% complete"

## Non-Functional Requirements

- **Performance:** Progress rollup calculation O(n) where n = number of linked projects; cached and refreshed max every 5 min or on webhook from planix-projects
- **Webhook integration:** Listen to planix `project.updated` events; re-calculate activiteit progress on task change
- **Accessibility:** Project/zaaktype cards readable by screen readers; progress % announced as text (not color alone)
- **Data consistency:** If linked project deleted in planix, reference becomes stale but does not block activiteit

## Acceptance Criteria

- [ ] User can link/unlink activiteit to/from planix project
- [ ] Project card displays in activiteit detail with status, % done, owner
- [ ] Activiteit progress calculated as average of linked project completion %
- [ ] Status pessimism applies (one project vertraagd → activiteit vertraagd)
- [ ] User can link/unlink activiteit to/from procest zaaktype
- [ ] Zaaktype appears in activiteit detail with "Output Zaken" count
- [ ] Output indicator auto-increments on procest zaak closure (via webhook)
- [ ] User can link/unlink activiteit to/from decidesk raadsbesluit
- [ ] Raadsbesluit appears in activiteit detail with metadata
- [ ] Gantt bar fill shows progress % from linked projects
- [ ] Progress recalculates within 5 min of project task change
- [ ] Stale references (deleted projects) do not block activiteit

## Notes

- Project linking is not required for activiteit to exist; activities may be tracked without project execution
- Zaaktype linking assumes procest integration via events; initial MVP may use polling endpoint instead of webhooks
- Future iteration may add dependency constraints (e.g., "this activiteit cannot complete until linked project PRJ-X closes")
