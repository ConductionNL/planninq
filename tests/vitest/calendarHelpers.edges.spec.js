/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the calendar helpers: Date values, invalid dates, and tasks
 * placed by their start date.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-calendar/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { dateKey, placement, tasksByDay } from '../../src/utils/calendarHelpers.js'

describe('dateKey edges', () => {
	it('formats a local Date and refuses an invalid one', () => {
		expect(dateKey(new Date(2026, 0, 5, 23, 30))).toBe('2026-01-05')
		expect(dateKey(new Date('nope'))).toBe('')
	})

	it('takes the date part of a string and nothing from other values', () => {
		expect(dateKey('2026-11-15T10:00:00Z')).toBe('2026-11-15')
		expect(dateKey('15-11-2026')).toBe('')
		expect(dateKey(null)).toBe('')
		expect(dateKey(undefined)).toBe('')
	})
})

describe('placement edges', () => {
	it('prefers the due date, then the start date, else nowhere', () => {
		expect(placement({ dueDate: '2026-11-20', startDate: '2026-11-01' })).toEqual({ date: '2026-11-20', starts: false })
		expect(placement({ startDate: '2026-11-01' })).toEqual({ date: '2026-11-01', starts: true })
		expect(placement({ dueDate: 'garbage' })).toBeNull()
		expect(placement(null)).toBeNull()
	})
})

describe('tasksByDay edges', () => {
	it('groups by day, sorts each day by title and skips unplaced tasks', () => {
		const days = tasksByDay([
			{ title: 'b', dueDate: '2026-11-20' },
			{ dueDate: '2026-11-20' },
			{ title: 'a', startDate: '2026-11-20' },
			{ title: 'nowhere' },
		])
		expect([...days.keys()]).toEqual(['2026-11-20'])
		expect(days.get('2026-11-20').map((item) => [item.task.title, item.starts])).toEqual([[undefined, false], ['a', true], ['b', false]])
	})

	it('is empty for a missing list', () => {
		expect(tasksByDay(null).size).toBe(0)
	})
})
