# Capacity Planning Specification

**Status**: planned

**Standards**: ISO 21500/21502 (project management), PRINCE2 (resource planning), OAuth 2.0 + SCIM (HR integration), iCalendar RFC 5545 (absence export), ISO 27001 (data access control)

**Feature tier**: V1

## Purpose

Capacity Planning adds resource visibility to Planix. Teams answer in real time: who has capacity for new work, who is overallocated, do we have someone with the right skills, and how accurate was our forecast? Resource managers allocate work informed by actual capacity, skill, and historical planning accuracy. Early alerts on over-allocation enable scope negotiation weeks before deadline instead of crisis management.

## Requirements

### Requirement: Resource Profile Lifecycle [V1]

The system MUST allow resource managers to create, read, update, and archive resource profiles (persons with capacity configuration).

#### Scenario: Create a resource profile

- **GIVEN** a resource manager opens the Capacity → Resource List page
- **WHEN** they click "Add Resource" and fill: name (derived from Nextcloud user), FTE (0.0–1.0), hours/week, cost/hour, location, start date, end date
- **THEN** the system MUST store a ResourceProfiel object in OpenRegister
- **AND** the system MUST compute CapaciteitWeek for the next 12 weeks
- **AND** the resource MUST immediately appear in the heatmap and list

#### Scenario: Import resource profiles from HR system

- **GIVEN** openconnector is configured with an HR adapter (e.g., AFAS, Visma)
- **WHEN** the resource manager triggers "Sync from HR"
- **THEN** planix MUST fetch FTE and person metadata from the HR system via SCIM
- **AND** planix MUST upsert ResourceProfiel objects for each active employee
- **AND** planix MUST update CapaciteitWeek for all affected persons
- **AND** an audit log entry MUST record the import timestamp and count

#### Scenario: Update resource profile

- **GIVEN** a resource profile exists with fte=1.0
- **WHEN** a resource manager updates it to fte=0.8
- **THEN** the system MUST recompute netto_beschikbaar_uren for all weeks (today + 12 weeks)
- **AND** any existing AlertSignales for over-allocation MUST be recalculated
- **AND** the heatmap MUST immediately reflect the new utilization percentages

#### Scenario: Archive a resource profile

- **GIVEN** a resource with active forecast allocations
- **WHEN** the resource manager marks them as beschikbaar_tot=today (end date)
- **THEN** the system MUST hide them from the active resource list after today
- **AND** their historical forecast and actual hours MUST remain for retrospective analysis
- **AND** the heatmap MUST exclude them from the 12-week horizon after the end date

### Requirement: Skill Inventory and Proficiency [V1]

The system MUST track person-skill relationships with proficiency levels and verification status.

#### Scenario: Define global skill library

- **GIVEN** an admin opens Capacity → Settings
- **WHEN** they create a skill: code=vue-js, name=Vue.js, category=framework
- **THEN** the system MUST store a Skill object
- **AND** the skill MUST be available in all skill-picker dialogs (resource profile, task requirement, forecast allocation)

#### Scenario: Assign a skill to a person

- **GIVEN** a person (alice) and a skill (vue-js) exist
- **WHEN** the resource manager opens alice's resource detail and clicks "Add Skill"
- **THEN** the system MUST create a PersoonSkill with nivel=junior (or medior/senior/expert)
- **AND** the system MUST record zelfverklaard=true or geverifieerd=true (based on evidence upload)
- **AND** the skill MUST immediately appear in candidate picker when filtering by vue-js

#### Scenario: Update skill proficiency after training

- **GIVEN** a person has PersoonSkill(vue-js, junior, geverifieerd=false)
- **WHEN** the person completes a Vue.js course and uploads a certificate
- **THEN** a resource manager MUST be able to update the PersoonSkill to nivel=medior, geverifieerd=true
- **AND** the latest_evidence_ref MUST link to the certificate in docudesk
- **AND** the candidate picker MUST immediately reflect the new proficiency

#### Scenario: Skill-mismatch alert

- **GIVEN** a task requires skill_vereiste={vue-js: senior, accessibility: junior+}
- **WHEN** a forecast allocation is created for a person with only {vue-js: medior}
- **THEN** the system MUST generate an AlertSignaal of type=skill-mismatch
- **AND** the resource manager MUST see the alert in the Capacity → Alerts section
- **AND** the candidate picker MUST highlight the mismatch in red

