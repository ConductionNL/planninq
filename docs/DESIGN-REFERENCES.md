# Planninq — Design References & Wireframes

## 1. Design Inspiration Sources

### Dashboard / Landing Page
| Source | URL / Search | Key Patterns |
|--------|-------------|--------------|
| Linear | linear.app | Minimal dashboard, "My Issues" with priority grouping, cycle progress |
| Plane | plane.so | Project cards grid, issue count badges, recent activity |
| Asana "My Tasks" | asana.com | Today/upcoming/later sections, clean personal task view |
| Dribbble "project management dashboard" | Search Dribbble | KPI cards, project progress bars, team workload heatmaps |
| Figma Community "PM Dashboard Kit" | figma.com/community | Card-based layouts with progress indicators and activity feeds |

### Kanban Board
| Source | URL / Search | Key Patterns |
|--------|-------------|--------------|
| Nextcloud Deck | apps.nextcloud.com/apps/deck | Board/Stack/Card — familiar Nextcloud kanban, drag-and-drop |
| Plane Board View | plane.so | Column headers with task count, minimal cards, fast drag |
| Linear Kanban | linear.app | Compact cards, status dots, priority indicators, clean columns |
| Jira Kanban | atlassian.com/jira | WIP limit indicators, swimlanes, card quick-actions on hover |
| Trello | trello.com | Gold standard UX: minimal card, smooth drag-and-drop, column colors |
| Dribbble "kanban UI" | Search Dribbble | Card anatomy: avatar, due date, label chips, priority dot |

### Task Detail
| Source | URL / Search | Key Patterns |
|--------|-------------|--------------|
| Linear Issue Detail | linear.app | Right sidebar with metadata, activity feed, minimal card layout |
| Plane Issue Detail | plane.so | Card split-layout: description left, metadata panel right |
| Jira Issue View | atlassian.com/jira | Activity timeline, attachments, sub-tasks, link section |
| Asana Task Detail | asana.com | Right sidebar panel, section grouping, attachment thumbnails |

### Backlog View
| Source | URL / Search | Key Patterns |
|--------|-------------|--------------|
| Jira Backlog | atlassian.com/jira | Ordered list, drag-to-reorder, batch selection, "Add to board" action |
| Linear Backlog | linear.app | Issue list with status/priority inline, quick filters |
| Plane List View | plane.so | Dense table-like list, column headers sortable, group by project |

### My Work / Personal View
| Source | URL / Search | Key Patterns |
|--------|-------------|--------------|
| Linear "My Issues" | linear.app | Grouped by: Active, Backlog, Done — priority-sorted |
| Asana "My Tasks" | asana.com | Sections: Today, Upcoming, Later — assignee-filtered |
| Jira "My Work" | atlassian.com/jira | Assigned issues with project label, due date, priority icon |
| Dribbble "personal workload" | Search Dribbble | Overdue section highlighted red, weekly task completion chart |

### Admin Settings
| Source | URL / Search | Key Patterns |
|--------|-------------|--------------|
| OpenRegister admin settings | Reference implementation | CnVersionInfoCard + CnSettingsSection pattern |
| Nextcloud admin panel | Nextcloud core | NcSettingsSection, card-based sections, toggle switches |

---

## 2. Missing Features Identified from Design Patterns

### MVP Additions
| Feature | Source Pattern | Justification |
|---------|--------------|---------------|
| View toggle on board (kanban ↔ list) | Linear, Plane, Jira | Users need a dense list view alongside kanban for large projects |
| Task card hover quick-actions | Jira, Asana, Trello | Assign, change status, set due date without opening detail |
| Task count per column (shown in column header) | All kanban tools | Instant awareness of column load without counting cards |
| Overdue task highlight (red border/badge) on card | Jira, Linear, Asana | Urgency signal visible without opening task |

### V1 Additions
| Feature | Source Pattern | Justification |
|---------|--------------|---------------|
| Aging indicator (days in current column) | Jira, Kanboard | Shows how long a task has been stuck; enables flow analysis |
| Task quick-add inline in column | Trello, Plane | "+" at column bottom to create task without opening form |
| Cumulative flow diagram | Kanban Guide, Leantime | Standard kanban flow metric — cycle time and WIP trend |
| Card size toggle (compact / full) | Linear, Plane | Compact mode for 50+ card boards; full for focus |
| Keyboard shortcut to open task | Linear | Press `Enter` on selected card to open detail |

