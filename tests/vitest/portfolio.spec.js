/**
 * Unit tests for the portfolio capacity-summary helper.
 *
 * SPDX-FileCopyrightText: 2026 Planninq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/capacity-planning-resource.md
 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { isDueSoon, remainingMinutes, summariseByAssignee, summariseProjectTasks, UNASSIGNED } from '../../src/utils/portfolioHelpers.js'

const NOW = new Date('2026-07-07T12:00:00')

describe('summariseProjectTasks', () => {
	it('counts open, overdue and total', () => {
		const tasks = [
			{ status: 'open', dueDate: '2026-07-01' }, // open + overdue
			{ status: 'in_progress', dueDate: '2026-12-01' }, // open, not overdue
			{ status: 'open' }, // open, no due date
			{ status: 'done', dueDate: '2026-01-01' }, // closed → ignored
			{ status: 'cancelled' }, // closed → ignored
		]
		expect(summariseProjectTasks(tasks, NOW)).toEqual({
			open: 3,
			overdue: 1,
			total: 5,
		})
	})

	it('does not count a closed task as overdue even if its due date passed', () => {
		const tasks = [{ status: 'done', dueDate: '2026-01-01' }]
		expect(summariseProjectTasks(tasks, NOW)).toEqual({
			open: 0,
			overdue: 0,
			total: 1,
		})
	})

	it('returns zeros for an empty list', () => {
		expect(summariseProjectTasks([], NOW)).toEqual({
			open: 0,
			overdue: 0,
			total: 0,
		})
	})
})

describe('summariseByAssignee', () => {
	const omgevingsvisie = { id: 'p-1', title: 'Omgevingsvisie' }
	const wegbeheer = { id: 'p-2', title: 'Wegbeheer' }
	const input = () => [
		{
			project: omgevingsvisie,
			tasks: [
				{ status: 'open', assignedTo: 'ada', remainingEstimate: 240 },
				{ status: 'in_progress', assignedTo: 'ada', estimatedDuration: 180 },
				{ status: 'open', assignedTo: 'ada', remainingEstimate: 180, estimatedDuration: 600 },
				{ status: 'done', assignedTo: 'ada', remainingEstimate: 999 },
			],
		},
		{
			project: wegbeheer,
			tasks: [
				{ status: 'open', assignedTo: 'ada' },
				{ status: 'open', assignedTo: 'ada', dueDate: '2026-07-15' },
				{ status: 'open', assignedTo: 'bram', dueDate: '2026-07-01', remainingEstimate: 240 },
				{ status: 'open' },
				{ status: 'open', assignedTo: '' },
				{ status: 'cancelled' },
			],
		},
	]

	it('gives one row per person with open, overdue, hours and tasks without an estimate', () => {
		const rows = summariseByAssignee(input(), NOW)
		const ada = rows.find((row) => row.uid === 'ada')
		expect(ada).toMatchObject({ open: 5, overdue: 0, minutes: 600, withoutEstimate: 2, dueSoon: 1, shared: 0 })
		const bram = rows.find((row) => row.uid === 'bram')
		expect(bram).toMatchObject({ open: 1, overdue: 1, minutes: 240, withoutEstimate: 0 })
	})

	it('keeps open work without an assignee in its own row, listed last', () => {
		const rows = summariseByAssignee(input(), NOW)
		expect(rows[rows.length - 1]).toMatchObject({ uid: UNASSIGNED, open: 2 })
	})

	it('breaks a person down per project', () => {
		const ada = summariseByAssignee(input(), NOW).find((row) => row.uid === 'ada')
		expect(ada.projects.map((p) => [p.title, p.open])).toEqual([['Omgevingsvisie', 3], ['Wegbeheer', 2]])
		expect(ada.projects[0].minutes).toBe(600)
	})

	it('counts a shared task for the primary assignee only, and as shared without hours for the others', () => {
		const rows = summariseByAssignee([{ project: omgevingsvisie, tasks: [{ status: 'open', assignedTo: 'ada', sharedWith: ['bram', 'ada'], remainingEstimate: 360 }] }], NOW)
		expect(rows.find((row) => row.uid === 'ada')).toMatchObject({ open: 1, minutes: 360, shared: 0 })
		expect(rows.find((row) => row.uid === 'bram')).toMatchObject({ open: 0, minutes: 0, shared: 1 })
		expect(rows.reduce((sum, row) => sum + row.minutes, 0)).toBe(360)
	})

	it('counts only the projects it is given', () => {
		const rows = summariseByAssignee(input().filter(({ project }) => project.id === 'p-1'), NOW)
		expect(rows.map((row) => row.uid)).toEqual(['ada'])
	})

	it('treats a due date within 14 days as due soon, and not one further out or past', () => {
		expect(isDueSoon({ dueDate: '2026-07-07' }, NOW)).toBe(true)
		expect(isDueSoon({ dueDate: '2026-07-21' }, NOW)).toBe(true)
		expect(isDueSoon({ dueDate: '2026-07-22' }, NOW)).toBe(false)
		expect(isDueSoon({ dueDate: '2026-07-06' }, NOW)).toBe(false)
		expect(isDueSoon({}, NOW)).toBe(false)
	})

	it('reads the remaining estimate before the estimate', () => {
		expect(remainingMinutes({ remainingEstimate: 0, estimatedDuration: 60 })).toBe(0)
		expect(remainingMinutes({ estimatedDuration: 60 })).toBe(60)
		expect(remainingMinutes({})).toBeNull()
	})
})
