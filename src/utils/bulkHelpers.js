/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Bulk actions on the backlog list (tasks-search-and-bulk, design D2 and D3):
 * the selection, and the patch each action makes for one task. Labels are
 * added or removed, never replaced, so a bulk action keeps the labels the
 * member did not see.
 *
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.3
 */

/** The bulk actions, in the order the bar offers them. */
export const BULK_ACTIONS = ['status', 'assign', 'priority', 'labelAdd', 'labelRemove']

/** The statuses a task can be given. */
export const BULK_STATUSES = ['open', 'in_progress', 'blocked', 'done', 'cancelled']

/**
 * The selection with one task added or removed.
 *
 * @param {Array<string>} selected The selected task ids.
 * @param {string}        id       The task.
 * @return {Array<string>}
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.1
 */
export function toggleSelection(selected, id) {
	return selected.includes(id) ? selected.filter((other) => other !== id) : [...selected, id]
}

/**
 * Whether every visible row is selected.
 *
 * @param {Array<string>} selected The selected task ids.
 * @param {Array<object>} rows     The visible rows.
 * @return {boolean}
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.1
 */
export function allSelected(selected, rows) {
	return rows.length > 0 && rows.every((row) => selected.includes(row.id))
}

/**
 * "Select all" for the visible rows: selects them all, or clears them when
 * they all are; a selection outside the visible rows is dropped.
 *
 * @param {Array<string>} selected The selected task ids.
 * @param {Array<object>} rows     The visible rows.
 * @return {Array<string>}
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.1
 */
export function toggleAll(selected, rows) {
	return allSelected(selected, rows) ? [] : rows.map((row) => row.id)
}

/**
 * The selection kept to the visible rows, so a task a search or filter hid
 * is never changed by a bulk action the member cannot see it in.
 *
 * @param {Array<string>} selected The selected task ids.
 * @param {Array<object>} rows     The visible rows.
 * @return {Array<string>}
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.1
 */
export function visibleSelection(selected, rows) {
	const visible = new Set(rows.map((row) => row.id))
	return selected.filter((id) => visible.has(id))
}

/**
 * The patch a bulk action makes on one task, or null when it changes nothing.
 *
 * @param {string}      action One of BULK_ACTIONS.
 * @param {string|null} value  The status, user id, priority or label id; '' or null unassigns.
 * @param {object}      task   The task as loaded.
 * @return {object|null}
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.3
 */
export function bulkPatch(action, value, task) {
	const labels = Array.isArray(task?.labels) ? task.labels : []
	switch (action) {
		case 'status':
			return BULK_STATUSES.includes(value) ? { status: value } : null
		case 'assign':
			return { assignedTo: value || null }
		case 'priority':
			return ['low', 'normal', 'high', 'urgent'].includes(value) ? { priority: value } : null
		case 'labelAdd':
			return value && !labels.includes(value) ? { labels: [...labels, value] } : null
		case 'labelRemove':
			return value && labels.includes(value) ? { labels: labels.filter((label) => label !== value) } : null
		default:
			return null
	}
}
