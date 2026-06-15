# Discovery: Task Due Date Warning

## Question

Which existing component should be used for the due date badge on task cards, and how should date comparison handle timezone and time-of-day edge cases?

## Approach Taken

- Reviewed `@nextcloud/vue` component library for chip/badge components
- Checked existing TaskCard.vue for current card anatomy and layout patterns
- Reviewed ARCHITECTURE.md for the `dueDate` field definition (type: `date`, maps to iCalendar `DUE`)
- Checked FEATURES.md for the MVP feature "Overdue task highlight (red border/badge on card)"

## Findings

1. **Component choice**: `@nextcloud/vue` provides `NcChip` — a small colored label component suitable for inline badges. It supports custom background color and text. This is the right fit for a compact "Due soon" / "Overdue" indicator on a task card.

2. **Date field format**: The `dueDate` field on the Task entity is a `date` type (not `datetime`). This simplifies comparison — no timezone issues. Compare date-only strings or use `new Date()` with date-only values (midnight local time).

3. **Threshold**: FEATURES.md lists "Overdue task highlight (red border/badge on card)" as an MVP feature. The 2-day "approaching" threshold aligns with the notification feature "task due date approaching (1 day before)" — using 2 days for the visual warning gives users more lead time than the notification.

4. **Existing patterns**: The kanban board spec already mentions "due date" as part of the task card anatomy. Adding a badge is additive — it doesn't change the existing card layout, just adds a visual indicator next to the due date.

## Recommendation

Use `NcChip` from `@nextcloud/vue` for the badge. Perform date-only comparison (strip time component) using `new Date(dueDate).setHours(0,0,0,0)` vs `new Date().setHours(0,0,0,0)`. This avoids timezone drift and matches the `date` (not `datetime`) type of the field.

## Next Steps

Proceed to specs and implementation. No remaining uncertainty — the approach is straightforward.
