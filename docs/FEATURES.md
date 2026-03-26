# Planix — Feature Analysis & Product Strategy

## Executive Summary

Nextcloud Deck is the only native Nextcloud kanban app — and it is fundamentally limited: no backlog management, no time tracking, no GitHub integration, no WIP limits, and severe performance issues at scale. Meanwhile, leading tools like Jira are expensive and complex, while Plane and Linear are SaaS-first and lack Nextcloud integration. Planix fills the gap as a **developer-first kanban app built natively on Nextcloud** — delivering a focused feature set between Deck's simplicity and Jira's complexity, with time tracking and backlog management that most kanban tools miss.

**Key insight**: Dev and IT teams on Nextcloud are stuck using either the underpowered Deck or an external SaaS tool. Planix gives them flow-based task management, time tracking, and backlog management — all in the sovereign, integrated environment they already use for files, chat, and calendar.

## 1. Competitive Landscape

### Nextcloud App Store

| Name | Status | Key Features | Gaps |
|------|--------|-------------|------|
| **Nextcloud Deck** | Bundled, active (v1.17, Feb 2026) | Kanban boards, cards, labels, file attachment, mobile apps, Circles sharing | No backlog, no time tracking, no GitHub sync, no WIP limits, 6500+ DB queries per board, no multi-view |
| **Nextcloud Tasks** | Bundled, active | CalDAV/VTODO task sync, due dates, priorities, sub-tasks | No project grouping, no kanban board, no time tracking, no team collaboration |
| **Nextcloud Deck Extended** | Community, low activity | Minor Deck extensions | Unmaintained, Deck fork approach |

**Finding**: No mature, developer-focused kanban+time-tracking app exists in the Nextcloud ecosystem. Deck is the only real option and leaves major feature gaps for dev teams.

### Self-Hosted Open Source

| Name | GitHub ★ | Positioning | Key Features | Weaknesses |
|------|----------|------------|-------------|------------|
| **Plane** | 40k+ | Linear alternative, GitHub-native | Kanban, list, calendar views; cycles (sprints); GitHub/GitLab sync; epics (archivable); project subscribers; workspace-level kanban/calendar; Plane AI (web search, chart generation); Slack routing; Jira import | No time tracking, no Gantt, SaaS-first |
| **Taiga** | ~10k | Agile teams, Scrum+Kanban | Scrum boards, backlog, burndown charts, epics, user stories, wiki, swimlanes, WIP limits | No GitHub PR integration, complex UI, no time tracking |
| **Vikunja** | 5k+ (1.0 stable Jan 2026) | Self-hosted flexible | Kanban, list, Gantt (overhauled v2.2), table views; recurring tasks; task duplication; email notifications | No sprint/cycle, no GitHub integration, no time tracking |
| **WeKan** | 14.6k | Trello alternative | Kanban boards, automation rules, swimlanes, 70+ languages, Trello import | Kanban-only, no agile features, no time tracking, Meteor stack |
| **Kanboard** | 9.5k | Minimalist kanban | WIP limits, query language, LDAP, GitHub webhooks | Kanban-only, minimal by design, no time tracking, no backlog |
| **Leantime** | — | Goal-driven PM | Kanban, Gantt, time tracking, time blocking, whiteboard, sprints, neuro-inclusive | Limited GitHub integration, smaller community, complex for small teams |
| **OpenProject** | 14.6k | Enterprise hybrid PM | Gantt, Scrum/Kanban, backlog, time tracking, test cases, fine-grained permissions, EU government adoption | Complex for small teams, heavy UI, no GitHub PR sync, steep learning curve |

### Enterprise SaaS

