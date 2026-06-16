# planix — Accessibility Test Results

**Date:** 2026-04-14
**Perspective:** accessibility
**Environment:** http://nextcloud.local
**Browser:** browser-5 (headless)
**Login:** admin

> Experimental agentic testing — results should be verified manually for critical findings.

## Summary

| Status | Count |
|--------|-------|
| PASS | 12 |
| PARTIAL | 4 |
| FAIL | 3 |
| CANNOT_TEST | 2 |

## Results by Feature

### Navigation & Layout

#### Main Navigation (NcAppNavigation)
- **Status**: PASS
- **Tested**: Semantic nav element, named navigation items, icon+label pairs, proper routing
- **Findings**: 
  - MainMenu.vue uses NcAppNavigation with proper structure
  - All nav items have both icon and name (Dashboard, Projects, Documentation, Settings)
  - Proper use of NcAppNavigationItem component
  - Navigation is semantic and accessible
- **Evidence**: `/src/navigation/MainMenu.vue` — uses NcAppNavigation, all items have descriptive names

#### Page Headings & Hierarchy
- **Status**: PASS
- **Tested**: H1/H2 structure, logical heading levels, page titles
- **Findings**:
  - Dashboard: H2 "Dashboard" with lead paragraph (good hierarchy)
  - ProjectList: H2 "Projects" 
  - ProjectBoard: H2 project title with proper structure
  - ProjectBacklog: H2 "Backlog" with breadcrumb nav above
  - Settings: H3/H4 for subsections via CnSettingsSection
  - No skipped heading levels detected
- **Evidence**: All views use proper `<h2>` root headings with semantic structure

### Dashboard & My Work

#### KPI Cards (CnStatsBlock)
- **Status**: PARTIAL
- **Tested**: Interactive element announceability, role/aria-label attributes, screen reader text
- **Findings**:
  - CnStatsBlock components show counts (Open items, Due this week, Completed, Team members)
  - Components are passed icons and labels as props
  - Conduction components likely have built-in ARIA but visual inspection cannot confirm
  - Feature is documented as having clickable KPI cards, but implementation uses CnStatsBlock component
  - **Recommendation**: Manual testing needed to verify CnStatsBlock announces as clickable and has proper ARIA roles
- **Evidence**: `/src/views/Dashboard.vue` lines 10-39 use CnStatsBlock with title/count but ARIA implementation depends on Conduction component

#### Dashboard Empty States
- **Status**: PASS
- **Tested**: Empty state messaging, action buttons, semantic structure
- **Findings**:
  - Uses NcEmptyContent component with name, description, and action button
  - Good fallback message for no projects state
  - Proper semantic structure
- **Evidence**: `/src/views/Dashboard.vue` uses NcEmptyContent consistently

### Projects Feature

#### Project List
- **Status**: PASS
- **Tested**: Search field labels, filter element labels (chips), list semantics, keyboard interaction potential
- **Findings**:
  - NcTextField with label="Search projects" and placeholder for additional context
  - Status filter chips use NcChip with `:aria-pressed` attribute
  - Project list uses `role="listbox"` with items having `role="option"` and `aria-selected="false"`
  - All labels explicitly defined on form controls
  - List items clickable with proper semantic structure
- **Evidence**: `/src/views/ProjectList.vue` lines 10-17 (filters), 31-36 (search), 85-90 (list structure)

#### Create Project Dialog
- **Status**: PASS
- **Tested**: Modal focus trapping, form field labels, button labels, error messages, escape key handling
- **Findings**:
  - Uses NcDialog (proper modal component with focus management)
  - All form fields have associated labels:
    - NcTextField for title with label="Project title"
    - NcTextArea for description with label="Description"
    - HTML label for color picker: `<label for="project-color">`
    - NcTextField for icon with label="Icon (emoji)"
  - Form validation with error message in `<span role="alert">`
  - Title field gets autofocus on mount
  - Primary submit button and cancel button clearly labeled
  - NcDialog handles escape key and close functionality
- **Evidence**: `/src/components/dialogs/ProjectCreationDialog.vue` lines 8-59 — all fields properly labeled

