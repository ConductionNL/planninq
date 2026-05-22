# Tasks: Raadsbesluit Deliverable Chain

## Data Model & Schema Implementation

- [ ] Create OpenRegister schema `BesluitDeliverableChain` with all fields (uuid, decidesk_besluit_id, titel, type, status_overall, voortgang_percentage, etc.)
- [ ] Create OpenRegister schema `ChainLink` with relation type, critical-path flag, progress tracking
- [ ] Create OpenRegister schema `ChainMijlpaal` with dates, status enum, reporting cadence
- [ ] Create OpenRegister schema `ChainEventLog` with event types, audit trail fields
- [ ] Create OpenRegister schema `RaadslidVolgVoorkeur` with user follow preferences
- [ ] Add database indices: `decidesk_besluit_id` (unique), `eigenaar_ambtelijk_id`, `uiterste_rapportage_datum`, `volgers_user_ids` (GIN)
- [ ] Create migration script to generate seed data (4-5 chains, 6-8 links, 8-10 milestones, sample event log entries)
- [ ] Add schema validation rules: besluit_id must exist in decidesk with status=vastgesteld, link references must be resolvable, milestone dates >= vastgesteld_op

## Backend Services (Planix)

- [ ] Create `BesluitDeliverableChainService`: lifecycle (create, read, update, delete), permissions checks, cross-app lookups
- [ ] Create `ChainLinkService`: add/edit/delete links, referential integrity validation, cross-app entity resolution
- [ ] Create `ChainRollupService`: voortgang % calculation algorithm, status inference state machine, atomic updates
- [ ] Create `ChainEventService`: event logging (append-only), audit trail queries, event deduplication
- [ ] Create `ChainAiService`: LLM-based link suggestions (calls external LLM API), confidence scoring, citation extraction
- [ ] Create `ChainEscalationService`: nightly milestone sweep logic, status marking (missed), notification dispatch
- [ ] Create `ChainReportService`: Markdown template rendering, PDF generation (docudesk integration), file storage
- [ ] Create `ChainAuthorizationHandler`: permission checks (openbaar, geheim, indieners, RBAC), audit logging of denials
- [ ] Create `ChainCrossAppService`: decidesk/procest event listeners, webhook subscriptions, event propagation

## API Endpoints (Planix REST)

- [ ] `GET /api/chains` — List chains with filtering, pagination, permission enforcement
- [ ] `GET /api/chains/{id}` — Retrieve chain detail (read-only or read-write based on permission)
- [ ] `POST /api/chains` — Create chain (internal only, triggered by decidesk event)
- [ ] `PATCH /api/chains/{id}` — Update chain (eigenaar/griffie only): eigenaar, status, rapportage text
- [ ] `DELETE /api/chains/{id}` — Delete chain (griffie only, niet_gestart state only)
- [ ] `GET /api/chains/{id}/links` — List all links for a chain
- [ ] `POST /api/chains/{id}/links` — Create new link (manual or AI-suggested)
- [ ] `PATCH /api/chains/{id}/links/{link_id}` — Update link (volgorde, is_kritiek_pad, dates, toelichting)
- [ ] `DELETE /api/chains/{id}/links/{link_id}` — Delete link
- [ ] `GET /api/chains/{id}/milestones` — List all milestones for a chain
- [ ] `POST /api/chains/{id}/milestones` — Create new milestone
- [ ] `PATCH /api/chains/{id}/milestones/{milestone_id}` — Update milestone (date, status, responsible, reporting format)
- [ ] `DELETE /api/chains/{id}/milestones/{milestone_id}` — Delete milestone
- [ ] `GET /api/chains/{id}/events` — Retrieve event log for chain (permission filtered)
- [ ] `POST /api/chains/{id}/suggest-links` — Trigger AI link suggestion (returns candidates with confidence scores)
- [ ] `POST /api/chains/{id}/recalculate` — Manually trigger rollup recalculation (for testing)
- [ ] `GET /api/chains/export/lta/excel` — Export milestones to Excel (commissie, quarter filters)
- [ ] `GET /api/chains/export/lta/ical` — Export milestones to iCal feed (user_token param)
- [ ] `GET /api/chains/{id}/volgers` — List raadsleden following a chain (count only, no names)
- [ ] `GET /api/chains/{id}/voortgang-breakdown` — Detailed progress breakdown (per-link %, weighted contribution)

## Event Listeners & Integration (Planix + n8n)

- [ ] Subscribe to decidesk `besluit.vastgesteld` event → trigger `BesluitDeliverableChainService.createChainFromEvent()`
- [ ] Subscribe to decidesk `besluit.amendment` event → update existing chain's `decidesk_besluit_id`
- [ ] Subscribe to decidesk `besluit.ingetrokken` event → set chain status to `afgewezen_door_college`
- [ ] Subscribe to planix internal `project.status_changed` event → trigger rollup recalc
- [ ] Subscribe to planix internal `task.completed` event → trigger rollup recalc
- [ ] Subscribe to procest `zaak.status_changed` event → update link progress, trigger rollup
- [ ] Publish `nl.conduction.planix.chain.voortgang_updated` event when voortgang % changes
- [ ] Publish `nl.conduction.planix.chain.status_changed` event when status_overall changes
- [ ] Publish `nl.conduction.planix.chain.eindrapportage_klaar` event when report generated
- [ ] Create n8n workflow for nightly escalation sweep: query upcoming milestones, send notifications, mark missed
- [ ] Create n8n integration: call decidesk API to attach endrapportage PDF when chain completes