| Name | Price/user/mo | Target Audience | Key Features | Why Not |
|------|--------------|-----------------|-------------|---------|
| **Jira** | Free–$14.54 | Dev teams, enterprise | Full Scrum/Kanban, JQL, GitHub integration, time tracking, burndown, 1000+ integrations | Per-user pricing, complex UI, vendor lock-in, data sovereignty |
| **Linear** | Free–$10 | SaaS startups, dev teams | Fast UI, cycles, kanban, GitHub/GitLab sync, keyboard-first, CLI | No time tracking, no backlog, no Gantt, SaaS-only, startup risk |
| **Asana** | Free–$24.99 | Cross-functional, marketing | List/Kanban/Gantt/Calendar, OKRs, form intake, 200+ integrations, AI features | Not developer-focused, no GitHub PR sync, not agile-native |
| **Trello** | Free–$17.50 | Small teams, simple workflows | Kanban, automation (Butler), Power-Ups, Mirror Cards | Kanban-only, no backlog, no sprint, no GitHub native, Power-Up cost creep |
| **Monday.com** | $9–$19 | Marketing, agencies, SMBs | Kanban/Gantt/Timeline, time tracking, automation, 200+ integrations, AI | Not dev-focused, no GitHub PR sync, expensive at scale, feature bloat |
| **ClickUp** | Free–$12 | Enterprises, power users | 15+ views, unlimited custom fields, sprints, time tracking, GitHub sync, docs | Overwhelming, customization fatigue, not kanban-optimized |

### Dutch Government

No dedicated Dutch government task management tools were identified. OpenProject sees EU public sector adoption for its compliance features. Tasks in Dutch government workflows are modeled as `InterneTaak` within the VNG Klantinteracties standard, making a Procest bridge (case tasks → Planix) the relevant Dutch government integration.

## 2. Feature Matrix

### Core Task Management

| Feature | Tier | Justification |
|---------|------|---------------|
| Task CRUD (title, description, status, priority) | **MVP** | Core entity |
| Task list with search, sort, filter | **MVP** | Navigation |
| Task detail view (CnDetailPage + CnObjectSidebar) | **MVP** | Critical UX pattern |
| Task assignment to user | **MVP** | Team workload distribution |
| Task due date | **MVP** | Deadline awareness |
| Task priority (low / normal / high / urgent) | **MVP** | Triage |
| Task status lifecycle (open → in_progress → done) | **MVP** | Core workflow |
| Task labels / tags | **MVP** | Cross-project categorization |
| Sub-tasks (one level deep) | **V1** | Breakdown complex work |
| Task dependencies (blocks / is-blocked-by) | **V1** | Flow management |
| Recurring tasks | **V1** | Regular work patterns |
| Task templates | **Enterprise** | Standardized workflows |
| Custom task fields | **Enterprise** | Domain-specific metadata |

### Project Management

| Feature | Tier | Justification |
|---------|------|---------------|
| Project CRUD (title, description, status, color, icon) | **MVP** | Core container |
| Project list with search and filter | **MVP** | Navigation |
| Project members (add/remove users) | **MVP** | Team collaboration |
| Project archiving | **MVP** | Lifecycle management |
| Project templates | **V1** | Standardized project setup |
| Project portfolios (grouping across projects) | **Enterprise** | Cross-team visibility |
| Project milestones | **V1** | Key deliverable tracking |

### Kanban Board

| Feature | Tier | Justification |
|---------|------|---------------|
| Kanban board view per project | **MVP** | Core flow visualization |
| Configurable columns (create, rename, reorder, delete) | **MVP** | Flow customization |
| Drag-and-drop task cards between columns | **MVP** | Core kanban interaction |
| WIP limits per column (soft, visual warning) | **MVP** | Core kanban practice |
| Task card anatomy (title, assignee, due date, labels, priority) | **MVP** | At-a-glance status |
| Column color coding | **MVP** | Visual workflow clarity |
| Swimlanes (group cards by assignee or priority) | **V1** | Workload visibility |
| Board filter (by assignee, label, priority) | **MVP** | Focus on relevant work |
| View toggle on board (kanban ↔ list) | **MVP** | Users need a dense list view alongside kanban for large projects (Linear, Plane, Jira pattern) |
| Task card hover quick-actions (assign, set due date, change status) | **MVP** | Assign/update without opening detail — Jira, Asana, Trello pattern |
| Task count per column (shown in column header) | **MVP** | Instant awareness of column load; present in every kanban tool |
| Overdue task highlight (red border/badge on card) | **MVP** | Urgency signal visible without opening task — Jira, Linear, Asana pattern |
| Collapsed columns | **V1** | Space management |
| Blocked task indicators | **V1** | Dependency visibility |
| Card quick-edit (inline title/status change) | **V1** | Speed of use |