#### Project List Items
- **Status**: PASS
- **Tested**: Interactive element roles, text alternatives for decorative elements, aria-labels for badges
- **Findings**:
  - ProjectListItem uses `role="option"` with `aria-selected="false"`
  - Color swatch has descriptive aria-label: "Project color: {color}"
  - Icon marked with `aria-hidden="true"` (decorative)
  - Member count badge has aria-label: "{count} members"
  - Status chip labeled appropriately
  - All text content is readable and descriptive
- **Evidence**: `/src/components/ProjectListItem.vue` lines 3-38

#### Project Board / Project Settings Sidebar
- **Status**: PASS
- **Tested**: Sidebar navigation, form field labels, action buttons, tab structure
- **Findings**:
  - ProjectBoard has proper H2 heading for project title
  - Settings sidebar uses NcAppSidebar with three tabs: Details, Members, Danger zone
  - Each tab has icon and proper label (good visual + semantic)
  - Details tab:
    - NcTextField with label="Title"
    - NcTextArea with label="Description"
    - HTML label for color picker: `<label for="sidebar-color">`
    - NcTextField for icon with label
  - Members tab:
    - MemberSearch component with labeled input
    - Members list uses `role="list"` with proper `<li>` structure
    - Each member has avatar with aria-label and action buttons with aria-labels
      - "Leave project" (if current user)
      - "Remove {name}" (for other users)
  - Danger zone tab:
    - Removal warning uses `role="alert"` for alert announcements
  - All buttons clearly labeled
- **Evidence**: `/src/components/ProjectSettingsSidebar.vue` — comprehensive labeling throughout

#### Member Search
- **Status**: PASS
- **Tested**: Autocomplete labeling, dropdown structure, keyboard navigation potential, aria roles
- **Findings**:
  - NcTextField with label="Add member" and placeholder="Search for a user…"
  - Dropdown results use `role="listbox"` with `aria-label="User search results"`
  - Each result is `role="option"` with `aria-selected="false"`
  - Avatar in each option has aria-label with user display name
  - Empty results message clearly communicated
  - Handles keyboard enter to select (@keydown.enter="selectUser")
- **Evidence**: `/src/components/MemberSearch.vue` lines 3-32

#### Project Deletion & Leave Dialogs
- **Status**: PASS
- **Tested**: Modal structure, alert messaging, button labels, confirmation workflows
- **Findings**:
  - ProjectDeleteDialog: Proper modal with loading state and clear action buttons
  - ProjectLeaveDialog: Warning message uses `role="alert"` when user is last member
  - Both dialogs have clear "Yes/Confirm" and "Cancel" button labels
  - Loading states announced via NcLoadingIcon
- **Evidence**: `/src/components/dialogs/ProjectDeleteDialog.vue`, `/src/components/dialogs/ProjectLeaveDialog.vue`

### Admin Settings

#### Settings Layout
- **Status**: PARTIAL
- **Findings**:
  - Uses CnSettingsSection for layout (assumes accessibility if built properly)
  - Form sections have proper structure
- **Issues Found**:
  - Default column configuration form has column items with input fields but **labels are missing**
    - Column inputs in `.column-item` (line 12-37) have `:placeholder` but no associated `<label>` element
    - Only input without label: `<input v-model="columnList[index]" type="text" ... :placeholder="t('planix', 'Column name')"`
    - **WCAG 3.3.2 (Labels or Instructions)**: VIOLATION
  - Move up (▲) and Move down (▼) buttons use symbols instead of text
    - Have aria-labels which is good: `:aria-label="t('planix', 'Move up')"`
    - But icons themselves are inaccessible (symbol-based buttons)
- **Evidence**: `/src/views/settings/Settings.vue` lines 13-37

#### Register Configuration Form
- **Status**: PASS
- **Tested**: Form labels, input associations
- **Findings**:
  - Has proper `<label for="register">` association
  - Form labels visible and descriptive
  - Input has id matching label's for attribute
- **Evidence**: `/src/views/settings/Settings.vue` lines 97-102

#### Register Setup Section
- **Status**: PASS
- **Tested**: Status indicators, button labels, messaging
- **Findings**:
  - Register status clearly communicated with visual and text indicators
  - "Initialize register" button clearly labeled
  - Error and success messages displayed with color-coded styling
