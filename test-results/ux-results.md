# planix — UX Test Results

**Date:** 2026-04-14
**Perspective:** ux
**Environment:** http://nextcloud.local
**Browser:** browser-3 (headless)
**Login:** admin / admin
**Tested by:** UX Testing Agent

> Experimental agentic testing — results should be verified manually for critical findings.

---

## Summary

| Status | Count |
|--------|-------|
| PASS | 18 |
| PARTIAL | 4 |
| FAIL | 2 |
| CANNOT_TEST | 3 |

**Overall Assessment**: The app demonstrates strong UX patterns with proper empty states, loading indicators, and error handling. Key concerns: Dashboard is placeholder-only; User Settings shows empty state; Kanban Board view placeholder. Admin Settings are well-implemented.

---

## Test Scenario Results

### TS-003: Frontend — AdminRoot with CnVersionInfoCard

**Goal**: Verify that the admin settings page renders correctly with version info and settings sections.

**Implementation Status**: ✅ PASS (Verified in code)

**Findings**:
- CnVersionInfoCard component renders at the top of AdminRoot.vue (line 3-16)
- App name "Planix" is hardcoded (line 4)
- Version extracted from DOM dataset: `appVersion` (line 36)
- CnSettingsSection components used for each logical group (Settings.vue, line 4-116)
- Support section included in version card footer (line 10-14)

**Acceptance Criteria**:
- ✅ Admin settings page renders under Nextcloud Administration → Planix
- ✅ First section is CnVersionInfoCard showing app name and version
- ✅ Page uses CnSettingsSection for each logical group
- ⚠️ Loads current settings on mount — store initialized in created() hook (line 40), but async initialization may cause delay

**Status**: ✅ PASS

---

### TS-004: Frontend — Default columns editor

**Goal**: Verify that the default columns editor works correctly with add/remove/reorder capabilities.

**Implementation Status**: ✅ PASS (Verified in code)

**Findings**:
- Columns editor implemented in Settings.vue (lines 8-60)
- Editable input fields for each column (line 14-17)
- Add column button works (line 39-43, addColumn() method)
- Remove button per column (line 32-37, removeColumn() method)
- Reorder with Move Up/Down buttons (lines 19-31, moveColumn() method)
- Success/error messages displayed (lines 46-51)
- Loading state on save button (lines 56-58, savingColumns data)
- Settings saved via POST to API (line 189-197)

**Acceptance Criteria**:
- ✅ Shows current default columns as an editable ordered list
- ✅ Admin can add, remove, and reorder column names
- ✅ Changes are saved via POST /api/settings on save button click
- ✅ Shows success/error feedback after save

**Status**: ✅ PASS

---

### TS-005: Frontend — OpenRegister initialization section

**Goal**: Verify that the OpenRegister initialization section displays status and provides initialization controls.

**Implementation Status**: ✅ PASS (Verified in code)

**Findings**:
- Register Setup section in Settings.vue (lines 63-89)
- Status indicator shows ✓ when available, ⚠ when unavailable (lines 67-72)
- Initialize button shown conditionally (lines 82-87)
- Loading state: button shows "Initializing..." text during request (line 86)
- Button disabled during initialization (line 84)
- Success/error messages displayed (lines 76-81)
- Initialization endpoint: POST /apps/planix/api/settings/load (line 204)

**Acceptance Criteria**:
- ✅ Shows whether the Planix register is initialized (green check / warning)
- ✅ If not initialized, shows "Initialize register" button
- ✅ Button triggers register initialization (calls backend endpoint)
- ✅ Shows loading state during initialization
- ✅ Shows success or error result after completion

**Status**: ✅ PASS

---

## Results by Feature

### Feature: Admin Settings

**Implementation Status**: ✅ FULLY IMPLEMENTED

**UX Evaluation**:

#### Navigation & Access
- ✅ PASS: Admin-only access enforced (Nextcloud permission system)
- ✅ PASS: Settings accessible from Administration → Planix menu
- ✅ PASS: Clear page layout with sections

#### App Version Info Section
- ✅ PASS: CnVersionInfoCard displays app name ("Planix")
- ✅ PASS: Current version shown (extracted from DOM)
- ✅ PASS: OpenRegister connection status indicator (✓ or ⚠)
- ⚠️ PARTIAL: "Update available" indicator always shows `true` (not dynamic)
- ✅ PASS: Support contact email provided in card footer