### Backlog Management

| Feature | Tier | Justification |
|---------|------|---------------|
| Backlog view (tasks without a column) | **MVP** | Holds unscheduled work |
| Drag task from backlog to board column | **MVP** | Flow into kanban |
| Backlog sorting (by priority, due date, created date) | **MVP** | Triage |
| Backlog search and filter | **MVP** | Find tasks quickly |
| Bulk select and move tasks | **V1** | Backlog grooming |
| Backlog item ordering (manual drag-and-drop rank) | **V1** | Priority ordering |
| Backlog statistics (count, overdue, unassigned) | **V1** | Health metrics |

### Time Tracking

| Feature | Tier | Justification |
|---------|------|---------------|
| Time estimate per task (minutes) | **MVP** | Planning baseline |
| Manual time log per task (duration + date + description) | **MVP** | Actual time capture |
| Multiple time entries per task | **MVP** | Log time across sessions/days |
| Total logged time per task | **MVP** | Actual vs estimate comparison |
| My timesheet view (all logs by current user, by date) | **MVP** | Personal time overview |
| Project time report (total estimated vs logged) | **V1** | Project health reporting |
| Team timesheet (admin: all users, export) | **V1** | Billing / capacity reporting |
| Timer (start/stop, auto-log) | **V1** | Convenient time capture |
| Time tracking export (CSV) | **V1** | External reporting / billing |
| Overtime / budget alerts | **Enterprise** | Project cost control |

### Dashboard & My Work

| Feature | Tier | Justification |
|---------|------|---------------|
| Personal dashboard (landing page) | **MVP** | Orientation on login |
| My Work view (tasks assigned to me, across all projects) | **MVP** | Personal workload overview |
| Overdue task list | **MVP** | Urgency signal |
| Tasks due this week | **MVP** | Near-term focus |
| Recently updated tasks | **MVP** | Context continuity |
| KPI cards (open tasks, overdue, in progress, completed today) | **MVP** | At-a-glance health |
| My recent projects | **MVP** | Quick navigation |
| Activity feed (task updates across my projects) | **V1** | Team awareness |
| Task completion streak / gamification | **Enterprise** | Team motivation |

### Admin Settings

| Feature | Tier | Justification |
|---------|------|---------------|
| OpenRegister setup (register + schemas) | **MVP** | App initialization |
| App version info (CnVersionInfoCard) | **MVP** | Standard NC admin pattern |
| Default column set (configure global template) | **MVP** | Project consistency |
| Label management (create, edit, delete app-wide labels) | **MVP** | Shared vocabulary |
| User settings — manage which users can create projects | **V1** | Access control |
| Procest integration toggle (enable/disable bridge) | **V1** | Cross-app integration |

### User Settings

| Feature | Tier | Justification |
|---------|------|---------------|
| NcAppSettingsDialog with notification toggles | **MVP** | Standard NC pattern (required by openspec/specs/nextcloud-app) |
| Default view preference (kanban / backlog / my work) | **MVP** | Personal workflow |
| Notification toggle: task assigned to me | **MVP** | Assignment awareness |
| Notification toggle: task due date reminder | **MVP** | Deadline awareness |
| Notification toggle: task status changed | **V1** | Status tracking |
| Notification toggle: comment added to my task | **V1** | Collaboration |
| Items per page (backlog pagination) | **V1** | Personal display preference |

### Notifications

| Feature | Tier | Justification |
|---------|------|---------------|
| Notification: task assigned | **MVP** | Immediate assignment feedback |
| Notification: task due date approaching (1 day before) | **MVP** | Deadline reminder |
| Notification: task overdue | **V1** | Urgency escalation |
| Notification: task commented | **V1** | Collaboration |
| Notification: task status changed | **V1** | Status tracking |
| Notification: project member added | **V1** | Team awareness |
| Push notification (Nextcloud push proxy) | **V1** | Mobile delivery |

