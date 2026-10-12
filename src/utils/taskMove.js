/**
 * Pure helpers for moving a task, or moving and copying a column, to another
 * project (tasks-move-between-projects).
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.1
 */
import { memberOptions } from './taskPeople.js'

/**
 * The id of an OpenRegister object, wherever the response put it.
 *
 * @param {object} object The object.
 * @return {string|undefined}
 */
function idOf(object) {
	return object?.id ?? object?.uuid ?? object?.['@self']?.id
}

/**
 * The projects a task or column can move to: the ones the user is on, minus the current one.
 *
 * @param {Array<object>} projects  The projects the user can see.
 * @param {string}        uid       The current user.
 * @param {string}        currentId The project the item is in now.
 * @return {Array<{id: string, label: string}>}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
 */
export function targetProjects(projects = [], uid = '', currentId = '') {
	return (projects || [])
		.filter((project) => idOf(project) !== String(currentId))
		.filter((project) => memberOptions(project).some((member) => member.id === uid))
		.map((project) => ({ id: idOf(project), label: project.title || project.name || idOf(project) }))
		.sort((a, b) => a.label.localeCompare(b.label))
}

/**
 * The people on a task who are not members of the target project.
 *
 * @param {object} task    The task.
 * @param {object} project The target project.
 * @return {Array<string>}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.2
 */
export function peopleToClear(task, project) {
	const members = new Set(memberOptions(project).map((member) => member.id))
	const people = [task?.assignedTo, ...(task?.sharedWith || [])].filter(Boolean)
	return [...new Set(people.filter((uid) => !members.has(uid)))]
}

/**
 * The PATCH body that moves a task to the target project's backlog.
 *
 * Clears everything that belongs to the old project (column, phase, epic,
 * release) and the people who are not on the target project. A subtask keeps
 * its parent, which moves in the same step.
 *
 * @param {object}  task    The task.
 * @param {object}  project The target project.
 * @param {boolean} subtask Whether the task moves with its parent.
 * @return {object}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.1
 */
export function moveTaskPatch(task, project, subtask = false) {
	const gone = new Set(peopleToClear(task, project))
	const patch = {
		project: idOf(project),
		column: null,
		columnOrder: null,
		phase: null,
		epic: null,
		release: null,
	}
	if (!subtask) {
		patch.parent = null
	}
	if (task?.assignedTo && gone.has(task.assignedTo)) {
		patch.assignedTo = null
	}
	if ((task?.sharedWith || []).some((uid) => gone.has(uid))) {
		patch.sharedWith = task.sharedWith.filter((uid) => !gone.has(uid))
	}
	return patch
}

/**
 * Time booked on another project than the task's current one, per project.
 *
 * @param {Array<object>} entries   The task's time entries.
 * @param {string}        projectId The task's current project.
 * @return {Array<{projectId: string, minutes: number}>}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.3
 */
export function timeLoggedElsewhere(entries = [], projectId = '') {
	const totals = new Map()
	for (const entry of entries || []) {
		const project = String(entry?.project?.id ?? entry?.project ?? '')
		if (project === '' || project === String(projectId)) {
			continue
		}
		totals.set(project, (totals.get(project) || 0) + (Number(entry.duration) || 0))
	}
	return [...totals].map(([id, minutes]) => ({ projectId: id, minutes }))
}

/**
 * The POST body for a copy of a column in the target project, appended as its last column.
 *
 * @param {object}        column        The source column.
 * @param {object}        project       The target project.
 * @param {Array<object>} targetColumns The target project's columns.
 * @return {object}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function columnCopyPayload(column, project, targetColumns = []) {
	const fields = { ...(column || {}) }
	for (const key of ['id', 'uuid', 'project', 'members', 'viewers', 'memberGroups', 'viewerGroups', 'portfolioReaders', '@self']) {
		delete fields[key]
	}
	const last = Math.max(0, ...(targetColumns || []).map((c) => Number(c?.order) || 0))
	return { ...fields, project: idOf(project), order: last + 1 }
}

/**
 * The POST body for a copy of a task in a copied column: open, no people, in the new column.
 *
 * @param {object} task     The source task.
 * @param {object} project  The target project.
 * @param {string} columnId The new column.
 * @return {object}
 *
 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
 */
export function columnTaskCopyPayload(task, project, columnId) {
	return {
		title: task?.title ?? '',
		description: task?.description ?? '',
		priority: task?.priority ?? 'normal',
		status: 'open',
		project: idOf(project),
		column: columnId,
		columnOrder: task?.columnOrder ?? 0,
	}
}
