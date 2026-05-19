# Planix Functional Test Execution Plan

**Date:** 2026-04-14
**Environment:** http://nextcloud.local
**Browser:** browser-2 (headless via MCP)
**Login:** admin / admin

## Test Scenario Execution Status

### TS-001: Project list renders for member projects
**Status**: Ready to test
**Preconditions**: User logged in, at least 2 projects with user as member, 1 project without
**Steps**:
1. Navigate to http://nextcloud.local/index.php/apps/planix/projects
2. Verify CnListViewLayout renders
3. Verify search bar present
4. Verify status filter chips (Active/Archived/Completed)
5. Verify each project shows: color swatch, icon, title, member count, status badge
6. Verify non-member projects not shown

### TS-002: Search filters projects in real time
**Status**: Ready to test
**Preconditions**: Project list with 3+ distinct projects visible
**Steps**:
1. Type partial title into search bar
2. Verify only matching projects shown
3. Verify no page reload
4. Clear search and verify all projects restored

### TS-003: Filter by status shows only matching projects
**Status**: Ready to test
**Preconditions**: Projects with Active, Archived, Completed statuses exist
**Steps**:
1. Click "Archived" status filter chip
2. Verify only archived projects shown
3. Verify chip shows selected state
4. Clear filter and verify all projects restored

### TS-004: Empty state when user has no projects
**Status**: Cannot test in current session
**Reason**: Would require creating a new user with no project memberships
**Expected behavior**: NcEmptyContent with "No projects yet" and action button

### TS-005: Create project field validation
**Status**: Ready to test
**Preconditions**: Project list with create button visible
**Steps**:
1. Click "New project" button
2. Focus title field and blur without entering value
3. Verify inline error "Title is required"
4. Verify submit button disabled
5. Enter valid title
6. Verify submit button enabled

### TS-006: Create project default columns created on success
**Status**: Ready to test
**Preconditions**: Project list view
**Steps**:
1. Click "New project" button
2. Enter unique title
3. Click "Create"
4. Verify dialog closes
5. Verify navigation to /projects/{newId}
6. Verify 4 default columns exist (To Do, In Progress, Review, Done)
7. Verify user is project member

### TS-008: Project settings sidebar edits reflect immediately
**Status**: Ready to test
**Preconditions**: On /projects/:id for a project user owns
**Steps**:
1. Click gear icon to open settings sidebar
2. Edit project title
3. Click Save
4. Verify title reflected in header immediately
5. Verify no full page reload
6. Verify members list preserved

### TS-009: Danger Zone archive and delete require confirmation
**Status**: Ready to test
**Preconditions**: Settings sidebar open on Danger Zone tab
**Steps**:
1. Verify "Archive project" button visible
2. Verify "Delete project" button visible
3. Click "Archive project" and verify confirmation shown
4. Cancel and verify project intact
5. Click "Delete project" and verify confirmation with task count
6. Cancel and verify project intact

### TS-010: Default column config used when admin setting set
**Status**: Requires admin settings test
**Preconditions**: Must first configure admin default_columns
**Steps**:
1. Navigate to http://nextcloud.local/settings/admin/planix
2. Configure custom default_columns (e.g., Backlog, Active Sprint, Done)
3. Create new project
4. Verify project uses admin-configured columns, not fallback

## Test Execution Notes

This plan outlines the functional test scenarios that need to be executed.
Each scenario requires browser interaction via the MCP browser-2 tools.

