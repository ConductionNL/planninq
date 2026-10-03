/**
 * Pure helpers for My tasks and the personal project order on the dashboard
 * (portfolio-my-work-dashboard). Free of Vue and the DOM so vitest covers them.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { CLOSED_STATUSES } from './portfolioHelpers.js'

/** Priority order, urgent first; a task without a priority counts as normal. */
const PRIORITY_RANK = { urgent: 0, high: 1, normal: 2, low: 3 }

/** The figures on the dashboard that open My tasks narrowed to them. */
export const MY_WORK_FILTERS = ['open', 'overdue', 'in_progress', 'completed_today']

/**
 * A date as a local calendar day at midnight, or null.
 *
 * @param {string|Date|null|undefined} value The date.
 * @return {Date|null}
 */
function dayOf(value) {
	if (!value) {
		return null
	}
	const date = value instanceof Date ? value : new Date(value)
	if (Number.isNaN(date.getTime())) {
		return null
	}
	return new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

/**
 * Whether a task is assigned to the user or shared with them.
 *
 * @param {object} task The task.
 * @param {string} uid The user id.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
export function isMine(task, uid) {
	if (!uid || !task) {
		return false
	}
	return task.assignedTo === uid || (Array.isArray(task.sharedWith) && task.sharedWith.includes(uid))
}

/**
 * Whether a task is still open work.
 *
 * @param {object} task The task.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
export function isOpenTask(task) {
	return !!task && !CLOSED_STATUSES.includes(task.status)
}

/**
 * Whether an open task's due date lies before today.
 *
 * @param {object} task The task.
 * @param {Date} today Today.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
export function isOverdue(task, today = new Date()) {
	const due = dayOf(task?.dueDate)
	return isOpenTask(task) && due !== null && due < dayOf(today)
}

/**
 * Urgent to low, then the earliest due date, then the title.
 *
 * @param {object} a A task.
 * @param {object} b Another task.
 * @return {number}
 */
function byUrgency(a, b) {
	const rank = (task) => PRIORITY_RANK[task?.priority] ?? PRIORITY_RANK.normal
	const due = (task) => dayOf(task?.dueDate)?.getTime() ?? Number.POSITIVE_INFINITY
	return rank(a) - rank(b) || due(a) - due(b) || String(a?.title ?? '').localeCompare(String(b?.title ?? ''))
}

/**
 * Open tasks in three groups: Overdue (due before today), Due this week (today
 * up to and including Sunday) and Later (after this week, or no due date),
 * each sorted urgent to low. Closed tasks are left out.
 *
 * @param {Array<object>} tasks The tasks.
 * @param {Date} [today] Today.
 * @return {Array<{id: string, tasks: Array<object>}>}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
export function groupMyTasks(tasks = [], today = new Date()) {
	const start = dayOf(today)
	const sunday = new Date(start)
	sunday.setDate(start.getDate() + ((7 - start.getDay()) % 7))
	const groups = { overdue: [], week: [], later: [] }
	for (const task of tasks || []) {
		if (!isOpenTask(task)) {
			continue
		}
		const due = dayOf(task.dueDate)
		if (due !== null && due < start) {
			groups.overdue.push(task)
		} else if (due !== null && due <= sunday) {
			groups.week.push(task)
		} else {
			groups.later.push(task)
		}
	}
	return Object.entries(groups).map(([id, list]) => ({ id, tasks: list.sort(byUrgency) }))
}

/**
 * The tasks My tasks lists. Without a figure: open tasks assigned to or
 * shared with the user. With a dashboard figure: the tasks that figure
 * counts, which are the ones the user is responsible for.
 *
 * @param {Array<object>} tasks The tasks read.
 * @param {string} uid The user id.
 * @param {string} figure '', or one of MY_WORK_FILTERS.
 * @param {Date} [today] Today.
 * @return {Array<object>}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
export function filterMyTasks(tasks = [], uid = '', figure = '', today = new Date()) {
	const list = tasks || []
	if (!MY_WORK_FILTERS.includes(figure)) {
		return list.filter((task) => isOpenTask(task) && isMine(task, uid))
	}
	const responsible = list.filter((task) => uid && task?.assignedTo === uid)
	if (figure === 'overdue') {
		return responsible.filter((task) => isOverdue(task, today))
	}
	if (figure === 'in_progress') {
		return responsible.filter((task) => task.status === 'in_progress')
	}
	if (figure === 'completed_today') {
		const day = dayOf(today).getTime()
		return responsible.filter((task) => task.status === 'done' && dayOf(task.completedAt)?.getTime() === day)
	}
	return responsible.filter(isOpenTask)
}

/**
 * Projects with the pinned ones first, in the user's order, then the rest as given.
 *
 * @param {Array<object>} projects The projects.
 * @param {Array<string>} order The pinned project ids, first to last.
 * @return {Array<object>}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
 */
export function orderProjects(projects = [], order = []) {
	const byId = new Map((projects || []).map((project) => [String(project.id), project]))
	const pinned = (order || []).map((id) => byId.get(String(id))).filter(Boolean)
	const rest = (projects || []).filter((project) => !(order || []).includes(String(project.id)))
	return [...pinned, ...rest]
}

/**
 * Pin a project at the end of the pinned list, or unpin it.
 *
 * @param {Array<string>} order The pinned ids.
 * @param {string} id The project id.
 * @return {Array<string>}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
 */
export function togglePin(order = [], id = '') {
	const list = [...(order || [])]
	return list.includes(id) ? list.filter((pinned) => pinned !== id) : [...list, id]
}

/**
 * Move a pinned project one place up (-1) or down (1).
 *
 * @param {Array<string>} order The pinned ids.
 * @param {string} id The project id.
 * @param {number} step -1 or 1.
 * @return {Array<string>}
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-3.1
 */
export function movePinned(order = [], id = '', step = 1) {
	const list = [...(order || [])]
	const from = list.indexOf(id)
	const to = from + step
	if (from < 0 || to < 0 || to >= list.length) {
		return list
	}
	list.splice(from, 1)
	list.splice(to, 0, id)
	return list
}