## Frontend Components (Planix UI)

- [ ] Create page `ChainListView.vue` — displays all chains with filters, sorting, pagination, status badges
- [ ] Create page `ChainDetailView.vue` — full chain detail (header, timeline, links, rapportage, milestones, volgers)
- [ ] Create component `ChainProgressCard.vue` — compact card showing status, voortgang%, next milestone, deadline warning
- [ ] Create dialog `AddLinkDialog.vue` — select link type, search entity, set critical-path flag, save
- [ ] Create dialog `AILinkSuggestionsDialog.vue` — display suggestions with confidence scores, citations, accept/reject
- [ ] Create dialog `AddMilestoneDialog.vue` — form for new milestone (title, description, date, responsible, reporting format)
- [ ] Create component `MilestoneTimeline.vue` — vertical or horizontal timeline of milestones with status indicators
- [ ] Create component `ChainLinksList.vue` — table of all links with progress bars, volgorde drag-drop, delete buttons
- [ ] Create component `RapportageEditor.vue` — text editor for `laatste_rapportage_tekst` with save/preview
- [ ] Create dialog `LinkSuggestionsReview.vue` — AI-generated suggestions with user feedback loop
- [ ] Create utility `dateHelpers.ts` — date comparison for milestone escalation logic (today, 7d, 14d thresholds)
- [ ] Add i18n translations: Dutch labels for all UI elements, status enums, notifications (ADR-007)

## Frontend Components (Mydash - Raadslid Portal)

- [ ] Create tile `MyDecisionsTile.vue` — main raadslid portal page component
- [ ] Create section `IngedeindChainsList.vue` — list of chains where user is indiener (from decidesk)
- [ ] Create section `FollowedChainsList.vue` — list of chains user is following
- [ ] Create component `ChainStatusCard.vue` — compact card for each chain (ID, title, status, progress%, deadline, next milestone)
- [ ] Create dialog `FollowChainDialog.vue` — enable follow + configure notification preferences
- [ ] Create dialog `NotificationPreferencesDialog.vue` — per-chain notification settings (milestone, vertraging, afronding, kanaal)
- [ ] Create utility `notificationPreferences.ts` — manage `RaadslidVolgVoorkeur` preferences

## Frontend Components (Mydash - Griffie Dashboard)

- [ ] Create widget `Raadsbesluiten UitvoeringWidget.vue` — main griffie overview dashboard widget
- [ ] Create component `KPITiles.vue` — 3 tiles (Actieve, Vertraagde, Deze Week Deadline) with counts and trends
- [ ] Create component `ChainOverviewTable.vue` — traffic-light table with all chains, sorting, filtering, pagination
- [ ] Create component `StatusBadge.vue` — color-coded status badge with text label
- [ ] Create component `DeadlineWarning.vue` — visual indicator for uiterste_rapportage_datum approaching
- [ ] Create actions in table: View Detail, Notify Volgers, Edit, Escalate
- [ ] Create dialog `EditChainDialog.vue` — quick edit for eigenaar, deadline, tags, toelichting
- [ ] Create dialog `NotifyVolgersDialog.vue` — compose message to raadsleden following a chain
- [ ] Create export button → calls LTA Excel export endpoint
- [ ] Add filter sidebar with: status, portfolio, deadline window, eigenaar dropdowns

## Frontend Integration with Decidesk & Procest

- [ ] Add cross-app chain status badge to decidesk besluit-detail page (shows voortgang%, status, link to chain)
- [ ] Add cross-app chain context banner to procest zaak-detail page (if zaak is linked, shows "Contributes to [chain_id]")
- [ ] Subscribe to mydash `chain.voortgang_updated` event in decidesk UI (real-time badge update)
- [ ] Subscribe to mydash `chain.status_changed` event in procest UI (real-time banner update)

## Tests

### Unit Tests

- [ ] Test `BesluitDeliverableChainService`: creation, update, delete, permission checks
- [ ] Test `ChainRollupService`: voortgang % calculation (all combinations of critical/non-critical, 0 links, etc.)
- [ ] Test `ChainRollupService`: status transitions (niet_gestart → in_uitvoering → afgerond, vertraagd logic)
- [ ] Test `ChainEventService`: event logging, append-only semantics, deduplication
- [ ] Test `ChainAuthorizationHandler`: public/restricted/geheim visibility, RBAC integration
- [ ] Test `ChainLinkService`: referential integrity validation, cross-app entity lookup
- [ ] Test `ChainEscalationService`: milestone date logic (14d, 7d, 0d thresholds), missed marking
- [ ] Test `ChainReportService`: Markdown template rendering, PDF generation call
- [ ] Test `ChainAiService`: LLM call, confidence score ranking, deduplication