### Requirement: Weekly Capacity Calculation [V1]

The system MUST derive available capacity per person per week, accounting for absence and overhead.

#### Scenario: Capacity calculation with absence

- **GIVEN** a ResourceProfiel: fte=0.8 (nominaal=32 u/week)
- **AND** an Afwezigheid in that week: type=vakantie, 5 days (40 hours)
- **AND** overhead policy: 4 u/week (meetings, email)
- **WHEN** CapaciteitWeek is calculated
- **THEN** netto_beschikbaar_uren = 32 − 40 − 4 = −12 (overallocated before forecast)
- **AND** the CapaciteitWeek object MUST be immutable (recalc only on profile/absence change)

#### Scenario: Overhead policy application

- **GIVEN** a resource manager sets overhead_rate=10% in admin settings
- **WHEN** CapaciteitWeek is computed for a 40-hour-per-week person
- **THEN** afgetrokken_uren_overhead = 40 × 0.10 = 4 hours
- **AND** netto = 40 − absence − 4

#### Scenario: Aging of capacity (week boundary)

- **GIVEN** today is 2026-05-22 (week 21)
- **WHEN** the nightly scheduler runs
- **THEN** it MUST age CapaciteitWeek: compute week 22 (next) through week 33 (12 weeks out)
- **AND** weeks in the past (week ≤ 20) MUST be marked read-only for audit

### Requirement: Forecast Allocations and Over-Booking Alerts [V1]

The system MUST track planned hours per person per week and alert when allocation exceeds capacity.

#### Scenario: Create a forecast allocation

- **GIVEN** a task exists and a person (alice) is selected
- **WHEN** the planner opens the task's "Assign Forecast" dialog and selects alice for week 2026-W21
- **THEN** the system MUST create a ForecastAllocatie: persoon=alice, week=2026-W21, gepland_uren=32, skill_vereiste={vue-js: senior}
- **AND** status=concept until the planner clicks "Confirm"
- **AND** when status→bevestigd, the alert-generation job MUST check for over-booking

#### Scenario: Over-booking alert (high severity)

- **GIVEN** CapaciteitWeek(alice, 2026-W22, netto=36 uren)
- **AND** sum(ForecastAllocatie) for alice in week 22 = 48 hours
- **WHEN** the second ForecastAllocatie is confirmed to bring total to 48 hours
- **THEN** the system MUST create an AlertSignaal: type=overbezetting, ernst=hoog (33% overage > 20%)
- **AND** the alert MUST appear immediately in the Capacity → Alerts section
- **AND** the resource manager's dashboard MUST show a red badge on the Capacity section

#### Scenario: Under-booking alert and candidate recommendations

- **GIVEN** a task needs skill_vereiste={php: medior+}
- **AND** bob has {php: medior} and capacity in week 22 > 20% of his weekly hours
- **WHEN** the task is unassigned and the candidate picker is opened
- **THEN** the system MUST rank bob highly based on (1) skill match, (2) available capacity, (3) cost
- **AND** an AlertSignaal of type=onderbezetting SHOULD be created for bob: "You have 8+ hours available in W22 and 2 open tasks needing PHP"

#### Scenario: Adjust forecast allocation and re-alert

- **GIVEN** an AlertSignaal of type=overbezetting exists for alice
- **WHEN** the planner reduces a ForecastAllocatie from 32 to 20 hours
- **THEN** the system MUST recalculate sum(gepland_uren) and recompute netto_beschikbaar_uren
- **AND** if the new sum ≤ netto × 1.1, the AlertSignaal.status MUST change to opgelost
- **AND** if still over-booked but within tolerance, ernst MUST downgrade to medium

### Requirement: Absence Management and Conflict Detection [V1]

The system MUST track vacation, sick leave, training, and other absence types, with automatic conflict detection when absence overlaps with forecasted work.

#### Scenario: Plan vacation

- **GIVEN** alice opens her resource detail → Absence tab
- **WHEN** she clicks "Add Absence" and enters: type=vakantie, van=2026-07-13, tot=2026-07-24 (2 weeks)
- **THEN** the system MUST create Afwezigheid objects with status=gepland
- **AND** CapaciteitWeek.afgetrokken_uren_afwezigheid MUST be updated for weeks W29–W30 (reduce from 40 to 0)
- **AND** if ForecastAllocaties exist in those weeks, an AlertSignaal of type=afwezigheid-conflict MUST be generated

