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
