/**
 * Pure helpers for the project overview, the project tabs and the project log
 * (projects-overview-logs-risks).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { buildMovePatch, sortColumns } from './columnHelpers.js'

/** The log entry types, in the order the filter shows them. */
export const LOG_TYPES = ['issue', 'lesson', 'meeting', 'decision']

/** The `issueType` of a task that came out of a log entry. */
export const ACTION_ISSUE_TYPE = 'action'

/** The project tabs in display order. Each names the manifest page it opens. */
export const PROJECT_TABS = [
	{ id: 'overview', route: 'ProjectOverview' },
	{ id: 'board', route: 'ProjectBoard' },
	{ id: 'backlog', route: 'ProjectBacklog' },
	{ id: 'timeline', route: 'ProjectTimeline' },
	{ id: 'risks', route: 'ProjectRisks' },
	{ id: 'log', route: 'ProjectLog' },
]

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
 * Progress of a project: done tasks against every task that is not cancelled.
 *
 * `percentComplete` is not used: nothing sets it, and a mean of unset values
 * reads as zero.
 *
 * @param {Array<object>} tasks The project's tasks.
 * @return {{done: number, total: number}}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
export function projectProgress(tasks = []) {
	let done = 0
	let total = 0
	for (const task of tasks || []) {
		if (!task || task.status === 'cancelled') {
			continue
		}
		total++
		if (task.status === 'done') {
			done++
		}
	}
	return { done, total }
}

/**
 * The people on a project: its owner first, then its members, each once.
 *
 * @param {object} project The project.
 * @return {Array<string>} User ids.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
export function projectPeople(project) {
	const people = []
	for (const uid of [project?.owner, ...(project?.members || [])]) {
		if (typeof uid === 'string' && uid !== '' && !people.includes(uid)) {
			people.push(uid)
		}
	}
	return people
}

/**
 * When an entry was saved, from OpenRegister's own metadata.
 *
 * @param {object} entry The log entry.
 * @return {string}
 */
export function entryCreated(entry) {
	return String(entry?.['@self']?.created ?? entry?.created ?? '')
}

/**
 * Who saved an entry, from OpenRegister's own metadata.
 *
 * @param {object} entry The log entry.
 * @return {string}
 */
export function entryAuthor(entry) {
	return String(entry?.['@self']?.owner ?? '')
}

/**
 * Log entries newest first: by date, then by the time they were saved.
 *
 * @param {Array<object>} entries The entries.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
 */
export function sortLog(entries = []) {
	return [...(entries || [])].sort((a, b) => {
		const byDate = String(b?.date ?? '').localeCompare(String(a?.date ?? ''))
		return byDate !== 0 ? byDate : entryCreated(b).localeCompare(entryCreated(a))
	})
}

/**
 * The newest entries of a log, for the overview.
 *
 * @param {Array<object>} entries The entries.
 * @param {number} [limit] How many.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
export function latestLogEntries(entries = [], limit = 5) {
	return sortLog(entries).slice(0, limit)
}

/**
 * The entries a log filter shows. `all` shows every entry; a type shows that type.
 *
 * @param {Array<object>} entries The entries.
 * @param {string} filter `all` or one of LOG_TYPES.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
 */
export function filterLog(entries = [], filter = 'all') {
	const sorted = sortLog(entries)
	return LOG_TYPES.includes(filter) ? sorted.filter((entry) => entry?.type === filter) : sorted
}

/**
 * The project's actions: its tasks that came out of a log entry.
 *
 * @param {Array<object>} tasks The project's tasks.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.3
 */
export function actionTasks(tasks = []) {
	return (tasks || []).filter((task) => task?.issueType === ACTION_ISSUE_TYPE)
}

/**
 * The payload of a new or edited log entry. The server adds the author, the
 * time and the members list.
 *
 * @param {object} fields The form fields.
 * @param {string} projectId The project.
 * @return {object}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
 */
export function logEntryPayload(fields, projectId) {
	const type = LOG_TYPES.includes(fields?.type) ? fields.type : 'issue'
	const payload = {
		title: String(fields?.title ?? '').trim(),
		project: projectId,
		type,
		date: String(fields?.date ?? ''),
		body: String(fields?.body ?? ''),
		status: fields?.status === 'closed' ? 'closed' : 'open',
		attendees: type === 'meeting' ? [...new Set((fields?.attendees || []).filter(Boolean))] : [],
		actions: [...(fields?.actions || [])],
	}
	return payload
}

/**
 * The task an "Add action" creates: an ordinary task in the same project with
 * `issueType: 'action'`, at the bottom of the board's first column so it
 * appears on the board. With no columns it lands in the backlog.
 *
 * @param {object} fields `title`, and optionally `assignedTo` and `dueDate`.
 * @param {string} projectId The project.
 * @param {Array<object>} columns The project's board columns.
 * @param {Array<object>} tasks The project's tasks.
 * @return {object}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.3
 */
export function newActionTask(fields, projectId, columns = [], tasks = []) {
	const task = {
		title: String(fields?.title ?? '').trim(),
		status: 'open',
		priority: 'normal',
		issueType: ACTION_ISSUE_TYPE,
		project: projectId,
	}
	if (fields?.assignedTo) {
		task.assignedTo = fields.assignedTo
	}
	if (fields?.dueDate) {
		task.dueDate = fields.dueDate
	}
	const first = sortColumns(columns)[0]
	if (!first) {
		return { ...task, column: null }
	}
	const lane = (tasks || []).filter((other) => other?.column === idOf(first))
	return { ...task, ...buildMovePatch(first, lane) }
}