#### Scenario: HR system imports absence (sick leave)

- **GIVEN** openconnector is syncing with the HR system
- **WHEN** the HR system reports alice took sick leave 2026-05-20 to 2026-05-22 (3 days = 24 hours)
- **THEN** the system MUST create Afwezigheid: type=ziekte, status=geboekt (not modifiable by user)
- **AND** CapaciteitWeek for week 21 MUST be recalculated
- **AND** any conflict with forecasted hours MUST generate an AlertSignaal with escalation priority

#### Scenario: Absence-conflict resolution options

- **GIVEN** an AlertSignaal of type=afwezigheid-conflict for alice's vacation (W29–W30) with 40 forecasted hours
- **WHEN** the resource manager clicks "Resolve Conflict"
- **THEN** the system MUST show options: (1) Shift tasks to other weeks, (2) Reassign to another person, (3) Reduce scope
- **AND** selecting "Reassign" MUST open the candidate picker (same ranking as REQ-003)
- **AND** the status MUST update to erkend when a resolution is acknowledged

### Requirement: Multi-Week Capacity Heatmap [V1]

The system MUST display a 12-week capacity horizon with color-coded utilization per person.

#### Scenario: View the heatmap

- **GIVEN** the user opens Capacity → Heatmap
- **WHEN** the page loads
- **THEN** the heatmap MUST show:
  - Y-axis: list of active resources (sortable by name, FTE, location)
  - X-axis: ISO weeks (today + 12 weeks, e.g., W21–W33)
  - Cells: utilization % = sum(gepland_uren) / netto_beschikbaar_uren × 100
  - Colors: 0–50% green, 50–90% yellow, 90–110% orange, >110% red
- **AND** hovering a cell MUST show tooltip: "alice, W21: 32/36 (89%)"
- **AND** clicking a cell MUST expand to show the breakdown: list of ForecastAllocaties for that week

#### Scenario: Filter and sort heatmap

- **GIVEN** the heatmap is displayed
- **WHEN** the resource manager applies a filter: Locatie=Amsterdam
- **THEN** the Y-axis MUST show only resources with locatie=Amsterdam
- **AND** they can sort by: Name (A–Z), FTE (high–low), Avg Utilization (high–low), Cost/hour (high–low)

#### Scenario: Identify over-booked people

- **GIVEN** the heatmap shows week W22 with alice's cell red (>110%)
- **WHEN** the resource manager clicks the red cell
- **THEN** the detail pane MUST show:
  - alice's allocations for W22: Task A (32h), Task B (16h) = 48 hours
  - Capacity for W22: 36 hours (bruto 40 − 4 overhead)
  - Over-allocation: 12 hours (33%)
- **AND** a "Resolve" button MUST be visible (links to AlertSignaal)

### Requirement: Skill-Matching Candidate Picker [V1]

The system MUST rank candidate resources by skill match, available capacity, and cost when assigning tasks.

#### Scenario: Assign task with skill requirements

- **GIVEN** a task with skill_vereiste={vue-js: medior+, accessibility: junior+}
- **WHEN** the planner opens "Assign Forecast" for week W21
- **THEN** the system MUST fetch all PersoonSkill records matching the requirements
- **AND** MUST rank candidates by: (1) skill-match-score (higher = better match), (2) netto_beschikbaar_uren in W21 (more = higher rank), (3) kosten_per_uur (lower = higher rank)
- **AND** MUST display in a table:
  - Candidate name, skills (with proficiency badges), W21 available hours, cost/hour, rank score
  - Visual indicators: ✅ all skills ≥ required level, ⚠️ some skills below level, ❌ missing skill
  - Green row if available capacity ≥ task est. hours, yellow if tight, red if over-allocated

#### Scenario: Select candidate and confirm allocation

- **GIVEN** alice appears in the ranked candidate list with ✅ (all skills met) and 32 available hours (enough for 32-hour task)
- **WHEN** the planner clicks alice and then "Assign"
- **THEN** the system MUST create a ForecastAllocatie for alice
- **AND** status MUST be concept; planner must confirm to trigger alert generation
- **AND** the picker MUST close and the task MUST show alice as assigned (with week and hours)