### Enterprise Additions
| Feature | Source Pattern | Justification |
|---------|--------------|---------------|
| Cycle time heatmap per column | Advanced kanban tools | Shows which columns create bottlenecks |
| Team capacity view (workload per user) | Jira, OpenProject | Bar chart of open tasks per assignee across projects |
| Custom dashboard widgets (drag-to-arrange) | Monday.com, ClickUp | Power users configure their own dashboard layout |

---

## 3. Wireframes

### 3.1 Main Dashboard (Landing Page)

```
┌────────────────────────────────────────────────────────────────────┐
│  PLANNINQ                                          [Search] [+ Task] │
├──────────┬───────────┬──────────┬──────────┬───────────────────────┤
│ Dashboard│ Projects  │ My Work  │ Timesheet│                       │
├──────────┴───────────┴──────────┴──────────┘                       │
│                                                                    │
│  Good morning, Wilco  ·  Tuesday, 24 March 2026                   │
│                                                                    │
│  ┌──────────────┐ ┌──────────────┐ ┌────────────┐ ┌────────────┐ │
│  │  Open Tasks  │ │   Overdue    │ │ In Progress│ │Done Today  │ │
│  │      14      │ │      3       │ │     5      │ │     2      │ │
│  │  → My Work   │ │ ⚠ urgent    │ │            │ │ ✓          │ │
│  └──────────────┘ └──────────────┘ └────────────┘ └────────────┘ │
│                                                                    │
│  ── Recent Projects ───────────────────────────────────────────── │
│                                                                    │
│  ● API Gateway Refactor        ████████████░░░  8/12 tasks  ↗    │
│  ● Nextcloud App — Planninq      ██░░░░░░░░░░░░   3/22 tasks  ↗    │
│  ● Security Audit Q1           ████████████████ 12/12 done  ✓    │
│  ● Infra Migration             █████░░░░░░░░░░  5/14 tasks  ↗    │
│                                                                    │
│  ── Due This Week ─────────────────────────────────────────────── │
│                                                                    │
│  ⬤ urgent  Fix auth token expiry bug         API Gateway  Today  │
│  ⬤ high    Write deployment checklist        Infra Migr.  Wed    │
│  ⬤ normal  Review PR #42 — rate limiting     API Gateway  Thu    │
│  ⬤ normal  Update README with new endpoints  API Gateway  Fri    │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

### 3.2 Project List View

```
┌────────────────────────────────────────────────────────────────────┐
│  PLANNINQ › Projects                                    [+ Project] │
├────────────────────────────────────────────────────────────────────┤
│  🔍 Search projects...          [Active ▾]   [My projects only □] │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ● API Gateway Refactor                               [Board] [⋯] │
│    Backend infra · 3 members · 8/12 tasks             ██████░░░░  │
│    Last activity: 2h ago · 1 overdue                             │
│                                                                    │
│  ● Nextcloud App — Planninq             [Case: 2024-001] [Board] [⋯]│
│    Frontend · 2 members · 3/22 tasks                  █░░░░░░░░░  │
│    Last activity: 1d ago · 0 overdue                             │
│                                                                    │
│  ● Infra Migration                                    [Board] [⋯] │
│    DevOps · 4 members · 5/14 tasks                    ████░░░░░░  │
│    Last activity: 3d ago · 2 overdue ⚠                          │
│                                                                    │
│  ── Archived ─────────────────────────────────────────────────── │
│  ○ Security Audit Q1 · Completed 15 Mar · 12/12 done  [View] [⋯] │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

### 3.3 Kanban Board View

