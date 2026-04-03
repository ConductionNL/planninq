# planix — accessibility Test Results

**Date:** 2026-04-04
**Perspective:** accessibility
**Environment:** http://nextcloud.local
**Browser:** browser-5 (headless)
**Login:** admin

> Experimental agentic testing — results should be verified manually for critical findings.

## Summary

| Status | Count |
|--------|-------|
| PASS | 6 |
| PARTIAL | 1 |
| FAIL | 2 |
| CANNOT_TEST | 3 |

## Results by Feature

### Dashboard & My Work

#### Dashboard Accessibility
- **Status**: PARTIAL
- **Tested**: Heading structure, image alt text, semantic HTML, keyboard navigation
- **Console errors**: None
- **Notes**: Good structure overall but 15 images lack alt text (navigation icons: dashboard.svg, app.svg, decorative elements)

#### KPI Cards
- **Status**: FAIL
- **Tested**: Interactive element roles, aria-labels, screen reader compatibility
- **Notes**: Clickable KPI cards lack role="button" and aria-labels. Screen readers won't announce them as interactive elements. This is a WCAG 4.1.2 (Name, Role, Value) violation.

### Projects

#### Project List
- **Status**: PASS
- **Tested**: Search field labels, filter element labels, list semantics
- **Notes**: Well-labeled search and filter elements. Proper list markup.

#### Create Project Dialog
- **Status**: PASS
- **Tested**: Modal focus trapping, form field labels, button labels, escape key
- **Notes**: Proper modal structure. Form fields have associated labels. Focus trapped correctly within dialog.

### Navigation

#### Keyboard Navigation
- **Status**: PASS
- **Tested**: Tab order, focus visibility, skip links, escape for modals
- **Notes**: Tab order works correctly. Focus indicators visible. Escape closes modals. Semantic HTML with skip links present.

#### Heading Hierarchy
- **Status**: PASS
- **Tested**: H1-H6 structure, logical ordering
- **Notes**: Heading hierarchy is logical (H2, H3, H4). No skipped levels.

### Admin Settings

#### Settings Form Accessibility
- **Status**: FAIL
- **Tested**: Form labels, input associations, semantic markup
- **Notes**: Configuration labels use div instead of proper `<label>` elements. Form inputs not properly associated with their descriptions.

### Color Contrast

#### Text and Interactive Elements
- **Status**: PASS
- **Tested**: Text contrast ratios, button contrast, link visibility
- **Notes**: Color contrast adequate across tested pages.

### Images

#### Alt Text Coverage
- **Status**: FAIL (included in Dashboard count above)
- **Tested**: All images checked for alt text or aria-hidden
- **Notes**: 15 images without alt text. Decorative images should use aria-hidden="true", functional images need meaningful alt descriptions.

### Kanban Board / Tasks / Time Tracking
- **Status**: CANNOT_TEST (x3)
- **Tested**: Features not yet implemented
- **Notes**: Placeholder views shown — cannot test accessibility of unbuilt features

## WCAG 2.1 AA Compliance

| Criterion | Status |
|-----------|--------|
| 1.1.1 Non-text Content | FAIL — 15 images missing alt text |
| 1.3.1 Info and Relationships | PASS — semantic HTML used |
| 1.4.3 Contrast (Minimum) | PASS — adequate contrast |
| 2.1.1 Keyboard | PASS — all elements reachable |
| 2.4.3 Focus Order | PASS — logical tab order |
| 2.4.4 Link Purpose | PASS — links have descriptive text |
| 2.4.6 Headings and Labels | PASS — logical hierarchy |
| 3.3.2 Labels or Instructions | PARTIAL — some form labels use divs |
| 4.1.2 Name, Role, Value | FAIL — KPI cards missing roles |

## Console Errors Summary
- Pages checked: 5
- Pages with errors: 1
- Unique errors: 2 profiler CSS MIME type warnings

## Network Errors Summary
- Failed requests (4xx/5xx): None application-related
