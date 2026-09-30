import { matchesFilter } from './boardFilter.js'
/**
 * Cross-project views (boards-cross-project-board): a saved selection of
 * projects whose tasks are shown together in status lanes.
 *
 * A view stores a title, its owner, the people it is shared with and up to
 * twenty project ids; never columns, card order or tasks. Tasks are read and
 * written with the viewer's own rights, and a move on the view goes through
 * the task's own project columns, so every project keeps its one board.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
 */
import { buildMovePatch, mappedStatus, sortColumns } from './columnHelpers.js'
import { BOARD_STATUSES, groupTasksByStatus } from './taskHelpers.js'

/** The most projects one view shows. */
export const MAX_VIEW_PROJECTS = 20

/**
 * @param {object} object An object from the register.
 * @return {string|undefined}
 */
function idOf(object) {
	return object?.id ?? object?.uuid ?? object?.['@self']?.id
}

/**
 * Distinct non-empty strings, in their first order.
 *
 * @param {Array} values Anything.
 * @return {string[]}
 */
function distinct(values) {
	return [...new Set((Array.isArray(values) ? values : []).map((value) => String(value ?? '').trim()).filter((value) => value !== ''))]
}

/**
 * The body saved for a view: a trimmed title, distinct projects and people.
 *
 * @param {object} draft The dialog's values.
 * @param {string} draft.title    The name.
 * @param {Array}  draft.projects Project ids.
 * @param {Array}  draft.members  User ids the view is shared with.
 * @return {{title: string, projects: string[], members: string[]}}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
 */
export function viewPayload({ title, projects, members } = {}) {
	return {
		title: String(title ?? '').trim(),
		projects: distinct(projects),
		members: distinct(members),
	}
}

/**
 * What stops a view from being saved: `title`, `noProjects`, `tooManyProjects`.
 *
 * @param {object} payload A viewPayload() result.
 * @return {string[]}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
 */
export function viewProblems(payload) {
	const problems = []
	if (!payload?.title) {
		problems.push('title')
	}
	const count = payload?.projects?.length ?? 0
	if (count === 0) {
		problems.push('noProjects')
	} else if (count > MAX_VIEW_PROJECTS) {
		problems.push('tooManyProjects')
	}
	return problems
}

/**
 * Whether the user may rename, change or delete the view: its owner or an admin.
 *
 * @param {object} view The view.
 * @param {{uid: string, isAdmin?: boolean}} user The current user.
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
 */
export function canManageView(view, user) {
	if (!view || !user?.uid) {
		return false
	}
	return user.isAdmin === true || view.owner === user.uid
}

/**
 * The projects a user may put in a view: the ones they are a member or the owner of.
 *
 * @param {Array<object>} projects The projects the user can read.
 * @param {string}        uid      The user.
 * @return {Array<object>}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
 */
export function pickableProjects(projects, uid) {
	return (projects || []).filter((project) => project && (project.owner === uid || (Array.isArray(project.members) && project.members.includes(uid))))
}

/**
 * The view's reads put together: every readable project's tasks, the readable
 * projects by id, and how many projects the viewer cannot read. OpenRegister
 * answers a project the viewer may not read and one that no longer exists
 * with the same 404, so both count as hidden and neither is named.
 *
 * @param {Array<{projectId: string, project: object|null, tasks: Array<object>}>} results One per project in the view.
 * @return {{tasks: Array<object>, projectsById: object, hidden: number}}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
 */
export function mergeViewResults(results) {
	const tasks = []
	const projectsById = {}
	const seen = new Set()
	let hidden = 0
	for (const result of results || []) {
		if (!result?.project) {
			hidden++
			continue
		}
		projectsById[result.projectId] = result.project
		for (const task of result.tasks || []) {
			const id = idOf(task)
			if (!task || task.project !== result.projectId || seen.has(id)) {
				continue
			}
			seen.add(id)
			tasks.push(task)
		}
	}
	return { tasks, projectsById, hidden }
}

/**
 * The view's lanes: the filtered tasks grouped by status.
 *
 * @param {Array<object>} tasks  The view's tasks.
 * @param {object}        filter A board filter.
 * @param {string}        uid    The current user.
 * @param {Date}          [today] Today.
 * @return {{[status: string]: Array<object>}}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
 */
export function viewLanes(tasks, filter, uid, today = new Date()) {
	return groupTasksByStatus((tasks || []).filter((task) => matchesFilter(task, filter, uid, today)), BOARD_STATUSES)
}

/**
 * The write that moves a task to a status lane, through its own project: the
 * first column by order whose mapped status is the lane's status, at the end
 * of that column, exactly the write a move on the project board makes. No
 * matching column refuses the move.
 *
 * @param {object}        task         The moved task.
 * @param {string}        status       The target lane's status.
 * @param {Array<object>} columns      The task's project columns.
 * @param {Array<object>} projectTasks The task's project tasks.
 * @return {{ok: true, patch: object}|{ok: false}}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.4
 */
export function resolveViewMove(task, status, columns, projectTasks) {
	const column = sortColumns(columns).find((candidate) => mappedStatus(candidate) === status)
	if (!column) {
		return { ok: false }
	}
	const lane = (projectTasks || []).filter((card) => card && card.column === idOf(column) && idOf(card) !== idOf(task))
	return { ok: true, patch: buildMovePatch(column, lane, null) }
}
