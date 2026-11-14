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

/** The priorities a task can be given. */
const BULK_PRIORITIES = ['low', 'normal', 'high', 'urgent']

/**
 * The labels of a task as loaded, or none.
 *
 * @param {object} task The task as loaded.
 * @return {Array<string>}
 */
function taskLabels(task) {
	return Array.isArray(task?.labels) ? task.labels : []
}

/**
 * One patch builder per bulk action: (value, labels) => patch or null.
 */
const PATCH_BUILDERS = {
	status: (value) => (BULK_STATUSES.includes(value) ? { status: value } : null),
	assign: (value) => ({ assignedTo: value || null }),
	priority: (value) => (BULK_PRIORITIES.includes(value) ? { priority: value } : null),
	labelAdd: (value, labels) => (value && !labels.includes(value) ? { labels: [...labels, value] } : null),
	labelRemove: (value, labels) => (value && labels.includes(value) ? { labels: labels.filter((label) => label !== value) } : null),
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
	const build = Object.hasOwn(PATCH_BUILDERS, action) ? PATCH_BUILDERS[action] : null
	return build ? build(value, taskLabels(task)) : null
}