### Collaboration

| Feature | Tier | Justification |
|---------|------|---------------|
| Notes/comments on tasks (ICommentsManager) | **MVP** | Collaboration basics |
| File attachments on tasks (CnObjectSidebar Files tab) | **MVP** | Document management |
| @mention users in comments | **V1** | Direct collaboration |
| Activity stream on task (CnObjectSidebar Audit Trail tab) | **MVP** | Change visibility |
| Shared project access (multi-user) | **MVP** | Team collaboration |
| Talk integration (per-task conversation) | **V1** | Real-time discussion |

### Reporting & Analytics

| Feature | Tier | Justification |
|---------|------|---------------|
| Project progress (tasks done / total) | **MVP** | Quick project health |
| Cumulative flow diagram (per project) | **V1** | Kanban flow metrics |
| Team workload report (tasks per user) | **V1** | Capacity visibility |
| Time report (estimated vs actual, per project) | **V1** | Project cost tracking |
| Label/category distribution chart | **V1** | Work type analysis |
| Throughput chart (tasks completed per week) | **V1** | Team velocity (flow-based) |
| Cycle time tracking (column entry to exit) | **Enterprise** | Advanced flow metrics |

### Integration

| Feature | Tier | Justification |
|---------|------|---------------|
| Procest bridge (case → project/task) | **MVP** | Cross-app workflow (sister app) |
| Nextcloud Files (attachment via CnObjectSidebar) | **MVP** | Document management |
| Nextcloud Activity (publish task events) | **MVP** | Unified timeline |
| Nextcloud Notifications (INotificationManager) | **MVP** | In-app push |
| CalDAV/VTODO export (sync to Nextcloud Tasks app) | **V1** | Calendar integration |
| GitHub/GitLab sync (via OpenConnector) | **V1** | Dev team integration |
| Webhook outgoing (on task create/update/complete) | **V1** | External system triggers |
| Import from Nextcloud Deck (boards → projects) | **V1** | Migration from Deck |
| Import from CSV (tasks bulk import) | **V1** | Data onboarding |

### Security & Compliance

| Feature | Tier | Justification |
|---------|------|---------------|
| Nextcloud user authentication (OCP) | **MVP** | NC-native auth |
| Project-level access control (members only) | **MVP** | Data isolation |
| Audit trail on all task changes (CnObjectSidebar) | **MVP** | Accountability |
| WCAG AA accessibility (NL Design System) | **MVP** | Government requirement |
| GDPR-compliant (no external data transfer) | **MVP** | Sovereign deployment |
| Role-based project permissions (viewer/editor/admin) | **Enterprise** | Enterprise governance |

## 3. Settings & Notifications (Derived from Features)

### 3.1 Admin Settings (IAppConfig)

| Setting | Feature Source | Type | Default | Tier |
|---------|---------------|------|---------|------|
| `default_columns` | Kanban board | JSON (array of column titles) | `["To Do","In Progress","Review","Done"]` | MVP |
| `allow_project_creation` | Project management | enum: all, admins, groups | `all` | V1 |
| `procest_bridge_enabled` | Procest integration | boolean | `false` | V1 |
| `procest_base_url` | Procest integration | string | `""` | V1 |
| `max_projects_per_user` | Project management | integer (0 = unlimited) | `0` | Enterprise |

### 3.2 User Settings (OCP\IConfig, NcAppSettingsDialog)

| Setting | Feature Source | Type | Default | Tier |
|---------|---------------|------|---------|------|
| `notify_assigned` | Task assignment | boolean | `true` | MVP |
| `notify_due_reminder` | Due date reminder | boolean | `true` | MVP |
| `notify_overdue` | Overdue tasks | boolean | `true` | V1 |
| `notify_commented` | Task comments | boolean | `true` | V1 |
| `notify_status_changed` | Status changes | boolean | `false` | V1 |
| `default_view` | Dashboard/navigation | enum: kanban, backlog, my-work | `my-work` | MVP |
| `items_per_page` | Backlog pagination | integer | `25` | V1 |

