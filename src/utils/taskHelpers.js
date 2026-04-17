/**
 * SPDX-License-Identifier: EUPL-1.2
 * Copyright (C) 2026 Conduction B.V.
 *
 * Task utility helper functions.
 *
 * @spec openspec/changes/task-due-date-warning/tasks.md#task-1
 */

/**
 * Compute the due date urgency status of a task.
 *
 * Compares the task's due date to today (ignoring time component) and returns
 * a status string for display in the UI.
 *
 * @param {object} task - Task object with optional `dueDate` field
 * @param {string|null|undefined} task.dueDate - ISO 8601 date string (YYYY-MM-DD format)
 * @return {string|null} `null` if no due date or due date > 2 days away,
 *                       `"approaching"` if due date is within 2 days (0-2 days from now),
 *                       `"overdue"` if due date is in the past
 *
 * @spec openspec/changes/task-due-date-warning/tasks.md#task-1
 */
export function dueDateStatus(task) {
	if (!task || !task.dueDate) {
		return null
	}

	const today = new Date()
	today.setHours(0, 0, 0, 0)

	const dueDate = new Date(task.dueDate)
	dueDate.setHours(0, 0, 0, 0)

	const daysUntilDue = Math.floor((dueDate - today) / (1000 * 60 * 60 * 24))

	if (daysUntilDue < 0) {
		return 'overdue'
	}

	if (daysUntilDue <= 2) {
		return 'approaching'
	}

	return null
}
