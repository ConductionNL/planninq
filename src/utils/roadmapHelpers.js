/**
 * Pure helpers for releases and the project roadmap (backlog-releases-roadmap).
 *
 * A release belongs to one project and a task points at no release or one.
 * An epic is a task with `issueType: epic`; other tasks of the same project
 * point at it through `epic`. Nothing here reads the DOM or writes anything:
 * the patch helpers return only the fields a save must send, because
 * OpenRegister's PUT nulls what it is not sent and a PATCH is the only safe write.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.2
 */
import { MS_PER_DAY, parseDay } from './timelineHelpers.js'

/** The `issueType` that makes a task an epic. */
export const EPIC_TYPE = 'epic'

/** Task statuses that count as finished for a release. */
const FINISHED = ['done', 'cancelled']

/**
 * The id of an object or of a reference, whichever shape the API returned.
 *
 * @param {object|string|null} value The object, reference or id.
 * @return {string} The id, or ''.
 */
export function refId(value) {
	if (value === null || value === undefined) {
		return ''
	}
	if (typeof value === 'object') {
		return String(value.id ?? value.uuid ?? value['@self']?.id ?? '')
	}
	return String(value)
}

/**
 * Whether a task is an epic.
 *
 * @param {object} task The task.
 * @return {boolean}
 */
export function isEpic(task) {
	return task?.issueType === EPIC_TYPE
}

/**
 * The tasks planned against a release.
 *
 * @param {object} release The release.
 * @param {Array<object>} tasks The project's tasks.
 * @return {Array<object>}
 */
function tasksOf(release, tasks) {
	const id = refId(release)
	return (tasks || []).filter((task) => id !== '' && refId(task.release) === id)
}

/**
 * Progress of a release: done or cancelled tasks out of all tasks that reference it.
 *
 * @param {object} release The release.
 * @param {Array<object>} tasks The project's tasks.
 * @return {{done: number, total: number}}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.2
 */
export function releaseProgress(release, tasks) {
	const planned = tasksOf(release, tasks)
	return { done: planned.filter((task) => FINISHED.includes(task.status)).length, total: planned.length }
}

/**
 * The tasks of a release that are neither done nor cancelled.
 *
 * @param {object} release The release.
 * @param {Array<object>} tasks The project's tasks.
 * @return {Array<object>}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
 */
export function unfinishedTasks(release, tasks) {
	return tasksOf(release, tasks).filter((task) => !FINISHED.includes(task.status))
}

/**
 * The date span of an epic: its own dates, or else the earliest start to the
 * latest due date of the tasks linked to it. Null when there is no date at all.
 *
 * @param {object} epic The epic.
 * @param {Array<object>} tasks The project's tasks.
 * @return {{start: string, end: string}|null}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.2
 */
export function epicSpan(epic, tasks) {
	if (epic?.startDate || epic?.dueDate) {
		return { start: epic.startDate || epic.dueDate, end: epic.dueDate || epic.startDate }
	}
	const id = refId(epic)
	const dates = []
	;(tasks || []).filter((task) => refId(task.epic) === id).forEach((task) => {
		;[task.startDate, task.dueDate].forEach((value) => {
			if (parseDay(value) !== null) {
				dates.push(String(value).slice(0, 10))
			}
		})
	})
	if (dates.length === 0) {
		return null
	}
	dates.sort()
	return { start: dates[0], end: dates[dates.length - 1] }
}

/**
 * Releases in target-date order, releases without a date last.
 *
 * @param {Array<object>} releases The releases.
 * @return {Array<object>} A sorted copy.
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
 */
export function sortReleases(releases) {
	return [...(releases || [])].sort((a, b) => {
		const x = a.releaseDate || '￿'
		const y = b.releaseDate || '￿'
		return x < y ? -1 : (x > y ? 1 : String(a.title || '').localeCompare(String(b.title || '')))
	})
}

/**
 * The roadmap chart: a bar per dated epic and a marker per dated release on
 * one day axis, plus the epics and releases that have no date.
 *
 * @param {Array<object>} epics The project's epics.
 * @param {Array<object>} tasks The project's tasks (for epic spans).
 * @param {Array<object>} releases The project's releases.
 * @param {number} pxPerDay Pixels per day.
 * @return {{bars: Array<object>, markers: Array<object>, unscheduled: Array<object>, undatedReleases: Array<object>, minDay: number, dayCount: number, chartWidth: number}}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.2
 */
