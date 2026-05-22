# Proposal: Raadsbesluit Deliverable Chain

## Summary

Implement a cross-app deliverable chain that connects raadsbesluiten (council decisions) from decidesk to their operational execution via planix projects, procest cases, and live status on mydash. This creates an automated, end-to-end trace from decision to measurable outcome, enabling both raad (council) and college (executive board) to track decision implementation with transparency and accountability.

**Formal basis**: Gemeentewet art. 169 (actieve informatieplicht), art. 155a/b (right of inquiry); mirrors the canonical GEMMA "Raadsbesluit afhandelen" process.

## Motivation

In Dutch municipalities, ~200–400 raadsbesluiten per year assign tasks to the college, yet raadsleden have historically received little feedback on execution status until periodic reports (months/years later) or only when writing formal inquiries. This information gap erodes trust between raad and college. The deliverable chain solves this by:

1. **Automatic linkage**: Each approved decidesk besluit triggers a chain record with zero manual overhead.
2. **Live rollup**: Status of planix projects, procest cases, and milestones auto-aggregates upward to show overall progress %, next milestone, and timeline risk.
3. **Multi-stakeholder visibility**: Raadsleden see their own motions/decisions with live status; griffie and college track portfolios; mydash dashboard alerts on overdue milestones.
4. **Audit trail**: Event log captures every status change, triggering notifications and enabling reconstruction of decision execution path.

The system is the "glue" connecting five disparate apps (decidesk → planix → procest → mydash → docudesk) into one coherent citizen-facing and internal-facing narrative.

## Affected Projects

- [x] Project: `planix` — Host app; contains the `besluit_deliverable_chain` and related schemas
- [x] Cross-app: `decidesk` — Publishes `besluit.vastgesteld`, `besluit.amendment`, `besluit.ingetrokken` events
- [x] Cross-app: `procest` — Publishes `zaak.status_changed` events
- [x] Cross-app: `mydash` — Displays chain status widget and raadslid portal
- [x] Cross-app: `docudesk` — Renders final report PDF
- [x] Automation: `n8n` — Nightly milestone escalation sweep, e-mail to volgers

## Scope

### In Scope

**Data Model** (4 new schemas in planix):
- `besluit_deliverable_chain` — root entity linking decidesk besluit to execution chain
- `chain_link` — individual connections to planix projects, procest cases, external docs
- `chain_mijlpaal` — milestones with planned/actual dates and reporting cadence
- `chain_event_log` — audit trail of all status changes and propagation

**Mechanisms**:
- Automatic chain creation on decidesk `status = vastgesteld`
- AI-assisted link suggestions from besluit text (LLM analysis of text → candidate planix/procest entities)
- Manual link creation via UI
- Template-based link patterns per besluit-type
- Auto-event propagation from planix/procest back to chain (task completion, zaak closed, etc.)

**Rollup & Reporting**:
- Weighted voortgang % aggregation (critical path only)
- Status auto-transition (not_gestart → in_uitvoering → afgerond | vertraagd | on_hold)
- Milestone escalation: nightly sweep for due soon (14d, 7d, 0d); mark missed, notify eigenaar + griffie
- Eindrapportage PDF generation on completion

**Portals & Dashboards**:
- Mydash griffie widget (traffic-light overview, 5 KPI tiles, filterable table)
- Raadslid follow portal ("mijn moties", "mijn besluiten", live status, notification prefs)
- LTA export (Excel, iCal) for griffie calendar sync

**Permissions**:
- Chains for `openbaar = false` besluiten are filtered from public portal; visible only to eigenaar, griffie, authorized users
- Audit log respects same visibility

### Out of Scope

- Juridical-formal handling of raadsbesluiten (remains in decidesk)
- Actual project/task execution (remains in planix)
- Zaak handling (remains in procest)
- Financial realization tracking (remains in financeq — referenceable only)
- Configurable rollup thresholds (2-day warning for milestones, 50-link soft cap, hardcoded for MVP)

## Approach

**Delivery cadence**: Four coordinated artifacts (proposal → design → specs → tasks) map the full data model, integration points, and UI workflows. Implementation is planix-hosted with cross-app event subscriptions (CloudEvents) and n8n-driven automations. Frontend uses existing mydash widgets and Nextcloud-vue components.