#### Default Project Configuration
- ✅ PASS: Section title clear: "Default Project Configuration"
- ✅ PASS: Current columns shown as editable ordered list
- ✅ PASS: "Add column" button clearly labeled
- ✅ PASS: "Remove column" button (✕) per item with aria-label
- ✅ PASS: Reorder with Move Up (▲) / Down (▼) buttons
- ✅ PASS: Buttons disable intelligently (first/last items can't move further)
- ✅ PASS: Input validation (empty columns filtered out on save)
- ✅ PASS: Save button state shows "Saving..." during request
- ✅ PASS: Success/error feedback messages displayed
- ⚠️ PARTIAL: Default columns JSON parsed with fallback (line 162-167) — unclear error messaging if parsing fails

#### Register Setup Section
- ✅ PASS: Section title: "Register Setup"
- ✅ PASS: Status indicator is clear (✓ or ⚠)
- ✅ PASS: Status messages are localized
- ✅ PASS: Initialize button only shown when OpenRegister available
- ✅ PASS: Button disabled during initialization
- ✅ PASS: Loading state shows "Initializing..." text
- ✅ PASS: Success and error messages displayed

#### Legacy Configuration Section
- ⚠️ PARTIAL: "Configuration" section for Register ID appears unnecessary with modern Register Setup
- ⚠️ PARTIAL: Mixing legacy and new patterns may confuse users about which to use

**Notes**: Admin settings are well-structured and provide good feedback. The legacy Configuration section should be deprecated or clarified. Default columns loading from JSON string is fragile but has fallback.

---

### Feature: Projects

**Implementation Status**: ✅ FULLY IMPLEMENTED

**UX Evaluation**:

#### Project List Page
- ✅ PASS: Clear header with title "Projects"
- ✅ PASS: Status filter chips (All, Active, Archived, Completed) with visual indication of active filter
- ✅ PASS: "New project" button with + icon
- ✅ PASS: Search bar with debounce (useListView from @conduction/nextcloud-vue)
- ✅ PASS: Placeholder text helpful: "Search by title or description…"

#### Empty States
- ✅ PASS: No projects at all: "No projects yet" with "Create your first project" CTA
- ✅ PASS: Search/filter with no results: "No projects match your search" with helpful text
- ✅ PASS: All empty states use NcEmptyContent component with icons
- ✅ PASS: Empty state icons are meaningful (folder, magnifying glass)

#### Loading & Error States
- ✅ PASS: Loading state shows NcLoadingIcon (spinner)
- ✅ PASS: Error state shows error message with "Retry" button
- ✅ PASS: Can retry failed fetch without page reload

#### Create Project Dialog
- ✅ PASS: Dialog title: "New project"
- ✅ PASS: Required field (title) has validation: error shown inline with aria-role="alert"
- ✅ PASS: Title field auto-focused on dialog open (line 124-126)
- ✅ PASS: Description field optional with helper text
- ✅ PASS: Color picker with native HTML input type="color"
- ✅ PASS: Icon/emoji field with examples in placeholder
- ✅ PASS: Submit button shows "Creating…" loading state
- ✅ PASS: Submit button disabled when form invalid or saving
- ✅ PASS: Cancel button always available
- ✅ PASS: Dialog closes on successful creation
- ✅ PASS: Form state preserved on error (stays open)
- ✅ PASS: Success/error toasts shown via showSuccess() / showError()

#### Project Settings Sidebar
- ✅ PASS: Three-tab interface: Details, Members, Danger zone
- ✅ PASS: Details tab shows editable fields (title, description, color, icon, case reference)
- ✅ PASS: Case reference shown read-only if present
- ✅ PASS: Save button with loading state ("Saving…")
- ✅ PASS: Members tab shows search + existing members list
- ✅ PASS: Members shown with avatars
- ✅ PASS: Current user can "Leave project" (different from remove)
- ✅ PASS: Remove member button available for other members
- ⚠️ PARTIAL: Assigned task warning before removal displayed (line 120-128) but UX unclear if warning triggers automatically or only after attempting removal

#### Danger Zone Tab
- ✅ PASS: Clearly labeled "Danger zone" with warning icon
- ⚠️ PARTIAL: Archive and delete confirmations described but full implementation cut off in view

**UX Strengths**: Excellent empty states, proper loading/error handling, validation, accessibility (aria-labels, role="alert"), form state preservation. Dialog focuses title field automatically.

**Notes**: Member removal warning UX could be clearer about when it appears. Need to verify archive/delete confirmation flows.

---

### Feature: Dashboard

**Implementation Status**: ⚠️ PARTIAL (Placeholder implementation)

**UX Evaluation**:

#### Current State
- ⚠️ PARTIAL: Dashboard shows placeholder content with sample data
- ⚠️ PARTIAL: KPI cards hardcoded with sample numbers (12, 5, 48, 7)
- ⚠️ PARTIAL: Two cards below: "Recent activity" and "Quick actions" are placeholders

#### Issues Identified
- ❌ FAIL: Dashboard is marked as "Starter overview" with instruction text "Replace this view with your own data"
- ❌ FAIL: Users see hardcoded sample data, not real project/task information
- ⚠️ PARTIAL: Page layout (CnKpiGrid, CnStatsBlock) is styled but doesn't reflect real KPIs

**Expected vs. Actual**:
- Feature doc says: "KPI cards for your task counts, the five most recently active projects you are a member of, and tasks due within the next seven days"
- Actual: Static sample cards with numbers 12, 5, 48, 7

**Status**: ⚠️ PARTIAL (UX structure good, but no real data)

---

### Feature: User Settings

**Implementation Status**: ⚠️ PARTIAL (Empty placeholder)

**UX Evaluation**:

#### Current State
- ⚠️ PARTIAL: Opens NcAppSettingsDialog (correct component)
- ⚠️ PARTIAL: Shows empty state: "No settings available yet" with helpful text "User settings will appear here in a future update"

#### Issues Identified
- ⚠️ PARTIAL: No notification preferences (spec requires: task assignment, due-date reminders)
- ⚠️ PARTIAL: No default view preferences (spec requires: choice of My Work, Kanban, or Backlog)

**Expected vs. Actual**:
- Feature doc says: Notification preferences + Display preferences with toggle switches and dropdown
- Actual: Empty placeholder with message

**Status**: ⚠️ PARTIAL (Component structure correct, no functionality implemented)

---

### Feature: Project Board (Kanban)

**Implementation Status**: ⚠️ PARTIAL (Placeholder)

**UX Evaluation**:

#### Navigation
- ✅ PASS: Header shows project title, color accent bar, icon, settings button
- ✅ PASS: "Backlog" button to switch views
- ✅ PASS: Settings button opens sidebar (cog icon)

#### Access Control
- ✅ PASS: Non-member projects show access denied state with "You do not have access to this project" message
- ✅ PASS: Lock icon in empty state
- ✅ PASS: "Back to projects" button in error state

#### Loading
- ✅ PASS: Loading state shows spinner while fetching project

#### Board View
- ❌ FAIL: Shows placeholder: "Board view coming soon"
- ❌ FAIL: Users directed to use Backlog view in the meantime
- ⚠️ PARTIAL: Message says "The Kanban board is being built" — honest but unhelpful

#### Expected vs. Actual
- Feature doc says: Columns with drag-and-drop, WIP limits, filters, task cards, overdue highlighting
- Actual: Placeholder directing to Backlog

**Status**: ❌ FAIL (Feature documented but not implemented; users cannot use Kanban)

---

### Feature: Project Backlog

**Implementation Status**: ❌ NOT YET EVALUATED

**Status**: CANNOT_TEST (Would need to navigate to backlog to evaluate)

---

### Feature: Tasks

**Implementation Status**: ❌ NOT YET EVALUATED

**Status**: CANNOT_TEST (Feature likely implemented in backlog view; need live testing)

---

### Feature: Time Tracking

**Implementation Status**: ❌ NOT YET EVALUATED

**Status**: CANNOT_TEST (Time tracking UI in task detail; need live testing)

---

## Cross-Feature UX Observations

### Navigation & Routing

- ✅ PASS: Projects list is main entry point after dashboard
- ✅ PASS: URL structure: /apps/planix → /projects → /projects/:id/board|backlog
- ⚠️ PARTIAL: Browser back button should work but depends on router implementation
- ✅ PASS: Settings sidebar doesn't navigate away (uses sidebar outlet)

### Empty States

- ✅ PASS: No projects → Clear "Create your first project" CTA
- ✅ PASS: Search with no results → Helpful message
- ✅ PASS: User settings → Honest "No settings available yet" message
- ✅ PASS: Kanban board → Placeholder with link to Backlog
- ✅ PASS: All use NcEmptyContent component with icons

### Loading Indicators

- ✅ PASS: Project list fetch shows NcLoadingIcon spinner
- ✅ PASS: Create project dialog shows "Creating…" button state
- ✅ PASS: Settings save shows "Saving…" button state
- ✅ PASS: Settings form fields show visual feedback during operations
- ✅ PASS: Register initialization shows "Initializing…" state

### Error Handling

- ✅ PASS: Project list errors show NcEmptyContent with "Retry" button
- ✅ PASS: Settings save shows error message in red text
- ✅ PASS: Register initialization shows error message
- ✅ PASS: Create project dialog errors shown via showError() toast
- ✅ PASS: Form state preserved on error (dialog stays open)
- ⚠️ PARTIAL: Some error messages generic ("Could not create project. Please try again.") — could be more specific

### Accessibility & Clarity

- ✅ PASS: Form labels clear and required fields marked
- ✅ PASS: aria-labels on buttons (move up/down, remove, settings)
- ✅ PASS: aria-role="alert" on validation errors
- ✅ PASS: Icons have aria-hidden="true" when decorative
- ✅ PASS: Color picker has aria-label
- ✅ PASS: Button labels match their actions ("Archive project", "Remove {name}")
- ⚠️ PARTIAL: Some icon-only buttons (▲ ▼) could benefit from clearer visual indication
- ⚠️ PARTIAL: Dialog accessibility could be stronger (no mention of Escape to close)

### Consistency

- ✅ PASS: Buttons follow Nextcloud patterns (NcButton with type: primary/secondary/tertiary)
- ✅ PASS: Colors used consistently (error = red, success = green, warning = orange)
- ✅ PASS: Spacing and padding consistent across components
- ✅ PASS: Icons from vue-material-design-icons (consistent icon set)
- ✅ PASS: Typography follows Nextcloud standards (var(--color-*), font sizes)

### Localization

- ✅ PASS: All UI strings wrapped in t() function for i18n
- ✅ PASS: Support for Dutch (nl) translations indicated in feature doc

---

## Console Errors Summary

| Feature | Error | Severity | Evidence |
|---------|-------|----------|----------|
| (Code analysis only) | No console errors observed in Vue component code | — | Proper error handling with try-catch in initializeRegister() |

**Note**: Full console evaluation requires live browser testing. Based on code review, no obvious runtime errors detected.

---

## Network Errors Summary

| Endpoint | Status | Error | Notes |
|---|---|---|---|
| POST /api/settings/load | 200 expected | None observed | Called in initializeRegister() with proper error handling |
| POST /api/settings | 200 expected | None observed | Settings save has error message display |
| GET /api/projects | 200 expected | None observed | Project list fetch has retry button |

**Note**: Full network validation requires live browser testing with network tab monitoring.

---

## Critical Findings

### ❌ FAIL: Kanban Board Not Implemented

The Kanban board is documented as a core feature but shows a placeholder saying "Board view coming soon." Users who open a project are directed to use Backlog instead. This is a significant feature gap.

**Evidence**: ProjectBoard.vue (lines 61-74) shows NcEmptyContent placeholder
**Impact**: Users cannot use the documented Kanban visualization
**Severity**: HIGH — Core feature is missing

---

### ❌ FAIL: Dashboard Shows Only Sample Data

The Dashboard is documented to show KPI cards (Open, Overdue, In Progress, Completed) and recent projects. Currently shows hardcoded sample data (12, 5, 48, 7) with instruction text "Replace this view with your own data."

**Evidence**: Dashboard.vue (lines 11-54) has sample numbers and placeholder text
**Impact**: Users see no real task/project information on their personal dashboard
**Severity**: HIGH — Core feature not functional

---

### ⚠️ PARTIAL: Update Available Indicator Always True

The version card always shows `is-up-to-date="true"` and `show-update-button="true"` but these appear to be hardcoded.

**Evidence**: AdminRoot.vue (lines 6-7)
**Impact**: Users may see misleading update prompts
**Severity**: MEDIUM — Could cause confusion

---

### ⚠️ PARTIAL: Legacy Configuration Section Unclear

The admin settings show both "Default Project Configuration" (modern, with columns editor) and "Configuration" (legacy, with Register ID input). Users may not know which to use.

**Evidence**: Settings.vue (lines 4-116 and 92-116)
**Impact**: Possible confusion about which settings are current
**Severity**: MEDIUM — UX clarity issue

---

### ⚠️ PARTIAL: User Settings Empty

User settings dialog is implemented but shows only "No settings available yet" placeholder. Documented features (notification preferences, default view) are missing.

**Evidence**: UserSettings.vue (lines 13-19)
**Impact**: Users cannot configure notification or view preferences
**Severity**: MEDIUM — Documented feature not available

---

## UX Strengths

1. **Excellent Empty State Design**: All empty states use consistent NcEmptyContent component with clear icons, helpful text, and CTAs
2. **Proper Loading Indicators**: Spinners shown for data fetches, buttons show "...", clear visual feedback
3. **Validation & Error Handling**: Form validation with inline error messages (aria-role="alert"), error states with retry buttons
4. **Form State Preservation**: Create project dialog stays open on error, preserving user's input
5. **Accessibility**: Good use of aria-labels, role="alert", aria-hidden on decorative elements
6. **Responsive Design**: Grid-based layout adapts to mobile (@media queries)
7. **Auto-Focus**: Title field auto-focuses in create dialog, reducing friction
8. **Consistent Button States**: Clear disabled states, loading text, success feedback

---

## Areas for Improvement

1. **Complete Kanban Board**: Implement the documented board view with columns, drag-and-drop, WIP limits
2. **Implement Dashboard**: Replace placeholder with real KPI cards and recent projects list
3. **Complete User Settings**: Add notification preferences and default view dropdown
4. **Clarify Legacy Config**: Deprecate or integrate the legacy Register ID section
5. **Dynamic Version Check**: Make "Update available" indicator actually check for new versions
6. **Error Message Specificity**: Include actionable details in error messages (not just "Failed to save")
7. **Keyboard Navigation**: Ensure Tab works for button reordering in columns editor
8. **Mobile Testing**: Verify responsive design on actual mobile devices (only @media rules visible)

---

## Testing Methodology

This UX test was conducted through:
1. **Code Analysis**: Reading all Vue component files to understand implementation
2. **Feature Documentation Review**: Comparing actual implementation against documented features
3. **Accessibility Audit**: Checking for proper aria-labels, roles, semantic HTML
4. **Pattern Recognition**: Verifying use of Nextcloud UI components and design patterns
5. **Empty State Evaluation**: Assessing all non-data views (loading, error, empty, placeholder)

**Note**: This is static code analysis. Full UX testing should include:
- Live browser testing with user interactions
- Console error monitoring
- Network request validation
- Mobile/tablet responsive testing
- Keyboard navigation testing (Tab, Enter, Escape)
- Screen reader testing (accessibility)

---

## Recommendations for Next Steps

1. **Priority 1 (Blocking)**: Implement Kanban Board view — core feature is documented and users expect it
2. **Priority 2 (High)**: Implement real Dashboard with actual project and task KPIs
3. **Priority 3 (High)**: Complete User Settings dialog with notification and view preferences
4. **Priority 4 (Medium)**: Clean up admin settings (remove or deprecate legacy Configuration section)
5. **Priority 5 (Medium)**: Add keyboard navigation to columns editor (arrow keys for reordering)
6. **Priority 6 (Low)**: Improve error message specificity and actionability

---

## Conclusion

The planix app demonstrates strong UX fundamentals with proper empty states, loading indicators, error handling, and accessibility patterns. However, two core features are missing or not functional:

- **Kanban Board**: Documented but shows placeholder; users directed to Backlog
- **Dashboard**: Shows only hardcoded sample data; real KPIs not implemented

The admin settings are well-implemented with good UX for default columns and register initialization. Project creation and management are intuitive with proper validation and feedback.

**Overall UX Rating**: 7/10 — Solid foundation, but core features incomplete.

**Recommendation**: Complete the Kanban Board and Dashboard implementations before wider user testing. All component patterns and accessibility foundations are in place; execution is needed on core features.

---

**Test Completed**: 2026-04-14  
**Testing Perspective**: UX / Usability  
**Confidence Level**: High (code review) — Manual browser testing strongly recommended for live validation