```
┌────────────────────────────────────────────────────────────────────┐
│  PLANNINQ › API Gateway Refactor       [Board] [Backlog] [Settings] │
├──────────────────────────────────────────────────────────────────── │
│  Filter: [All assignees ▾] [All labels ▾] [Priority ▾]  [+ Task] │
├──────────────────────────────────────────────────────────────────── │
│                                                                    │
│  ┌─ To Do (3) ────┐ ┌─ In Progress (3/3)─┐ ┌─ Review (1) ──────┐ │
│  │ WIP: none      │ │ WIP: 3 ⚠ AT LIMIT  │ │ WIP: 2            │ │
│  ├────────────────┤ ├────────────────────┤ ├───────────────────┤ │
│  │ ┌────────────┐ │ │ ┌────────────────┐ │ │ ┌───────────────┐ │ │
│  │ │ Add rate   │ │ │ │ ⚠ Fix auth    │ │ │ │ Write API     │ │ │
│  │ │ limiting   │ │ │ │ token expiry  │ │ │ │ docs v2       │ │ │
│  │ │ middleware │ │ │ │ ─────────────  │ │ │ │ ─────────────  │ │ │
│  │ │ ─────────  │ │ │ │ 👤 Wilco  🔴  │ │ │ │ 👤 Sofia      │ │ │
│  │ │ [backend]  │ │ │ │ ⏰ Today  ⬤H │ │ │ │ [docs]        │ │ │
│  │ └────────────┘ │ │ └────────────────┘ │ │ └───────────────┘ │ │
│  │ ┌────────────┐ │ │ ┌────────────────┐ │ │                   │ │
│  │ │ Write load │ │ │ │ Add JWT        │ │ │   [+ Add task]    │ │
│  │ │ tests      │ │ │ │ refresh flow   │ │ └───────────────────┘ │
│  │ │ ─────────  │ │ │ │ ─────────────  │ │                       │
│  │ │ 👤 —   ⬤N │ │ │ │ 👤 Ana   Thu  │ │ ┌─ Done (4) ────────┐ │
│  │ │ ⏰ Fri     │ │ │ │ [auth] ⬤H    │ │ │                   │ │
│  │ └────────────┘ │ │ └────────────────┘ │ │ ┌───────────────┐ │ │
│  │ ┌────────────┐ │ │ ┌────────────────┐ │ │ │ ✓ Set up      │ │ │
│  │ │ Pagination │ │ │ │ Migrate to     │ │ │ │ CI pipeline   │ │ │
│  │ │ for /list  │ │ │ │ PostgreSQL     │ │ │ │ [devops]  ✓   │ │ │
│  │ │ endpoints  │ │ │ │ connection     │ │ │ └───────────────┘ │ │
│  │ │ ─────────  │ │ │ │ pool           │ │ │ ┌───────────────┐ │ │
│  │ │ 👤 —   ⬤L │ │ │ │ ─────────────  │ │ │ │ ✓ API v2      │ │ │
│  │ └────────────┘ │ │ │ 👤 Wilco Wed  │ │ │ │ contract      │ │ │
│  │                │ │ │ [infra]  ⬤N   │ │ │ │ [docs]    ✓   │ │ │
│  │  [+ Add task]  │ │ └────────────────┘ │ │ └───────────────┘ │ │
│  └────────────────┘ └────────────────────┘ └───────────────────┘ │
│                                                                    │
│  Card legend: 👤 Assignee · ⏰ Due date · 🔴 Overdue              │
│               ⬤U Urgent · ⬤H High · ⬤N Normal · ⬤L Low          │
└────────────────────────────────────────────────────────────────────┘
```

### 3.4 Task Detail View (CnDetailPage + CnObjectSidebar)