#### Scenario: Unqualified candidate greyed out with reason

- **GIVEN** bob has {vue-js: junior} but task requires medior+
- **WHEN** the candidate picker loads
- **THEN** bob MUST still appear in the list (not hidden) but greyed out
- **AND** a tooltip MUST explain: "Vue.js: junior (requires medior+)"
- **AND** the planner can still select bob if they choose (with a warning)

### Requirement: Forecast-vs-Actual Analysis [V1]

The system MUST track actual hours booked and enable retrospective comparison against forecast for accuracy analysis.

#### Scenario: Book actual hours

- **GIVEN** a task forecast-allocated to alice for 32 hours in week W21
- **WHEN** alice opens Capacity → Forecast Review and fills the week W21 timesheet:
  - Task A: Mon–Tue (16h), Task B: Wed–Fri (16h) = 32h
- **THEN** the system MUST create WerkelijkUur objects (one per day per task)
- **AND** immutable audit log MUST record: date, uren, persoon, project, geboekt_op timestamp

#### Scenario: Forecast-vs-actual retrospective report

- **GIVEN** week W21 has closed (today is after W21)
- **WHEN** a manager opens Capacity → Forecast Review and selects date range: W15–W21
- **THEN** the system MUST display:
  - Person | Forecast (h) | Actual (h) | Ratio (actual/forecast) | Variance (%)
  - alice: 160, 158, 0.99, −1%
  - bob: 128, 156, 1.22, +22%
- **AND** optionally group by skill-category (e.g., {vue-js: 1.05 variance, php: −0.8 variance})
- **AND** an "Export" button MUST export the report as CSV for finance

#### Scenario: Identify systematic under-/over-estimation

- **GIVEN** retrospective data for 3 months shows:
  - PHP tasks: avg variance +15% (consistently underestimated)
  - Vue.js tasks: avg variance −5% (overestimated)
- **WHEN** a resource manager views the Forecast Review grouped by skill
- **THEN** the system MUST highlight (bold/red) categories with |variance| > 10%
- **AND** a "Calibrate Forecast Models" button MUST be visible (future feature to adjust default estimates per skill)

### Requirement: What-If Scenarios [V1]

The system MUST allow planners to explore hypothetical allocations without mutating live data.

#### Scenario: Enter scenario mode

- **GIVEN** the user is viewing the Capacity → Heatmap
- **WHEN** they toggle "Scenario Mode"
- **THEN** the system MUST fork the current CapaciteitWeek and ForecastAllocatie state locally (in browser storage or temp table)
- **AND** a banner MUST appear: "Scenario Mode: Changes are not saved. Click 'Apply Scenario' to commit."
- **AND** all mutations (new forecasts, absence changes) MUST affect only the local scenario, not live data

#### Scenario: Add a new project in scenario

- **GIVEN** scenario mode is active and a new project "Client ABC Contract" arrives with 80 hours to allocate across 2 weeks
- **WHEN** the planner assigns: alice W21 (32h, available=36h), bob W21 (20h, available=32h), carol W22 (28h, available=40h)
- **THEN** the heatmap MUST immediately update to show the new utilization percentages
- **AND** an AlertSignaal would normally be generated (e.g., alice at 89%, bob at 62%, carol at 70%), but MUST be marked as "scenario" not "live"

#### Scenario: Apply scenario to live planning

- **GIVEN** scenario mode shows the new project allocations (alice 89%, bob 62%, carol 70%) and the planner is satisfied
- **WHEN** they click "Apply Scenario"
- **THEN** the system MUST commit the ForecastAllocaties to live OpenRegister
- **AND** the alert-generation job MUST run (will not create alerts in this case — all within tolerance)
- **AND** the heatmap MUST refresh to live data
- **AND** the scenario MUST be discarded (or optionally versioned for audit)

#### Scenario: Discard scenario

- **GIVEN** scenario mode shows allocations that don't work well
- **WHEN** the planner clicks "Discard Scenario"
- **THEN** all temporary changes MUST be lost
- **AND** the heatmap MUST immediately revert to live data

### Requirement: Privacy and Access Control [V1]

The system MUST enforce role-based access to sensitive resource data.

#### Scenario: Resource manager sees full data

