/**
 * Moving a task to another project (tasks-move-between-projects): which
 * projects a task may move to, who on it is not on the target, which
 * dependency links the move removes, the PATCH a move writes, and the booked
 * time a moved task carries from its earlier project.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.1
 */
import { projectRole } from './projectRole.js'

/**
 * The roles that may hold a task on a project.
 */
const WORKING_ROLES = ['owner', 'manager', 'member']

/**
 * An object's id, from `id` or the OpenRegister `@self` envelope.
 *
 * @param {object|string|null} ref An object or a uuid.
 * @return {string}
 */
export function idOf(ref) {
	if (ref && typeof ref === 'object') {
		return String(ref.id || ref.uuid || ref['@self']?.id || '')
	}
	return String(ref || '')
}

/**
 * The projects a task may move to: every open project the person works on as
 * owner, manager or member (directly or through a group), except the task's
 * own, by title. A portfolio reader's or viewer's projects are not offered.
 *
 * @param {Array<object>} projects The projects the person can see.
 * @param {string} currentProjectId The task's project.
 * @param {string} uid The person.
 * @param {Array<string>} [groupIds] The person's groups.
 * @return {Array<object>}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
 */
export function moveTargets(projects, currentProjectId, uid, groupIds = []) {
	const current = idOf(currentProjectId)
	return (Array.isArray(projects) ? projects : [])
		.filter((project) => idOf(project) && idOf(project) !== current && project.status !== 'archived')
		.filter((project) => WORKING_ROLES.includes(projectRole(project, uid, groupIds)))
		.sort((a, b) => String(a.title || '').localeCompare(String(b.title || '')))
}

/**
 * Whether a person may hold a task on a project: its owner, a manager or a
 * member, named on the project itself.
 *
 * @param {object} project The project.
 * @param {string} uid The person.
 * @return {boolean}
 */
export function worksOn(project, uid) {
	return WORKING_ROLES.includes(projectRole(project, uid, []))
}

/**
 * The people on a task who are not members of the target project, and so are
 * cleared when the task moves there.
 *
 * @param {object} task The task.
 * @param {object} target The target project.
 * @return {Array<string>} Their user ids, the responsible person first.
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.1
 */
export function peopleToClear(task, target) {
	const people = [task?.assignedTo, ...(Array.isArray(task?.sharedWith) ? task.sharedWith : [])]
		.map((uid) => String(uid || '').trim())
		.filter(Boolean)
	return [...new Set(people)].filter((uid) => !worksOn(target, uid))
}

/**
 * The dependency links a move removes: every link that names the task.
 * Links never cross projects, so none survives a move.
 *
 * @param {object} task The task.
 * @param {Array<{id: string, blocker: string, blocked: string}>} edges Every dependency link.
 * @return {Array<object>}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
 */
export function linksToRemove(task, edges) {
	const id = idOf(task)
	return (Array.isArray(edges) ? edges : []).filter((edge) => edge && (edge.blocker === id || edge.blocked === id))
}

/**
 * The PATCH that moves a task to the target project: the target's backlog
 * (no column, no column order), and only people who are members of the target.
 *
 * @param {object} task The task.
 * @param {object} target The target project.
 * @return {object}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.1
 */
export function movePatch(task, target) {
	const cleared = peopleToClear(task, target)
	const patch = { project: idOf(target), column: null, columnOrder: null }
	if (task?.assignedTo && cleared.includes(String(task.assignedTo))) {
		patch.assignedTo = null
	}
	if (Array.isArray(task?.sharedWith) && task.sharedWith.some((uid) => cleared.includes(String(uid)))) {
		patch.sharedWith = task.sharedWith.filter((uid) => !cleared.includes(String(uid)))
	}
	return patch
}

/**
 * The time logged on a task under another project than the task's own, per
 * project, for the "logged under" note.
 *
 * @param {object} task The task.
 * @param {Array<{project?: string, duration?: number, minutes?: number}>} entries The task's time entries.
 * @return {Array<{project: string, minutes: number}>}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.3
 */