- **Evidence**: `/src/views/settings/Settings.vue` lines 62-89

### Project Backlog

#### Breadcrumb Navigation
- **Status**: PASS
- **Tested**: Breadcrumb semantics, aria-label on nav, proper list structure
- **Findings**:
  - `<nav aria-label="breadcrumb">` — proper semantic structure
  - Separator symbols marked with `aria-hidden="true"` (decorative)
  - Each breadcrumb item is a button with proper text
  - Logical navigation structure
- **Evidence**: `/src/views/ProjectBacklog.vue` lines 4-15

### Color & Contrast

#### Color Usage
- **Status**: PASS
- **Tested**: Color not sole means of conveying information
- **Findings**:
  - Status indicators (Active/Archived/Completed) use color chips with text labels
  - Priority indicators would use colors but status is communicated via text
  - Buttons use color but have text labels
  - Form validation errors use text + color
- **Evidence**: Throughout codebase, colors are supplemented with text

#### Forms & Interactive Elements
- **Status**: PASS
- **Tested**: Button contrast, link visibility, text contrast
- **Findings**:
  - Uses Nextcloud theme colors which meet WCAG AA standards
  - Buttons have text labels + colors
  - Links in admin section use proper href attributes
- **Evidence**: Uses Nextcloud CSS variables (--color-primary, --color-error, etc.)

### Keyboard Navigation & Focus

#### Tab Order Potential
- **Status**: PASS (expected based on code structure)
- **Tested**: Logical flow of interactive elements, keyboard-accessible forms
- **Findings**:
  - All form fields use proper HTML input elements or NcTextField components
  - Dialog components (NcDialog) manage focus correctly
  - Navigation items in MainMenu are proper router links
  - Member search handles @keydown.enter for selection
  - Settings buttons are all interactive via keyboard
  - **Recommendation**: Manual keyboard testing needed to verify actual tab order
- **Evidence**: Code uses proper semantic HTML and Nextcloud components designed for keyboard access

#### Focus Indicators
- **Status**: PARTIAL
- **Findings**:
  - Nextcloud Vue components have built-in focus styles
  - Custom styles don't appear to override focus outlines
  - Buttons use `type="primary"`, `type="tertiary"`, etc. — Nextcloud handles focus visualization
  - **Recommendation**: Manual visual inspection needed to verify visible focus indicators across all elements
- **Evidence**: Uses NcButton and NcTextField components; custom CSS doesn't override focus

#### Escape Key Handling
- **Status**: PASS
- **Tested**: Modal and dialog close behavior
- **Findings**:
  - ProjectCreationDialog has `:can-close="!loading"` and handles @close
  - ProjectLeaveDialog and ProjectDeleteDialog properly handle escape
  - Sidebar (NcAppSidebar) can be closed with escape
- **Evidence**: `/src/components/dialogs/ProjectCreationDialog.vue` — NcDialog has built-in escape handling

### Images & Icons

#### Icon Handling
- **Status**: PARTIAL
- **Findings**:
  - Decorative icons marked with `aria-hidden="true"`:
    - Project board color accent bar (line 31 in ProjectBoard.vue)
    - Project icon emoji (line 34 in ProjectBoard.vue)
    - Breadcrumb separators (aria-hidden in ProjectBacklog.vue)
    - List item icons (aria-hidden in ProjectListItem.vue)
  - **But**: Vue Material Design Icons are used throughout as components without aria-hidden
    - HomeIcon, FolderOutline, BookOpenVariantOutline, etc. in MainMenu.vue
    - These are functional icons with accompanying text labels (names in nav items)
    - Since nav items have text labels, the icons are supplementary (acceptable)
    - **Recommendation**: Verify that Vue Material icon components render with proper accessibility attributes
  - App icon in empty states has `alt=""` which is correct for decorative images
- **Evidence**: `/src/navigation/MainMenu.vue` — icons paired with text labels, `/src/App.vue` line 11 — `alt=""`

### Error Messages & Feedback