- **GIVEN** a user has role resource-manager
- **WHEN** they open Capacity → Resource List
- **THEN** they MUST see columns: Name, Locatie, FTE, Nominaal u/wk, Netto u/wk, Bezetting %, Kosten/uur
- **AND** they can click any resource and edit all fields
- **AND** they can view all Alerts and Absence records for all persons

#### Scenario: Team lead sees team availability status only

- **GIVEN** a user has role team-lead (not resource-manager)
- **WHEN** they open Capacity → Heatmap
- **THEN** they MUST see the heatmap for their team members only (filtered by team assignment, if available)
- **AND** cells MUST show only: "Beschikbaar" (>20% free), "Beperkt" (5–20% free), "Vol" (≤5% free)
- **AND** the actual % and kosten_per_uur MUST NOT be visible
- **AND** they can see team members' Absence and Forecast allocations (overview only, no edit)

#### Scenario: Developer sees own capacity and skills

- **GIVEN** a user is a developer (not manager)
- **WHEN** they open Capacity → My Profile
- **THEN** they MUST see their own:
  - FTE, hours/week, location, skills (with nivaux)
  - Absence (can add/edit their own planned absence, but not sick leave or HR-imported absences)
  - Forecast (read-only view of what's assigned to them in the next 12 weeks)
  - Actuals (timesheet view for the current week and past weeks; can edit their own entries)
- **AND** they MUST NOT see: kosten_per_uur, other persons' data, team alerts

#### Scenario: Admin configures privacy settings

- **GIVEN** an admin opens Capacity → Settings → Privacy
- **WHEN** they view the role definitions for planix
- **THEN** they MUST see which roles can see which fields:
  - `kosten_per_uur` → only resource-manager, finance-admin, self
  - `netto_beschikbaar_uren` → only resource-manager; others see text "Beschikbaar/Beperkt/Vol"
  - Absence details → only resource-manager, self, their manager
- **AND** changes MUST take effect immediately via OpenRegister's PropertyRbacHandler

### Requirement: Alert Lifecycle [V1]

The system MUST generate, track, and resolve alerts as allocation conditions change.

#### Scenario: Generate and display alerts

- **GIVEN** ForecastAllocaties change or the nightly alert-generation job runs
- **WHEN** a person becomes over-allocated (gepland > netto × 1.1)
- **THEN** an AlertSignaal MUST be created (if not already present) or updated
- **AND** the alert MUST appear in:
  - Capacity → Alerts list (sortable by type, severity, person)
  - Resource detail → Alerts tab
  - Dashboard sidebar (red badge if any "hoog" severity alerts)

#### Scenario: Acknowledge and resolve alert

- **GIVEN** an AlertSignaal of type=overbezetting exists with status=open
- **WHEN** a resource manager clicks "Acknowledge" and adds a note: "Rescheduling Task B to W23"
- **THEN** the alert.status MUST change to erkend
- **AND** if the condition is later resolved (e.g., gepland drops below threshold), the alert.status MUST auto-update to opgelost
- **AND** opgelost alerts MUST remain in the table but visually marked as resolved (greyed out or archived)

## Acceptance Criteria (all requirements)

- [ ] All 7 schemas (ResourceProfiel, Skill, PersoonSkill, Afwezigheid, CapaciteitWeek, ForecastAllocatie, WerkelijkUur, AlertSignaal) are defined in OpenRegister and validate correctly
- [ ] Capacity calculation formula (bruto − absence − overhead = netto) is correctly implemented and tested with multiple scenarios
- [ ] Alert-generation job runs event-driven (on mutation) and nightly; correctly identifies over/under/conflict/mismatch cases
- [ ] Candidate picker ranks by skill match > available capacity > cost and displays correctly in UI
- [ ] Heatmap renders 12 weeks × N persons with correct color coding and interactive drill-down
- [ ] Scenario mode forks state locally and can apply/discard without affecting live data
- [ ] Privacy controls enforce field-level access per role (PropertyRbacHandler integration verified)
- [ ] Forecast-vs-actual report calculates accuracy ratios and identifies systematic variance
- [ ] Absence import from HR system (openconnector) creates/updates Afwezigheid records idempotently
- [ ] All UI pages (list, detail, heatmap, forecast review, alerts) use CnDataTable, CnDetailPage, CnObjectSidebar as specified in design.md
- [ ] Manual tests pass: create resource, assign task, generate alert, resolve alert, view heatmap, export report
