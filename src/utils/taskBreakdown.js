/**
 * Subtasks, the checklist, rollups and duplicating a task (tasks-subtasks-checklist).
 *
 * A subtask is an ordinary task in the same project with `parent` set, one
 * level deep. The checklist is an array of `{ id, text, done }` on the task;
 * every change writes the whole array. Every helper here is pure and returns
 * a new list or payload.
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
import { orderFor } from './columnHelpers.js'

/**
 * A fresh checklist item id.
 *
 * @return {string}
 */
function newItemId() {
	return globalThis.crypto?.randomUUID?.() ?? `c${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`
}

/**
 * The checklist with one more unticked item, or the same list for blank text.
 *
 * @param {Array<object>} list  The checklist.
 * @param {string}        text  The item text.
 * @param {Function}      [id]  Makes the id (for tests).
 * @return {Array<object>}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
export function addChecklistItem(list, text, id = newItemId) {
	const trimmed = String(text ?? '').trim()
	if (trimmed === '') {
		return list
	}
	return [...(list || []), { id: id(), text: trimmed, done: false }]
}

/**
 * The checklist with one item ticked or unticked.
 *
 * @param {Array<object>} list The checklist.
 * @param {string}        id   The item.
 * @return {Array<object>}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
export function toggleChecklistItem(list, id) {
	return (list || []).map((item) => item.id === id ? { ...item, done: !item.done } : item)
}

/**
 * The checklist with one item moved a step up (-1) or down (+1); the same list at an edge.
 *
 * @param {Array<object>} list      The checklist.
 * @param {string}        id        The item.
 * @param {number}        direction -1 or +1.
 * @return {Array<object>}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
export function moveChecklistItem(list, id, direction) {
	const from = (list || []).findIndex((item) => item.id === id)
	const to = from + direction
	if (from === -1 || to < 0 || to >= list.length) {
		return list
	}
	const next = [...list]
	const [item] = next.splice(from, 1)
	next.splice(to, 0, item)
	return next
}

/**
 * The checklist without one item.
 *
 * @param {Array<object>} list The checklist.
 * @param {string}        id   The item.
 * @return {Array<object>}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
export function removeChecklistItem(list, id) {
	return (list || []).filter((item) => item.id !== id)
}

/**
 * The card's done count such as "3/5", or empty without a checklist.
 *
 * @param {Array<object>} list The checklist.
 * @return {string}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-3.1
 */
export function checklistCount(list) {
	if (!list?.length) {
		return ''
	}
	return `${list.filter((item) => item.done).length}/${list.length}`
}

/**
 * A new subtask: same project and lane as the parent, at the bottom of that lane.
 *
 * @param {string}        title        The title as typed.
 * @param {object}        parent       The parent task.
 * @param {Array<object>} projectTasks The project's tasks, to place the card.
 * @return {object}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-2.1
 */
export function newSubtask(title, parent, projectTasks = []) {
	const project = parent?.project?.id ?? parent?.project
	const column = parent?.column ?? null
	const lane = column ? (projectTasks || []).filter((task) => task?.column === column) : []
	return {
		title: String(title ?? '').trim(),
		status: 'open',
		priority: 'normal',
		project,
		parent: parent?.id,
		column,
		columnOrder: orderFor(lane),
	}
}

/**
 * How many subtasks are done, cancelled ones left out: `{ done, total }`.
 *
 * @param {Array<object>} children The subtasks.
 * @return {{done: number, total: number}}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-2.1
 */
export function subtaskProgress(children = []) {
	const counted = (children || []).filter((task) => task?.status !== 'cancelled')
	return { done: counted.filter((task) => task.status === 'done').length, total: counted.length }
}

/**
 * The subtasks' summed estimate and logged time, in minutes.
 *
 * @param {Array<object>} children The subtasks.
 * @param {object}        entries  Subtask id to its time entries.
 * @return {{estimate: number, logged: number}}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-4.1
 */
export function subtaskRollup(children = [], entries = {}) {
	let estimate = 0
	let logged = 0
	for (const task of children || []) {
		estimate += Number(task?.estimatedDuration) || 0
		for (const entry of entries?.[task?.id] || []) {
			logged += Number(entry?.duration) || 0
		}
	}
	return { estimate, logged }
}

/**
 * The create payload for a copy of a task: its description, priority, labels,
 * lane and an unticked checklist; no dates, people, time, key or reporter.
 *
 * @param {object} task      The task to copy.
 * @param {string} template  The title with `{title}`, such as the translated "Copy of {title}".
 * @param {string} [parent]  The new parent, for a copied subtask.
 * @return {object}
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.1
 */
export function duplicatePayload(task, template, parent = null) {
	const copy = {
		title: String(template).replace('{title}', task?.title ?? ''),
		status: 'open',
		priority: task?.priority || 'normal',
		project: task?.project?.id ?? task?.project,
	}
	if (task?.description) {
		copy.description = task.description
	}
	if (task?.labels?.length) {
		copy.labels = [...task.labels]
	}
	if (task?.column) {
		copy.column = task.column
		copy.columnOrder = Number(task.columnOrder) || 0
	}
	if (task?.checklist?.length) {
		copy.checklist = task.checklist.map((item) => ({ ...item, done: false }))
	}
	if (parent) {
		copy.parent = parent
	}
	return copy
}