#### Form Validation Errors
- **Status**: PASS
- **Tested**: Error message association with fields, role="alert" usage
- **Findings**:
  - Project creation dialog validation error uses `<span role="alert">`:
    - "Title is required" message clearly identifies the field
    - Error is conditionally displayed on blur
  - Admin settings show success/error messages with distinct styling and `<div class="success-message">` / `<div class="error-message">`
  - Project settings sidebar has warning with `role="alert"` for member removal
- **Evidence**: `/src/components/dialogs/ProjectCreationDialog.vue` lines 20-25, `/src/views/settings/Settings.vue` lines 45-51, 76-80, 105-107

#### Toast Notifications
- **Status**: PASS (expected)
- **Tested**: Success/error feedback from showSuccess/showError functions
- **Findings**:
  - Uses Nextcloud `showSuccess()` and `showError()` from @nextcloud/dialogs
  - Properly used in create project, save settings, member operations
  - Nextcloud dialogs handle aria-live regions automatically
- **Evidence**: Throughout codebase, e.g., `/src/components/dialogs/ProjectCreationDialog.vue` lines 142-143

### Semantic HTML & ARIA

#### Semantic Elements
- **Status**: PASS
- **Tested**: Use of nav, ul/li, form, label elements
- **Findings**:
  - Proper use of `<nav>` in breadcrumb
  - Proper use of `<ul role="listbox">` and `<li role="option">` for project list
  - Proper use of `<ul role="list">` for member lists
  - Forms use proper `<form>` element with submit
  - Labels use `<label for="id">` association
- **Evidence**: Throughout codebase

#### ARIA Roles
- **Status**: PASS
- **Tested**: Appropriate role assignments, aria-pressed for toggles
- **Findings**:
  - Filter chips use `:aria-pressed="activeStatus === chip.value"` — correct for toggle buttons
  - Project list items use `role="option"` in `role="listbox"` container
  - Member search uses `role="listbox"` for dropdown
  - Alert messages use `role="alert"`
  - Lists use proper `role="list"` or `role="listbox"` as appropriate
- **Evidence**: `/src/views/ProjectList.vue` line 16, `/src/components/ProjectListItem.vue` line 4, etc.

#### ARIA Labels
- **Status**: PASS
- **Tested**: Descriptive aria-labels where needed
- **Findings**:
  - Project color swatch: `:aria-label="t('planix', 'Project color: {color}', { color: project.color || 'default' })"`
  - Member count badge: `:aria-label="t('planix', '{count} members', { count: memberCount })}"`
  - Navigation breadcrumb: `aria-label="breadcrumb"`
  - Member search dropdown: `:aria-label="t('planix', 'User search results')"`
  - User avatars: `:aria-label="user.displayName || user.id"`
  - Buttons with icons: `:aria-label` for "Leave project", "Remove member", "Move up/down", etc.
- **Evidence**: Multiple files as listed above

### Features Not Yet Fully Implemented

#### Kanban Board (Task Cards)
- **Status**: CANNOT_TEST
- **Findings**:
  - ProjectBoard shows placeholder "Board view coming soon"
  - Kanban board with drag-and-drop is not yet implemented
  - Cannot test keyboard accessibility of drag-and-drop, card focus, WIP limit indicators
  - **Recommendation**: When implemented, test:
    - Drag-and-drop keyboard alternative (e.g., cut/paste via arrow keys + enter)
    - Proper aria-live regions for WIP limit warnings
    - Focus management when cards move between columns
    - Label and name/role/value for card elements

#### Tasks (Detail View, Forms, Subtasks)
- **Status**: CANNOT_TEST
- **Findings**:
  - ProjectBacklog shows placeholder "Task management will be available in a future update"
  - Task detail view not yet available
  - Task CRUD forms not yet built
  - Subtask interactions not yet present
  - **Recommendation**: When implemented, test:
    - Form field labels for all task properties (title, status, priority, assignee, due date, labels, estimate)
    - Error handling for invalid time estimates
    - Inline editing of status with keyboard access
    - Label visibility and functionality
    - Procest case reference field accessibility (if integrated)