### 3.3 Notifications (OCP\Notification\IManager)

| Event | Subject Key | Setting Key | Recipient Logic | Tier |
|-------|-------------|-------------|-----------------|------|
| Task assigned | `task_assigned` | `notify_assigned` | Assignee | MVP |
| Task due tomorrow | `task_due_soon` | `notify_due_reminder` | Assignee | MVP |
| Task overdue | `task_overdue` | `notify_overdue` | Assignee | V1 |
| Comment added | `task_commented` | `notify_commented` | Assignee + task watchers | V1 |
| Task status changed | `task_status_changed` | `notify_status_changed` | Assignee | V1 |
| Project member added | `project_member_added` | — | New member | V1 |

**Backend pattern**: `NotificationService` with `SUBJECT_SETTING_MAP` constant mapping subjects to user setting keys. See `pipelinq/lib/Service/NotificationService.php` for reference.

## 4. Gap Analysis

### What Competitors Do Well

- **Plane**: Developer-native UX (issue-centric), excellent GitHub/GitLab sync, modern React UI with fast performance
- **Taiga**: Full Scrum methodology, backlog grooming, burndown charts — best agile toolset in OSS
- **Jira**: Most comprehensive agile toolset, JQL power querying, massive ecosystem, GitHub integration
- **Leantime**: Best time tracking + goal alignment in open source; neuro-inclusive design is unique

### What They Lack

| Gap | Opportunity for Planix |
|-----|------------------------|
| Nextcloud integration | None of the competitors integrate with NC Files, Calendar, Talk, Contacts — Planix is native |
| Time tracking in kanban tools | Deck, WeKan, Kanboard, Linear, Plane — no native time tracking |
| Backlog in simple tools | Deck, WeKan, Trello, Kanboard — no structured backlog |
| Privacy-first for EU gov | Most SaaS options require US data residency; Planix is fully self-hosted |
| Procest/ZGW bridge | No competitor supports Dutch government case-to-task integration |
| NL Design System | No competitor implements Dutch government accessibility standards |
| OpenRegister data model | No competitor exposes tasks as queryable linked data via OpenRegister |

### Nextcloud-Native Advantages

| Capability | Why Competitors Cannot Match It |
|------------|--------------------------------|
| **NC Files integration** | Tasks link to actual files in the user's Nextcloud; competitors use separate file storage |
| **NC Calendar sync** | Task due dates appear in NC Calendar (CalDAV); no separate calendar tool needed |
| **NC Talk integration** | Per-task conversations in Talk; competitors require Slack/external chat |
| **NC User management** | Assignees are existing NC users — no separate user invite/signup flow |
| **NC Activity stream** | Task changes appear in the unified NC Activity stream alongside files and shares |
| **Sovereign deployment** | Full data control; no SaaS dependency; offline-capable |
| **OpenRegister data platform** | All tasks queryable as linked data; other apps can reference and extend task objects |

## 5. Strategic Positioning

**Positioning Statement**: Planix is the developer-first kanban app for Nextcloud — delivering time tracking, backlog management, and flow-based project management without Jira's complexity or Deck's limitations.

### Differentiation Strategy

1. **Platform leverage**: Planix orchestrates Nextcloud's native capabilities (Files, Calendar, Talk, Activity, Notifications) rather than rebuilding them. The result is seamless integration that external tools cannot replicate.

2. **Dev-team first**: Time tracking and backlog management close the two biggest gaps Deck leaves open. GitHub/GitLab sync (V1 via OpenConnector) makes Planix the missing link between Nextcloud and developer workflows.

3. **Data platform**: All tasks are stored in OpenRegister as queryable JSON objects with Schema.org type annotations. Other Conduction apps (Procest, Pipelinq) can query, reference, and relate to Planix tasks — enabling cross-app dashboards and automation without custom integrations.

### Risks