**Key integrations**:
- **Decidesk listener**: On `besluit.vastgesteld` event, planix creates chain record. On `besluit.amendment`, updates `decidesk_besluit_id` and preserves chain.
- **Planix events**: On `project.status_changed`, `task.completed`, planix emits internal events that trigger chain rollup recalc.
- **Procest listener**: On `zaak.status_changed`, external event updates chain links and triggers rollup.
- **N8N automation**: Scheduled workflow (nightly) queries all upcoming milestones, sends escalation emails, marks as missed if past.

## New Dependencies

- **CloudEvents**: Already used for cross-app events; no new dependency.
- **Docudesk**: Existing integration for PDF generation; no new dependency.
- **N8N**: Existing automation platform; no new integration type needed.

## Impact

### New Schemas (Planix)
- `BesluitDeliverableChain` (root, 1 per decidesk-besluit)
- `ChainLink` (0..n per chain)
- `ChainMijlpaal` (0..n per chain)
- `ChainEventLog` (audit trail, 0..n per chain)
- `RaadslidVolgVoorkeur` (per-user follow preferences)

### UI Changes
- **Planix**: Chain detail page, link management dialogs, rollup visualization (Gantt with besluit context)
- **Mydash**: Griffie widget (new), raadslid portal (new)
- **Decidesk**: Chain status banner on besluit detail (read-only cross-app ref)
- **Procest**: Chain context banner when zaak is linked (read-only cross-app ref)

### API Changes
- **Planix REST**: CRUD for chain, link, milestone, event log; GET voortgang_percentage; GET live-status-rollup for mydash
- **Cross-app events**: Planix publishes `chain.voortgang_updated`, `chain.status_changed` (consumed by mydash, decidesk detail)

### Backend Services (Planix)
- `BesluitDeliverableChainService` — lifecycle, create/update/delete, permissions
- `ChainLinkService` — link management, referential integrity, cross-app lookup
- `ChainRollupService` — voortgang %, status inference, milestone tracking
- `ChainEventService` — event logging, audit trail
- `ChainAiService` — LLM-based link suggestions (calls external LLM API)
- `ChainEscalationService` — nightly milestone sweep (triggered by scheduler or n8n)
- `ChainReportService` — Markdown/PDF report generation

## Cross-Project Dependencies

- **Decidesk**: Must emit `besluit.vastgesteld`, `besluit.amendment`, `besluit.ingetrokken` events (CloudEvents); must consume chain-status for decision detail
- **Procest**: Must emit `zaak.status_changed` events (CloudEvents)
- **Mydash**: Must implement griffie-chain-overview widget and raadslid-portaal tile
- **Docudesk**: Must render chain-data + template → PDF for eindrapportage

## Risks

### Risk 1: Event ordering / eventual consistency
**Severity**: Medium — When a task completes in planix and the event propagates to chain, the rollup must recalc. If events arrive out of order (rare), voortgang % could be stale.  
**Mitigation**: Event log includes `timestamp` + `version`; rollup service idempotent (recalcs from current link states, not from events). Dashboard shows "last updated X minutes ago".

### Risk 2: Circular cross-app dependencies
**Severity**: Low — Planix listens to decidesk/procest events; if decidesk/procest listen to chain events, could create cycles.  
**Mitigation**: Architected as hub-and-spoke: planix is hub; decidesk/procest are spokes, consume chain-status but do not feed back (no circular dependency).

### Risk 3: LLM link suggestion hallucination
**Severity**: Low — LLM may suggest non-existent planix projects or procest case types.  
**Mitigation**: Link suggestion always shows confidence score + cite from besluit text + "needs confirmation"; user must click accept; server-side referential integrity check blocks invalid links.

### Risk 4: Permissions model complexity
**Severity**: Low — Besloten besluiten must be filtered. Schema requires ownership/griffie/authorized-user checks.  
**Mitigation**: Standard permission handler leverages OpenRegister `_rbac` and `_object_acl` (Planix auth already supports per-object ACLs). Audit trail also respects same rules.

### Risk 5: Scale: 50+ links per chain
**Severity**: Low — UI/UX may degrade if user tries to manage many links. Spec includes soft cap warning.  
**Mitigation**: Warn at 40 links, block new links at 50. Recommend splitting chain into 2-3 chains per besluit-type (rare in practice).

## Rollback Strategy

Rollback is multi-phased:
1. **Before go-live**: Delete all planix schemas; chains, links, milestones exist only in test/staging.
2. **After go-live** (chains in production): Keep all data; hide all widgets/portals from UI; decidesk/procest still emit events but chain doesn't listen. Restore by flipping feature flag or re-enabling listeners.

No data loss in either case — all decisions/executions remain in their original apps (decidesk, planix, procest).