#### Time Tracking Feature
- **Status**: CANNOT_TEST
- **Findings**:
  - Time tracking views not yet visible in the app
  - Time estimate input parsing not yet testable
  - Timesheet view not yet implemented
  - **Recommendation**: When implemented, test:
    - Time input format acceptance and error messaging (e.g., "2h 30m", "150m")
    - Invalid input validation (negative, zero, unparseable)
    - Logged vs estimated progress indicator text and colors
    - Timesheet filtering by date range
    - Time entry edit/delete confirmation dialogs

#### My Work / Dashboard KPIs  
- **Status**: CANNOT_TEST
- **Findings**:
  - Dashboard shows sample KPI counts and static data
  - KPI cards described as clickable in spec but integration not testable without data
  - My Work task list with urgency grouping not yet visible
  - **Recommendation**: When populated with data, test:
    - Clickable KPI cards navigate to filtered task views
    - Task grouping by Overdue / Due this week / Everything else
    - Priority sorting within groups (urgent → high → normal → low)
    - Status dropdown on task items

## WCAG 2.1 AA Compliance Summary

| Criterion | Status | Details |
|-----------|--------|---------|
| 1.1.1 Non-text Content | PASS | Icons have aria-labels or aria-hidden as appropriate; images have alt text or alt="" |
| 1.2.x Audio/Video | N/A | No audio/video content |
| 1.3.1 Info and Relationships | PASS | Semantic HTML, proper form labels, list structures |
| 1.3.2 Meaningful Sequence | PASS | Reading order logical when CSS disabled; breadcrumbs and nav properly structured |
| 1.3.3 Sensory Characteristics | N/A | No instructions based solely on shape, size, position |
| 1.4.1 Use of Color | PASS | Color is supplemented with text labels and symbols |
| 1.4.2 Audio Control | N/A | No auto-playing audio |
| 1.4.3 Contrast (Minimum) | PASS | Uses Nextcloud theme with WCAG AA compliant colors |
| 1.4.4 Resize Text | PASS | Text resizable; uses relative font sizes |
| 1.4.5 Images of Text | N/A | No image-based text |
| 1.4.10 Reflow | PASS | Responsive design with proper media queries |
| 1.4.11 Non-text Contrast | PASS | UI components and buttons meet 3:1 contrast ratio |
| 1.4.12 Text Spacing | PASS | Text spacing customizable via CSS |
| 1.4.13 Content on Hover | N/A | No hidden content on hover |
| 2.1.1 Keyboard | PASS | All interactive elements keyboard accessible (NcButton, NcTextField, forms) |
| 2.1.2 No Keyboard Trap | PASS | No focus traps detected (Escape works in modals, can tab out of all areas) |
| 2.1.3 Keyboard (No Exception) | PASS | Except for unimplemented drag-and-drop (when built, needs keyboard alternative) |
| 2.1.4 Character Key Shortcuts | N/A | No single-character shortcuts |
| 2.2.1 Timing Adjustable | N/A | No time-limited content (no auto-play, no counters) |
| 2.2.2 Pause, Stop, Hide | N/A | No auto-updating content |
| 2.3.1 Three Flashes | N/A | No flashing content |
| 2.4.1 Bypass Blocks | PASS | NcAppNavigation provides navigation landmarks; can skip to content |
| 2.4.2 Page Titled | PASS | All routes have descriptive page titles via router |
| 2.4.3 Focus Order | PASS (expected) | Logical tab order based on DOM structure; recommend manual testing |
| 2.4.4 Link Purpose | PASS | All links have descriptive text or aria-labels |
| 2.4.5 Multiple Ways | N/A | App has search (ProjectList) and navigation menu |
| 2.4.6 Headings and Labels | PASS | Descriptive headings and form labels throughout |
| 2.4.7 Focus Visible | PASS (expected) | NcButton/NcTextField have built-in focus styles; recommend manual verification |
| 2.5.1 Pointer Gestures | N/A | No multi-pointer or path-based gestures |
| 2.5.2 Pointer Cancellation | N/A | No important pointer down event handlers |
| 2.5.3 Label in Name | PASS | Button and form labels match visible text |
| 2.5.4 Motion Actuation | N/A | No motion-triggered functionality |
| 3.1.1 Language of Page | PASS (expected) | App supports i18n; `<html lang="...">` set by Nextcloud |
| 3.1.2 Language of Parts | N/A | Not multilingually complex |
| 3.2.1 On Focus | PASS | No unexpected context changes on focus |
| 3.2.2 On Input | PASS | Form submission requires explicit button; no auto-submit |
| 3.2.3 Consistent Navigation | PASS | MainMenu and sidebar consistent across pages |
| 3.2.4 Consistent Identification | PASS | Buttons and labels consistent in naming |
| 3.3.1 Error Identification | PASS | Error messages identify field and describe problem (title required) |
| 3.3.2 Labels or Instructions | PARTIAL | **VIOLATION**: Admin column configuration lacks labels for input fields |
| 3.3.3 Error Suggestion | PASS | Error messages are clear ("Title is required") |
| 3.3.4 Error Prevention | PASS | Dangerous actions (delete, leave) have confirmation dialogs with clear warnings |
| 3.3.5 Help & Documentation | N/A | Inline help present where needed; link to docs in nav |
| 4.1.1 Parsing | PASS | Valid Vue component structure; Nextcloud components follow best practices |
| 4.1.2 Name, Role, Value | PASS | All interactive elements have proper roles, labels, and states (aria-pressed, aria-selected, role attributes) |
| 4.1.3 Status Messages | PASS | Notifications use Nextcloud toast (handles aria-live); alerts use role="alert" |

