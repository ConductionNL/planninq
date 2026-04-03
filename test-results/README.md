# planix — Test Results Summary

**Date:** 2026-04-04
**Environment:** http://nextcloud.local
**Mode:** Full (6 perspectives)
**Method:** Automated browser testing with Playwright MCP (headless)

> Experimental agentic testing — results should be verified manually for critical findings.

---

## Overall Results

| Status | Count | Percentage |
|--------|-------|------------|
| **PASS** | 35 | 55% |
| **PARTIAL** | 5 | 8% |
| **FAIL** | 6 | 10% |
| **CANNOT_TEST** | 17 | 27% |

---

## FAIL Issues (Requires Attention)

| Feature | Perspective | Summary | Severity |
|---------|-------------|---------|----------|
| Task Creation API | API | POST returns 400 — status enum appears empty/not loading | HIGH |
| Task Update API | API | PUT requires full object resend, PATCH not supported | MEDIUM |
| Health Endpoint | API | GET /api/health returns 500 | MEDIUM |
| Metrics Endpoint | API | GET /api/metrics returns 500 | LOW |
| KPI Cards Roles | Accessibility | Clickable cards lack role="button" and aria-labels (WCAG 4.1.2) | MEDIUM |
| Image Alt Text | Accessibility | 15 images missing alt text (WCAG 1.1.1) | MEDIUM |

---

## PARTIAL Issues (Needs Investigation)

| Feature | Perspective | What Works | What Doesn't |
|---------|-------------|------------|--------------|
| Dashboard Images | Accessibility | Good structure, semantic HTML | 15 images lack alt text |
| Admin Settings | Functional | Version info and OpenRegister config visible | Default columns, label management, Procest toggle missing |
| Admin Settings | UX | Settings page accessible at /apps/planix/settings | /settings/admin/planix returns 404 |
| Settings Form Labels | Accessibility | Inputs functional | Uses div instead of proper `<label>` elements |
| User Settings | UX | Settings page accessible via gear icon | Missing notification and display preferences |

---

## CANNOT_TEST (Blocked)

| Feature | Perspective | Reason |
|---------|-------------|--------|
| Kanban Board | Functional, UX, Performance, Accessibility, Security | Not yet implemented — shows "Bordweergave komt eraan" placeholder |
| Task CRUD | Functional, UX | Task management UI not yet implemented |
| My Work View | Functional, UX | Not accessible from navigation |
| Time Tracking | Functional, UX, Performance, Accessibility, Security | Feature not implemented |
| Backlog | Functional | Shows "Backlogweergave komt eraan" placeholder |
| Procest Integration | UX | No visible UI elements for case references |

---

## Results by Perspective

### Functional
- **PASS**: 8 | **PARTIAL**: 2 | **FAIL**: 0 | **CANNOT_TEST**: 8
- **Key findings**:
  - Project list, search, filter, settings sidebar all work correctly
  - Seed data loads properly (5 labels, 3 projects, 12 columns, 5 tasks)
  - Kanban board, tasks, time tracking, My Work not yet implemented

### UX
- **PASS**: 8 | **PARTIAL**: 2 | **FAIL**: 0 | **CANNOT_TEST**: 5
- **Key findings**:
  - Create project dialog has excellent validation UX (disabled button until valid)
  - Color-coded action buttons in Danger Zone (yellow=archive, red=delete)
  - Full Dutch (nl) translation complete
  - Modal focus management works correctly

### Performance
- **PASS**: 5 | **PARTIAL**: 0 | **FAIL**: 0 | **CANNOT_TEST**: 3
- **Key findings**:
  - All pages load in <500ms, dashboard <1s
  - Zero slow API requests (all <200ms)
  - Search is client-side (instant, no API calls)
  - No performance bottlenecks detected

### Accessibility
- **PASS**: 6 | **PARTIAL**: 1 | **FAIL**: 2 | **CANNOT_TEST**: 3
- **Key findings**:
  - Keyboard navigation works (Tab, Escape, focus indicators)
  - 15 images missing alt text (WCAG 1.1.1 violation)
  - KPI cards missing role="button" and aria-labels (WCAG 4.1.2 violation)
  - Heading hierarchy is logical, color contrast adequate

### Security
- **PASS**: 8 | **PARTIAL**: 0 | **FAIL**: 0 | **CANNOT_TEST**: 3
- **Key findings**:
  - CSRF protection working (412 on missing token)
  - XSS payloads properly escaped in all tested fields
  - All resource IDs use UUIDs (non-enumerable)
  - No sensitive data leaked in console or network responses

### API
- **PASS**: 10 | **PARTIAL**: 0 | **FAIL**: 4 | **CANNOT_TEST**: 0
- **Key findings**:
  - All GET collection endpoints work (project, task, column, label, timeEntry)
  - Pagination (_limit, _offset) works correctly
  - Task creation broken: status enum validation fails (enum appears empty)
  - Health and metrics endpoints return 500

---

## Console Errors (Across All Perspectives)

| Error | Occurrences | Pages |
|-------|-------------|-------|
| Profiler CSS MIME type warning | 6 | All pages (dev environment only) |
| Vue propsData warning | 1 | Dashboard |
| Avatar 404s | 6 | Project pages (test user avatars) |

---

## Recommendations

### High Priority
1. **Fix task creation API** — Status enum validation broken, blocking all task CRUD
2. **Add alt text to images** — 15 images missing alt text (WCAG 1.1.1)
3. **Add roles to KPI cards** — Missing role="button" and aria-labels (WCAG 4.1.2)

### Medium Priority
4. **Fix /api/health and /api/metrics endpoints** — Both return 500
5. **Support PATCH for partial updates** — PUT requires full object resend
6. **Use proper `<label>` elements** in admin settings forms
7. **Register admin settings route** — /settings/admin/planix returns 404

### For Next Test Run
- Re-test after kanban board, tasks, and time tracking are implemented
- Test with non-admin user to verify access control boundaries
- Run screen reader testing (NVDA/JAWS) for deeper accessibility audit
- Load test with >100 projects/tasks to verify pagination and performance at scale
- No test scenarios were defined for `/test-app` — create them with `/test-scenario-create`