```
┌────────────────────────────────────────────────────────────────────┐
│  ← Back to Board   Fix auth token expiry bug   [Edit] [Delete] [⋯]│
├────────────────────────────────────────────────┬───────────────────┤
│                                                │ ╔═ Sidebar ═════╗ │
│  ┌─── Core Info ──────────────────────────┐   │ ║ Files │ Notes  ║ │
│  │ Status    ● In Progress  │ Priority ⬤H │   │ ║ Tags  │ Audit  ║ │
│  │ Assignee  👤 Wilco       │ Project  API│   │ ╠════════════════╣ │
│  │ Due Date  🔴 Today       │ Column   In │   │ ║ 📎 auth-bug    ║ │
│  │ Created   22 Mar 2026    │ Progress    │   │ ║    -flow.png   ║ │
│  │ Labels    [auth] [urgent]│             │   │ ║ 📎 token-      ║ │
│  │ Estimate  2h · Logged 45m│             │   │ ║    spec.pdf    ║ │
│  │                          ├─────────────│   │ ║                ║ │
│  │ Description:             │             │   │ ║ [Upload file]  ║ │
│  │ JWT tokens expire after  │             │   ║                ║ │
│  │ 15 min but the refresh   │             │   ║                ║ │
│  │ endpoint returns 401     │             │   ║                ║ │
│  │ intermittently. Repro:   │             │   ║                ║ │
│  │ 1. Login, 2. Wait 16m,   │             │   ║                ║ │
│  │ 3. Refresh — 50% failure │             │   ║                ║ │
│  └──────────────────────────┘             │   ╚════════════════╝ │
│                                           │                       │
│  ┌─── Time Tracking ─────────────────┐   │                       │
│  │ Estimate   2h 00m                 │   │                       │
│  │ Logged     0h 45m  ████░░░░░░     │   │                       │
│  │ Remaining  1h 15m                 │   │                       │
│  │                        [Log time] │   │                       │
│  │ ─ Entries ──────────────────────  │   │                       │
│  │ 24 Mar · 0h 45m · Reproduced bug  │   │                       │
│  └────────────────────────────────── ┘   │                       │
│                                           │                       │
│  ┌─── Sub-tasks (V1) ────────────────┐   │                       │
│  │ □ Identify race condition in      │   │                       │
│  │   RefreshTokenService             │   │                       │
│  │ □ Add integration test coverage   │   │                       │
│  │ ☑ Confirm bug is reproducible     │   │                       │
│  └───────────────────────────────────┘   │                       │
├────────────────────────────────────────────────────────────────────┤
```

**Component hierarchy**:
```
CnDetailPage (title="Fix auth token expiry bug", back-route=board)
├─ #header-actions → [Edit] [Delete] [⋯ More]
├─ #default → Card widgets (fixed grid)
│   ├─ CnDetailCard (title="Core Info")
│   │   └─ info grid: status, assignee, due date, priority, labels, estimate
│   ├─ CnDetailCard (title="Time Tracking")
│   │   └─ estimate/logged progress bar + TimeEntry list + [Log time]
│   └─ CnDetailCard (title="Sub-tasks") [V1]
│       └─ sub-task checklist
└─ CnObjectSidebar (object-type="planninq-task", object-id=uuid)
    ├─ Tab: Files    — upload, list, open (via OpenRegister → NC Files)
    ├─ Tab: Notes    — add, list, delete (via OpenRegister → NC Comments)
    ├─ Tab: Tags     — add, remove, list (via OpenRegister → NC Tags)
    └─ Tab: Audit Trail — read-only log (via auditTrailsPlugin)
```

### 3.5 Backlog View

```
┌────────────────────────────────────────────────────────────────────┐
│  PLANNINQ › API Gateway Refactor       [Board] [Backlog] [Settings] │
├────────────────────────────────────────────────────────────────────┤
│  Backlog (8 tasks)  🔍 Search...   [Priority ▾] [Assignee ▾] [+] │
├────────────────────────────────────────────────────────────────────┤
│  ☐  Prio  Task                           Assignee  Due     Labels  │
├────────────────────────────────────────────────────────────────────┤
│  ☐  ⬤U   Add CSRF token validation       Wilco     —       [sec]  │
│  ☐  ⬤H   Implement rate limit backoff    —         Fri     [api]  │
│  ☐  ⬤H   Write OpenAPI 3.0 spec          Sofia     —       [docs] │
│  ☐  ⬤N   Add structured logging (JSON)  —         —       [infra] │
│  ☐  ⬤N   Benchmark endpoint latency     Ana       —       [perf] │
│  ☐  ⬤N   Update error response format   —         —       [api]  │
│  ☐  ⬤L   Add example curl commands       Sofia     —       [docs] │
│  ☐  ⬤L   Document rate limit headers    —         —       [docs] │
├────────────────────────────────────────────────────────────────────┤
│  [Move to board ▾]  [Assign ▾]  [Set priority ▾]   Selected: 0   │
└────────────────────────────────────────────────────────────────────┘
```

### 3.6 My Work View