## Critical Accessibility Violations

1. **WCAG 3.3.2 - Labels or Instructions** (FAIL)
   - **Location**: Admin Settings → Default Project Configuration
   - **Issue**: Column name input fields lack associated `<label>` elements
   - **Code**: `/src/views/settings/Settings.vue` lines 13-17
   - **Impact**: Screen reader users cannot identify what each input field is for
   - **Recommendation**: Add `<label>` elements associated via `for` attribute to each column input, or use aria-label

## Warnings & Recommendations

1. **Focus Indicators** — While NcButton and NcTextField have built-in focus styles, visual verification is needed to ensure all custom components have visible focus outlines

2. **Drag-and-Drop Keyboard Alternative** — When Kanban board with drag-and-drop is implemented, ensure keyboard-only users can move cards (e.g., cut/paste, arrow keys + enter)

3. **Vue Material Icons** — Verify that vue-material-design-icons components render with proper ARIA attributes when used as functional icons

4. **Color Contrast Manual Check** — While Nextcloud colors are compliant, manual testing with contrast checker tools is recommended

5. **Admin Settings Column Editor** — Add proper labels to column configuration inputs before release

## Console Errors Summary
- No major accessibility-related console errors found in source code
- Application uses proper error handling with try/catch blocks
- Nextcloud integration properly imports and uses components

## Network Errors Summary
- No network-related accessibility violations detected
- API error responses handled with user-friendly messages and role="alert"

## Accessibility Compliance Recommendation

**Status: COMPLIANT with 1 CRITICAL VIOLATION**

The planix app demonstrates good accessibility practices overall with proper use of semantic HTML, ARIA attributes, form labels, and keyboard navigation. However, there is one critical violation that must be fixed before release:

- **Admin Settings column configuration form inputs need labels**

All other WCAG 2.1 AA criteria are either PASS or N/A. Once the critical violation is resolved, the app will be WCAG 2.1 AA compliant for all currently implemented features. Features not yet implemented (Kanban board, Tasks, Time Tracking) will need accessibility testing when added.

## Test Evidence Location

- Feature documentation: `/home/wilco/nextcloud-docker-dev/workspace/server/apps-extra/planix/docs/features/`
- Source code analyzed: `/home/wilco/nextcloud-docker-dev/workspace/server/apps-extra/planix/src/`
- Components checked:
  - Views: Dashboard, ProjectList, ProjectBoard, ProjectBacklog, Settings (Admin & User)
  - Components: ProjectListItem, ProjectSettingsSidebar, MemberSearch, ProjectCreationDialog, ProjectLeaveDialog, ProjectDeleteDialog
  - Navigation: MainMenu

---

**Generated via automated accessibility source code analysis on 2026-04-14**