export function timeLoggedElsewhere(task, entries) {
	const own = idOf(task?.project)
	const totals = new Map()
	for (const entry of Array.isArray(entries) ? entries : []) {
		const project = idOf(entry?.project)
		if (!project || project === own) {
			continue
		}
		const minutes = Number(entry.duration ?? entry.minutes ?? 0)
		totals.set(project, (totals.get(project) || 0) + (Number.isFinite(minutes) ? minutes : 0))
	}
	return [...totals].map(([project, minutes]) => ({ project, minutes })).filter((row) => row.minutes > 0)
}

/**
 * The projects a column may move or be copied to: every open project the
 * person owns (board columns are managed by the project owner), except the
 * column's own, by title.
 *
 * @param {Array<object>} projects The projects the person can see.
 * @param {string} currentProjectId The column's project.
 * @param {string} uid The person.
 * @param {Array<string>} [groupIds] The person's groups.
 * @return {Array<object>}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function columnTargets(projects, currentProjectId, uid, groupIds = []) {
	return moveTargets(projects, currentProjectId, uid, groupIds).filter((project) => projectRole(project, uid, groupIds) === 'owner')
}

/**
 * The order that puts a column last on a board.
 *
 * @param {Array<{order?: number}>} columns The board's columns.
 * @return {number}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function lastColumnOrder(columns) {
	const orders = (Array.isArray(columns) ? columns : []).map((column) => Number(column?.order)).filter(Number.isFinite)
	return orders.length ? Math.max(...orders) + 1 : 0
}

/**
 * The tasks a column holds, and the subtasks of those tasks that sit
 * elsewhere: the column carries the first, the second follow their parent.
 *
 * @param {object} column The column.
 * @param {Array<object>} tasks The board's tasks.
 * @return {{inColumn: Array<object>, subtasks: Array<object>}}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function columnTasks(column, tasks) {
	const id = idOf(column)
	const all = Array.isArray(tasks) ? tasks : []
	const inColumn = all.filter((task) => idOf(task?.column) === id)
	const parents = new Set(inColumn.map(idOf))
	const subtasks = all.filter((task) => idOf(task?.column) !== id && parents.has(idOf(task?.parent)))
	return { inColumn, subtasks }
}

/**
 * The PATCH that moves a task with its column: the target project, the same
 * column and place in it, and only people who are members of the target.
 *
 * @param {object} task The task.
 * @param {object} target The target project.
 * @param {string} columnId The moved column.
 * @return {object}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function columnTaskPatch(task, target, columnId) {
	return { ...movePatch(task, target), column: columnId, columnOrder: task?.columnOrder ?? null }
}

/**
 * The new column a copy creates on the target board: the column's own
 * settings, appended last. Its automation is not copied, since its rules name
 * the source board's columns.
 *
 * @param {object} column The column.
 * @param {object} target The target project.
 * @param {number} order The order that puts it last.
 * @return {object}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function columnCopy(column, target, order) {
	const copy = { title: column?.title || '', project: idOf(target), order }
	for (const field of ['wipLimit', 'color', 'type', 'status']) {
		if (column?.[field] !== undefined && column[field] !== null) {
			copy[field] = column[field]
		}
	}
	return copy
}

/**
 * A copy of a task for the copied column: its title, description, priority,
 * labels and checklist (nothing ticked), open, with no people, dates or time.
 *
 * @param {object} task The task.
 * @param {object} target The target project.
 * @param {string} columnId The new column.
 * @return {object}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function taskCopy(task, target, columnId) {
	const copy = {
		title: task?.title || '',
		status: 'open',
		project: idOf(target),
		column: columnId,
		columnOrder: task?.columnOrder ?? null,
	}
	for (const field of ['description', 'priority']) {
		if (task?.[field]) {
			copy[field] = task[field]
		}
	}
	if (Array.isArray(task?.labels) && task.labels.length) {
		copy.labels = [...task.labels]
	}
	if (Array.isArray(task?.checklist) && task.checklist.length) {
		copy.checklist = task.checklist.map((item) => ({ ...item, done: false }))
	}
	return copy
}
