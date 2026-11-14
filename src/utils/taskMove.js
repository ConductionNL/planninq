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