export function roadmapLayout(epics, tasks, releases, pxPerDay) {
	const spans = []
	const unscheduled = []
	;(epics || []).forEach((epic) => {
		const span = epicSpan(epic, tasks)
		if (span) {
			spans.push({ epic, span, startDay: parseDay(span.start), endDay: parseDay(span.end) })
		} else {
			unscheduled.push(epic)
		}
	})
	const dated = sortReleases(releases).filter((release) => parseDay(release.releaseDate) !== null)
	const undatedReleases = sortReleases(releases).filter((release) => parseDay(release.releaseDate) === null)

	const days = [
		...spans.flatMap((row) => [row.startDay, row.endDay]),
		...dated.map((release) => parseDay(release.releaseDate)),
	]
	const minDay = days.length ? Math.min(...days) : 0
	const maxDay = days.length ? Math.max(...days) : 0
	const dayCount = Math.max(1, maxDay - minDay + 1)

	spans.sort((a, b) => a.startDay - b.startDay)
	const bars = spans.map((row) => ({
		id: refId(row.epic),
		title: row.epic.title || '',
		start: row.span.start,
		end: row.span.end,
		left: (row.startDay - minDay) * pxPerDay,
		width: Math.max(pxPerDay, (row.endDay - row.startDay + 1) * pxPerDay),
	}))
	const markers = dated.map((release) => ({
		id: refId(release),
		title: release.title || '',
		date: String(release.releaseDate).slice(0, 10),
		status: release.status || 'planned',
		left: (parseDay(release.releaseDate) - minDay) * pxPerDay,
	}))

	return { bars, markers, unscheduled, undatedReleases, minDay, dayCount, chartWidth: dayCount * pxPerDay }
}

/**
 * The ISO date of a day index.
 *
 * @param {number} day Days since the epoch.
 * @return {string}
 */
export function dayIso(day) {
	return new Date(day * MS_PER_DAY).toISOString().slice(0, 10)
}

/**
 * The releases a task may be planned against: the planned releases of its own
 * project, plus the one it points at now, so the picker can show it.
 *
 * @param {object} task The task.
 * @param {Array<object>} releases The releases.
 * @return {Array<object>}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.4
 */
export function releaseChoices(task, releases) {
	const project = refId(task?.project)
	const current = refId(task?.release)
	return sortReleases((releases || []).filter((release) => refId(release.project) === project
		&& ((release.status || 'planned') === 'planned' || refId(release) === current)))
}

/**
 * The epics a task may link to: epics of its own project, never itself. An
 * epic links to no epic, so an epic gets no choices.
 *
 * @param {object} task The task.
 * @param {Array<object>} tasks The project's tasks.
 * @return {Array<object>}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.3
 */
export function epicChoices(task, tasks) {
	if (isEpic(task)) {
		return []
	}
	const project = refId(task?.project)
	const self = refId(task)
	return (tasks || []).filter((other) => isEpic(other) && refId(other.project) === project && refId(other) !== self)
}

/**
 * The PATCH that links a task to an epic (or unlinks it, for null), or why not.
 *
 * @param {object} task The task.
 * @param {object|null} epic The epic, or null to unlink.
 * @return {{ok: true, patch: object}|{ok: false, reason: string}}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.3
 */
export function epicPatch(task, epic) {
	if (!epic) {
		return { ok: true, patch: { epic: null } }
	}
	if (refId(epic.project) !== refId(task?.project)) {
		return { ok: false, reason: 'other-project' }
	}
	if (isEpic(task)) {
		return { ok: false, reason: 'epic-in-epic' }
	}
	if (!isEpic(epic) || refId(epic) === refId(task)) {
		return { ok: false, reason: 'not-an-epic' }
	}
	return { ok: true, patch: { epic: refId(epic) } }
}

/**
 * The POST body of a new release.
 *
 * @param {object} form The dialog's fields.
 * @param {string} projectId The project.
 * @return {object}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-3.3
 */
export function releasePayload(form, projectId) {
	return {
		title: String(form.title || '').trim(),
		project: projectId,
		releaseDate: form.releaseDate || null,
		startDate: form.startDate || null,
		description: form.description || '',
		status: 'planned',
	}
}

/**
 * The writes that ship a release: one PATCH per unfinished task for the
 * member's choice, then the release's own PATCH.
 *
 * @param {object} release The release.
 * @param {Array<object>} tasks The project's tasks.
 * @param {string} choice 'move', 'clear' or 'keep'.
 * @param {string|null} targetId The planned release to move to, for 'move'.
 * @param {Date} now The moment of release.
 * @return {{release: object, tasks: Array<{id: string, patch: object}>}}
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
 */
export function shipPatches(release, tasks, choice, targetId, now = new Date()) {
	if (choice === 'move' && !targetId) {
		throw new Error('A release to move the tasks to is required.')
	}
	const open = choice === 'keep' ? [] : unfinishedTasks(release, tasks)
	const value = choice === 'move' ? targetId : null
	return {
		release: { status: 'released', releasedAt: now.toISOString() },
		tasks: open.map((task) => ({ id: refId(task), patch: { release: value } })),
	}
}
