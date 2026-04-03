# planix — performance Test Results

**Date:** 2026-04-04
**Perspective:** performance
**Environment:** http://nextcloud.local
**Browser:** browser-4 (headless)
**Login:** admin

> Experimental agentic testing — results should be verified manually for critical findings.

## Summary

| Status | Count |
|--------|-------|
| PASS | 5 |
| PARTIAL | 0 |
| FAIL | 0 |
| CANNOT_TEST | 3 |

## Results by Feature

### Dashboard

#### Dashboard Load Time
- **Status**: PASS
- **Tested**: Initial page load and KPI card rendering
- **Screenshot**: perf-login-complete.png
- **Console errors**: 2 profiler CSS MIME type warnings (dev environment only)
- **Notes**: Dashboard loads in <1s with 10 API calls, all returning 200 OK

### Projects

#### Project List Load
- **Status**: PASS
- **Tested**: Project list rendering with seed data (2 projects)
- **Notes**: Loads in <500ms with 1 API call (200 OK)

#### Project Search Performance
- **Status**: PASS
- **Tested**: Client-side search filtering
- **Notes**: Instant response, 0 API calls — filtering happens client-side

#### Project Details Navigation
- **Status**: PASS
- **Tested**: Navigation to project detail view
- **Notes**: <500ms load, 1 API call (200 OK)

### Kanban Board

#### Board View
- **Status**: CANNOT_TEST
- **Tested**: Not yet implemented — shows "Bordweergave komt eraan" placeholder
- **Notes**: Cannot measure performance of unimplemented feature

### Tasks

#### Task CRUD Performance
- **Status**: CANNOT_TEST
- **Tested**: Task management not yet implemented
- **Notes**: Feature shows placeholder

### Time Tracking

#### Timesheet View
- **Status**: CANNOT_TEST
- **Tested**: Not yet implemented
- **Notes**: Feature not accessible

## Admin Settings

### Settings Page Load
- **Status**: PASS
- **Tested**: Admin settings page load and configuration
- **Notes**: <500ms load, 1 API call (200 OK). Note: /settings/admin/planix returns 404 — settings are at /index.php/apps/planix/settings

## Performance Summary

| Feature | Load Time | API Calls | API Status |
|---------|-----------|-----------|------------|
| Dashboard | <1s | 10 | All 200 OK |
| Projects List | <500ms | 1 | 200 OK |
| Project Search | Instant | 0 | N/A (client-side) |
| Project Details | <500ms | 1 | 200 OK |
| Settings | <500ms | 1 | 200 OK |

**Slow requests (>500ms):** None
**Performance failures (>1000ms):** None

## Console Errors Summary
- Pages checked: 5
- Pages with errors: 1 (profiler CSS only)
- Unique errors: 2 profiler stylesheet MIME type warnings (dev environment)

## Network Errors Summary
- Failed requests (4xx/5xx): 1 expected 404 for /settings/admin/planix
