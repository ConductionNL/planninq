/**
 * Pure helpers for task derived UI state.
 *
 * @spec openspec/changes/task-due-date-warning/specs/tasks/spec.md
 */

/**
 * Classify a task's due date for badge rendering.
 *
 * Compares the task's `dueDate` against today (date-only — time is ignored).
 *
 * @param {object} task                The task object — must expose `dueDate`.
 * @param {string|Date|null|undefined} task.dueDate ISO-8601 string, Date, or absent.
 * @param {Date} [now=new Date()]      Optional clock injection for testability.
 * @return {null|'approaching'|'overdue'}
 *         - `null`         when there is no due date.
 *         - `'approaching'` when the due date is today or within the next 2 days.
 *         - `'overdue'`    when the due date is strictly in the past.
 */
export function dueDateStatus(task, now = new Date()) {
	if (task === null || task === undefined) {
		return null
	}
	const raw = task.dueDate
	if (raw === null || raw === undefined || raw === '') {
		return null
	}

	const due = raw instanceof Date ? raw : new Date(raw)
	if (Number.isNaN(due.getTime())) {
		return null
	}

	// Date-only comparison: drop time component on both sides.
	const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate())
	const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

	const msPerDay = 24 * 60 * 60 * 1000
	const diffDays = Math.round((dueDay.getTime() - today.getTime()) / msPerDay)

	if (diffDays < 0) {
		return 'overdue'
	}
	if (diffDays <= 2) {
		return 'approaching'
	}
	return null
}