| Risk | Severity | Mitigation |
|------|---------|------------|
| Nextcloud Deck owns the kanban mindshare in the NC ecosystem | High | Differentiate on time tracking + backlog + dev integration — Deck explicitly excludes these |
| Plane (40k+ ★) moves faster than we can | Medium | Focus on Nextcloud-native features that Plane will never build; don't compete on Plane's turf |
| Small initial team → scope creep | Medium | MVP is strictly kanban + backlog + time tracking; defer everything else |
| Drag-and-drop kanban is UX-complex in Vue 2 | Medium | Use a proven drag library (vue-draggable/SortableJS); budget time for polish |
| OpenRegister performance at scale (many tasks) | Medium | Lean on OpenRegister's pagination and indexing; document pagination patterns early |

## 6. Recommended Feature Set Summary

### MVP (44 features)
Flow-based kanban with backlog and time tracking for dev/IT teams on Nextcloud. Covers the gap left by Nextcloud Deck.

1. Task CRUD (title, description, status, priority)
2. Task list with search, sort, filter
3. Task detail view (CnDetailPage + CnObjectSidebar)
4. Task assignment to user
5. Task due date
6. Task priority (low / normal / high / urgent)
7. Task status lifecycle (open → in_progress → done)
8. Task labels / tags
9. Time estimate per task
10. Manual time log per task (duration + date + description)
11. Multiple time entries per task
12. Total logged time per task
13. My timesheet view
14. Project CRUD (title, description, status, color, icon)
15. Project list with search and filter
16. Project members (add/remove users)
17. Project archiving
18. Kanban board view per project
19. Configurable columns (create, rename, reorder, delete)
20. Drag-and-drop task cards between columns
21. WIP limits per column (soft, visual warning)
22. Task card anatomy (title, assignee, due date, labels, priority)
23. Column color coding
24. Board filter (by assignee, label, priority)
25. View toggle on board (kanban ↔ list)
26. Task card hover quick-actions (assign, due date, status)
27. Task count in column header
28. Overdue task highlight (red border) on card
29. Backlog view (tasks without a column)
30. Drag task from backlog to board column
31. Backlog sorting (by priority, due date, created date)
32. Backlog search and filter
33. Personal dashboard (landing page with KPI cards)
34. My Work view (tasks assigned to me, across all projects)
35. Overdue task list
36. Tasks due this week
37. Recently updated tasks
38. Notes/comments on tasks (ICommentsManager)
39. File attachments on tasks (CnObjectSidebar)
40. Activity stream on task (Audit Trail tab)
41. Shared project access (multi-user)
42. Project progress (tasks done / total)
43. Procest bridge (case → project/task)
44. NcAppSettingsDialog (notify_assigned, notify_due_reminder, default_view)

### V1 (25 additional features, continuing from 44)

More collaboration, reporting, dev integrations, and advanced kanban.

45. Sub-tasks (one level deep)
46. Task dependencies (blocks / is-blocked-by)
47. Recurring tasks
48. Project milestones
49. Project templates
50. Swimlanes (group by assignee or priority)
51. Collapsed columns
52. Blocked task indicators
53. Card quick-edit (inline title/status change)
54. Bulk select and move tasks from backlog
55. Backlog item ordering (manual drag-and-drop rank)
56. Backlog statistics (count, overdue, unassigned)
57. Project time report (estimated vs logged)
58. Team timesheet (admin, all users, export CSV)
59. Timer (start/stop, auto-log)
60. Time tracking export (CSV)
61. Cumulative flow diagram
62. Team workload report (tasks per user)
63. Throughput chart (tasks/week)
64. @mention users in comments
65. Talk integration (per-task conversation)
66. Activity feed on dashboard
67. CalDAV/VTODO export (sync to Nextcloud Tasks)
68. GitHub/GitLab sync (via OpenConnector)
69. Import from Nextcloud Deck

### Enterprise (10 additional features, continuing from 69)

Governance, advanced analytics, and custom workflows.

70. Task templates
71. Custom task fields
72. Project portfolios (cross-project grouping)
73. Role-based project permissions (viewer/editor/admin)
74. Cycle time tracking (column entry to exit)
75. Overtime / budget alerts
76. Task completion gamification
77. Webhook outgoing (on task events)
78. Import from CSV
79. Advanced admin controls (max projects per user, role restrictions)