### Integration Tests

- [ ] Test end-to-end: decidesk event → chain creation → link addition → rollup → status transition
- [ ] Test: planix internal event (task completion) → rollup recalc → voortgang % update
- [ ] Test: procest zaak event → chain link progress update → rollup
- [ ] Test: raadslid follow → RaadslidVolgVoorkeur created → notification preferences
- [ ] Test: nightly escalation → milestone marked missed → notification sent
- [ ] Test: chain completion → report generated → decidesk PDF attached
- [ ] Test: permission filtering on list (public vs. restricted) → correct visibility
- [ ] Test: Excel LTA export with filters (commissie, quarter) → correct milestones returned
- [ ] Test: iCal feed generation → valid RFC 5545 format, subscribable in Outlook

### End-to-End Tests (Browser)

- [ ] Test raadslid portal: login → view own motions → follow a chain → see notifications
- [ ] Test griffie widget: open dashboard → filter vertraagde → click notify volgers → send message → verify receipt
- [ ] Test chain detail: expand timeline → see all milestones → click on milestone → show details
- [ ] Test link management: click add link → AI suggests → user accepts → link appears in list
- [ ] Test permission: try to access private chain as unauthorized user → 403 shown
- [ ] Test export: griffie opens widget → exports LTA Excel → file downloaded and readable

## Documentation

- [ ] Document: API endpoint reference (request/response examples for all /api/chains/* endpoints)
- [ ] Document: Event schema (CloudEvents format for all published events)
- [ ] Document: LLM link suggestion tuning (which models perform best, how to retune prompts)
- [ ] Document: Escalation configuration (nightly sweep schedule, e-mail templates)
- [ ] Document: Griffie onboarding guide (how to set up a new chain, manage milestones, send notifications)
- [ ] Document: Raadslid FAQ (how to follow decisions, manage notifications, understand progress %)
- [ ] Update app README with decision-chain feature overview

## Deduplication & Reuse Analysis

- [ ] Verify: No custom form dialogs needed (use CnFormDialog auto-generation from schema)
- [ ] Verify: No custom list views needed (use CnDataTable + useListView composable)
- [ ] Verify: No custom permission system (use OpenRegister RBAC + AuthorizationService)
- [ ] Verify: No custom search (use IndexService + SearchTrailService for analytics)
- [ ] Verify: No custom audit trail (use AuditTrailService; ChainEventLog is supplemental)
- [ ] Document: Deduplication check results (no overlaps found or overlap justifications)

## Seed Data Generation

- [ ] Generate seed data script (SQL inserts or JSON import):
  - 4-5 example BesluitDeliverableChain records (various types, statuses, progress %)
  - 6-8 ChainLink examples (planix_project, planix_task, procest_zaak types)
  - 8-10 ChainMijlpaal examples (various status: gepland, bereikt, gemist)
  - 20-30 ChainEventLog entries (various event types, propagation trails)
  - 5-6 RaadslidVolgVoorkeur examples (raadsleden following different chains)
- [ ] Load seed data on app install via `ConfigurationService.importFromApp()` (idempotent)
- [ ] Verify seed data loads without errors
- [ ] Manual QA: Browse chains, links, milestones in UI; verify relationships

## Configuration & Settings

- [ ] Add app config section for escalation schedule (nightly sweep time, timezone)
- [ ] Add app config section for LLM settings (API endpoint, model, temperature, max_tokens)
- [ ] Add app config section for report templates (per besluit-type: raadsbesluit, motie, amendement, etc.)
- [ ] Add app config section for LTA export (default columns, formatting options)
- [ ] Add app settings page: griffie can configure templates, escalation, notification defaults

## Deployment & Rollout

- [ ] Create feature flag: `feature.raadsbesluit_deliverable_chain` (initially off)
- [ ] Enable feature flag in staging environment for QA
- [ ] Create rollout plan: phased enable per gemeente (start with 1 pilot, expand to all)
- [ ] Create rollback procedure: disable feature flag reverts all chain UI to hidden state (data preserved)
- [ ] Prepare migration script: backfill chains for existing decisions (optional, manual trigger)
- [ ] Document: operations team runbook for escalation troubleshooting, event listener health checks
- [ ] Schedule: demo to griffiers, raadsleden, college; gather feedback before full rollout

## Post-Launch & Iteration

- [ ] Monitor: chain creation latency, rollup performance, event propagation delays
- [ ] Monitor: raadslid follow adoption rate, notification engagement
- [ ] Monitor: griffie dashboard usage, export frequency
- [ ] Gather feedback: survey griffiers on usability of widget + raadsleden on portal UX
- [ ] Plan Phase 2: configurable rollup thresholds, automatic milestone reaching on project completion, advanced reporting dashboards
