/**
 * Backlog helpers (backlog-list): the backlog is the set of a project's tasks
 * that sit in no board column. Rank is data (`columnOrder`, the same field a
 * board lane uses), sort is a view.
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-1.1
 */
import { orderFor } from './columnHelpers.js'

/** The sort choices, the first one the default. */
export const BACKLOG_SORTS = ['rank', 'priority', 'due', 'created']

/** Priority weight, most urgent first. */
const PRIORITY_RANK = { urgent: 0, high: 1, normal: 2, low: 3 }

/**
 * @param {object} task A task.
 * @return {number} Its rank, 0 when unset.
 */
function rank(task) {
	return Number(task?.columnOrder) || 0
}

/**
 * The backlog: tasks without a column, in rank order. Done tasks are finished
 * work and never listed; cancelled ones only when `cancelled` is asked for.
 *
 * @param {Array<object>} tasks                The project's tasks.
 * @param {{cancelled?: boolean}} [options]    Show the cancelled tasks instead.
 * @return {Array<object>}
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-1.1
 */
export function backlogTasks(tasks = [], { cancelled = false } = {}) {
	return (tasks || [])
		.filter((task) => task && !task.column && task.status !== 'done')
		.filter((task) => (task.status === 'cancelled') === cancelled)
		.sort((a, b) => rank(a) - rank(b))
}

/**
 * The backlog in the chosen order. An unknown choice keeps rank order.
 *
 * @param {Array<object>} tasks The backlog tasks.
 * @param {string}        sort  One of BACKLOG_SORTS.
 * @return {Array<object>}
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-2.2
 */
export function sortBacklog(tasks = [], sort = 'rank') {
	const list = [...(tasks || [])]
	const byRank = (a, b) => rank(a) - rank(b)
	const comparators = {
		priority: (a, b) => (PRIORITY_RANK[a.priority ?? 'normal'] ?? 2) - (PRIORITY_RANK[b.priority ?? 'normal'] ?? 2) || byRank(a, b),
		due: (a, b) => {
			if (!a.dueDate || !b.dueDate) {
				return (a.dueDate ? -1 : 0) + (b.dueDate ? 1 : 0) || byRank(a, b)
			}
			return String(a.dueDate).localeCompare(String(b.dueDate)) || byRank(a, b)
		},
		created: (a, b) => {
			const ca = a?.['@self']?.created ?? ''
			const cb = b?.['@self']?.created ?? ''
			if (!ca || !cb) {
				return (ca ? -1 : 0) + (cb ? 1 : 0) || byRank(a, b)
			}
			return new Date(ca) - new Date(cb) || byRank(a, b)
		},
	}
	return list.sort(comparators[sort] || byRank)
}

/**
 * The backlog narrowed to one priority; an empty choice keeps every task.
 * A task without a priority counts as normal, the schema default.
 *
 * @param {Array<object>} tasks     The backlog tasks.
 * @param {{priority?: string}} filters The filter choices.
 * @return {Array<object>}
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-2.3
 */
export function filterBacklog(tasks = [], { priority = '' } = {}) {
	if (!priority) {
		return [...(tasks || [])]
	}
	return (tasks || []).filter((task) => (task.priority || 'normal') === priority)
}

/**
 * The PATCH that takes a card off the board: no column, last in the backlog,
 * back to open.
 *
 * @param {Array<object>} backlog The current backlog.
 * @return {{column: null, columnOrder: number, status: string}}
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-3.1
 */
export function moveToBacklogPatch(backlog = []) {
	return { column: null, columnOrder: orderFor(backlog), status: 'open' }
}

/**
 * A new task for the backlog: no column, last in rank.
 *
 * @param {string}        title     The title as typed.
 * @param {string}        projectId The project UUID.
 * @param {Array<object>} backlog   The current backlog.
 * @return {object}
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-1.2
 */
export function newBacklogTask(title, projectId, backlog = []) {
	return {
		title: String(title ?? '').trim(),
		status: 'open',
		priority: 'normal',
		project: projectId,
		column: null,
		columnOrder: orderFor(backlog),
	}
}
