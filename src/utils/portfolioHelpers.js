/**
 * Pure derived-state helpers for the Portfolio capacity-planning MVP.
 *
 * Kept free of Vue/DOM so the counting logic can be unit-tested in a bare node
 * environment (tests/vitest/portfolio.spec.js).
 *
 * SPDX-FileCopyrightText: 2026 Planninq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/capacity-planning-resource.md
 */
import { dueDateStatus } from './taskHelpers.js'

/** Task statuses that count as "closed" (not open work). */
export const CLOSED_STATUSES = ['done', 'cancelled']

/**
 * Summarise a project's task list into the capacity counts the Portfolio MVP
 * shows: open (not done/cancelled), overdue (past due and still open) and
 * total.
 *
 * @param {Array<{status: string, dueDate?: string}>} tasks The project's tasks.
 * @param {Date} [now] Reference date for the overdue test (defaults to now).
 * @return {{open: number, overdue: number, total: number}} Capacity counts.
 *
 * @spec openspec/specs/capacity-planning-resource.md
 */
export function summariseProjectTasks(tasks = [], now = new Date()) {
	let open = 0
	let overdue = 0
	for (const task of tasks) {
		const closed = CLOSED_STATUSES.includes(task.status)
		if (!closed) {
			open++
			if (dueDateStatus(task, now) === 'overdue') {
				overdue++
			}
		}
	}
	return { open, overdue, total: tasks.length }
}

/** The key of the row for open work nobody is assigned to. */
export const UNASSIGNED = ''

/** Days ahead that count as "due soon" on the capacity report. */
export const DUE_SOON_DAYS = 14

/**
 * The minutes still to do on a task: its remaining estimate, else its
 * estimate, else null when it has neither.
 *
 * @param {object} task The task.
 * @return {number|null}
 *
 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-1.1
 */
export function remainingMinutes(task) {
	for (const field of ['remainingEstimate', 'estimatedDuration']) {
		const value = task?.[field]
		if (value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))) {
			return Math.max(0, Number(value))
		}
	}
	return null
}

/**
 * Whether an open task falls due today or in the next DUE_SOON_DAYS days.
 *
 * @param {object} task The task.
 * @param {Date} now Reference date.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-1.1
 */
export function isDueSoon(task, now = new Date()) {
	if (!task?.dueDate) {
		return false
	}
	const due = new Date(task.dueDate)
	if (Number.isNaN(due.getTime())) {
		return false
	}
	const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate())
	const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
	const days = Math.round((dueDay.getTime() - today.getTime()) / 86400000)
	return days >= 0 && days <= DUE_SOON_DAYS
}

/**
 * Open work per person across projects. A task's hours count for its primary
 * assignee (`assignedTo`) only; people in `sharedWith` count it as shared,
 * without hours, so the hours column adds up to the projects' remaining
 * estimate. Open work without an assignee gets the UNASSIGNED row.
 *
 * @param {Array<{project: object, tasks: Array<object>}>} tasksByProject The projects read, each with its tasks.
 * @param {Date} [now] Reference date for overdue and due soon.
 * @return {Array<{uid: string, open: number, overdue: number, minutes: number, withoutEstimate: number, dueSoon: number, shared: number, projects: Array<{id: string, title: string, open: number, overdue: number, minutes: number, shared: number}>}>}
 *
 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-1.1
 */
export function summariseByAssignee(tasksByProject = [], now = new Date()) {
	const people = new Map()
	const rowFor = (uid) => {
		if (!people.has(uid)) {
			people.set(uid, { uid, open: 0, overdue: 0, minutes: 0, withoutEstimate: 0, dueSoon: 0, shared: 0, projects: new Map() })
		}
		return people.get(uid)
	}
	const partFor = (row, project) => {
		const id = String(project?.id ?? '')
		if (!row.projects.has(id)) {
			row.projects.set(id, { id, title: String(project?.title ?? ''), open: 0, overdue: 0, minutes: 0, shared: 0 })
		}
		return row.projects.get(id)
	}
	for (const { project, tasks } of tasksByProject || []) {
		for (const task of tasks || []) {
			if (!task || CLOSED_STATUSES.includes(task.status)) {
				continue
			}
			const owner = typeof task.assignedTo === 'string' && task.assignedTo !== '' ? task.assignedTo : UNASSIGNED
			const row = rowFor(owner)
			const part = partFor(row, project)
			const overdue = dueDateStatus(task, now) === 'overdue'
			const minutes = remainingMinutes(task)
			row.open++
			part.open++
			if (overdue) {
				row.overdue++
				part.overdue++
			}
			if (minutes === null) {
				row.withoutEstimate++
			} else {
				row.minutes += minutes
				part.minutes += minutes
			}
			if (isDueSoon(task, now)) {
				row.dueSoon++
			}
			const shared = new Set(Array.isArray(task.sharedWith) ? task.sharedWith : [])
			for (const uid of shared) {
				if (typeof uid !== 'string' || uid === '' || uid === owner) {
					continue
				}
				const sharedRow = rowFor(uid)
				sharedRow.shared++
				partFor(sharedRow, project).shared++
			}
		}
	}
	return [...people.values()]
		.map((row) => ({ ...row, projects: [...row.projects.values()].sort((a, b) => a.title.localeCompare(b.title)) }))
		.sort((a, b) => (a.uid === UNASSIGNED) - (b.uid === UNASSIGNED) || b.open - a.open || a.uid.localeCompare(b.uid))
}