```
┌────────────────────────────────────────────────────────────────────┐
│  PLANNINQ › My Work                                                  │
├────────────────────────────────────────────────────────────────────┤
│  Showing tasks assigned to you across all projects                │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  🔴 OVERDUE (2) ─────────────────────────────────────────────────│
│                                                                    │
│  ⬤U  Fix auth token expiry bug     [● In Progress] API Gateway  │
│      Due: Yesterday                                               │
│  ⬤H  Deploy staging environment   [● Open]         Infra Migr. │
│      Due: 2 days ago                                              │
│                                                                    │
│  📅 DUE THIS WEEK (3) ────────────────────────────────────────── │
│                                                                    │
│  ⬤H  Migrate to PostgreSQL pool    [● In Progress] API Gateway  │
│      Due: Wednesday                                               │
│  ⬤N  Review PR #42 rate limiting   [● Open]         API Gateway  │
│      Due: Thursday                                                │
│  ⬤N  Write deployment checklist    [● Open]         Infra Migr. │
│      Due: Friday                                                  │
│                                                                    │
│  📋 EVERYTHING ELSE (7) ──────────────────────────────────────── │
│                                                                    │
│  ⬤N  Add CSRF token validation     [● Open]         API Gateway  │
│      No due date                                                  │
│  ⬤L  Add pagination to /list       [● Open]         API Gateway  │
│      No due date                                                  │
│  ⬤L  Document rate limit headers   [● Open]         API Gateway  │
│      No due date                                                  │
│  ··· 4 more ───────────────────────────────────────── [Show all] │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

### 3.7 Admin Settings

```
┌────────────────────────────────────────────────────────────────────┐
│  Administration › Planninq                                           │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ┌─ CnVersionInfoCard ──────────────────────────────────────────┐ │
│  │  Planninq  v0.1.0                         [Check for updates]  │ │
│  │  Installed · OpenRegister: ✓ connected                       │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                    │
│  ┌─ CnSettingsSection: Default Project Configuration ──────────┐ │
│  │  Default columns created when a new project is initialized   │ │
│  │                                                              │ │
│  │  Columns (drag to reorder):                                  │ │
│  │  ≡  To Do          [  Edit  ] [Delete]                       │ │
│  │  ≡  In Progress    [  Edit  ] [Delete]                       │ │
│  │  ≡  Review         [  Edit  ] [Delete]                       │ │
│  │  ≡  Done      ✓   [  Edit  ] [Delete]                       │ │
│  │                                    [+ Add column]            │ │
│  │                                              [Save changes]  │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                    │
│  ┌─ CnSettingsSection: Label Management ───────────────────────┐ │
│  │  App-wide labels available across all projects               │ │
│  │                                                              │ │
│  │  ● auth    #E03A3A  ● backend  #4376FC  ● docs    #27AE60   │ │
│  │  ● devops  #F39C12  ● perf     #9B59B6  ● sec     #E74C3C   │ │
│  │  ● api     #3498DB  ● infra    #1ABC9C                       │ │
│  │                                           [+ Create label]   │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                    │
│  ┌─ CnSettingsSection: OpenRegister Setup ─────────────────────┐ │
│  │  Register: planninq · Status: ✓ Initialized                  │ │
│  │  Schemas: task, project, column, timeEntry, label            │ │
│  │                             [Re-initialize (repair step)]    │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

**Component hierarchy**:
```
CnVersionInfoCard (app-name="Planninq", app-version="0.1.0", show-update-button)
CnSettingsSection (name="Default Project Configuration", doc-url="...")
  └─ editable ordered column list + [Save changes]
CnSettingsSection (name="Label Management", ...)
  └─ label color chips + [+ Create label]
CnSettingsSection (name="OpenRegister Setup", ...)
  └─ register status + [Re-initialize]
```

### 3.8 User Settings Dialog (NcAppSettingsDialog)

```
┌────────────────────────────────────────────────────────────────────┐
│  Planninq Settings                                              [✕]  │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  ┌─ NcAppSettingsSection: Notifications ───────────────────────┐  │
│  │  Choose which events send you a Nextcloud notification.      │  │
│  │                                                              │  │
│  │  [■] Notify me when a task is assigned to me                 │  │
│  │  [■] Remind me 1 day before a task's due date                │  │
│  │  [□] Notify me when a task I own is overdue        (V1)      │  │
│  │  [□] Notify me when someone comments on my task    (V1)      │  │
│  │  [□] Notify me when a task's status changes        (V1)      │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│  ┌─ NcAppSettingsSection: Display ─────────────────────────────┐  │
│  │  Customize how Planninq looks and behaves for you.             │  │
│  │                                                              │  │
│  │  Default view when opening a project:                        │  │
│  │  (●) My Work    ( ) Kanban board    ( ) Backlog              │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                    │
│                                                      [Save]        │
└────────────────────────────────────────────────────────────────────┘
```

**Note**: Uses `NcAppSettingsDialog` (NOT `NcDialog`). Triggered from the `?` / gear icon in the Planninq top navigation bar. See `openspec/specs/nextcloud-app/spec.md` for the authoritative pattern.

### 3.9 Timesheet View

```
┌────────────────────────────────────────────────────────────────────┐
│  PLANNINQ › My Timesheet                                             │
├────────────────────────────────────────────────────────────────────┤
│  [This week ▾]  Mar 24 – Mar 30, 2026           Total: 14h 30m    │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  📅 Monday, Mar 24                                     2h 45m     │
│  ──────────────────────────────────────────────────────────────── │
│  Fix auth token expiry bug       API Gateway     0h 45m  [✎] [✕] │
│  Write deployment checklist      Infra Migration 2h 00m  [✎] [✕] │
│                                                                    │
│  📅 Tuesday, Mar 25                                    4h 00m     │
│  ──────────────────────────────────────────────────────────────── │
│  Fix auth token expiry bug       API Gateway     1h 30m  [✎] [✕] │
│  Migrate to PostgreSQL pool      API Gateway     2h 30m  [✎] [✕] │
│                                                                    │
│  📅 Wednesday, Mar 26                                  3h 45m     │
│  ──────────────────────────────────────────────────────────────── │
│  Review PR #42 — rate limiting   API Gateway     0h 45m  [✎] [✕] │
│  Add CSRF token validation       API Gateway     3h 00m  [✎] [✕] │
│                                                                    │
│  📅 Thursday, Mar 27 · 2h 30m  │  📅 Friday, Mar 28 · 1h 30m    │
│  ──────────────────────────────┤──────────────────────────────── │
│  Pagination for /list   1h 00m │  Write OpenAPI 3.0 spec 1h 30m  │
│  Update error format    1h 30m │                                  │
│                                                                    │
├────────────────────────────────────────────────────────────────────┤
│  Week total: 14h 30m                          [+ Log time]        │
└────────────────────────────────────────────────────────────────────┘
```

**Component hierarchy**:
```
CnListViewLayout (title="My Timesheet")
├─ date range selector (This week / Last week / This month / Custom)
├─ week total badge
├─ CnDataTable (grouped by date)
│   ├─ date group header (date label + daily total)
│   └─ rows: task title (link) | project badge | duration | [edit] [delete]
└─ week total footer + [+ Log time] CTA
```

**Key UX patterns** (sourced from Leantime, OpenProject, Harvest):
- Date grouped rows with daily subtotals — scan work patterns at a glance
- Inline edit and delete per row — correct mistakes without navigating away
- Task title is a clickable link → task detail view (back returns to timesheet)
- Week view with mini day columns for at-a-glance density when days are sparse
- Weekly total prominently displayed in header and footer
- "Log time" CTA always visible — encourages consistent logging

---

## 4. Updated Feature Counts (after design review)

The following features were added to FEATURES.md from design pattern analysis:

**MVP additions** (added to existing feature matrix):
- View toggle on board (kanban ↔ list) — sourced from Linear, Plane, Jira
- Task card hover quick-actions (assign, due date, status) — sourced from Jira, Asana
- Task count in column header — sourced from all kanban tools
- Overdue task highlight (red border) on card — sourced from Jira, Linear

**V1 additions** (added to V1 section):
- Aging indicator (days in current column)
- Inline task quick-add at column bottom
- Card size toggle (compact / full)
- Cumulative flow diagram
- Keyboard shortcut navigation on board

**Enterprise additions** (added to Enterprise section):
- Cycle time heatmap per column
- Team capacity view (workload per user)
- Custom dashboard widgets
