/**
 * Placing tasks on a calendar (planning-calendar task 1.1).
 *
 * @spec openspec/changes/planning-calendar/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { dateKey, listByDate, monthWeeks, placement, shiftMonth, tasksByDay, weekDays } from '../../src/utils/calendarHelpers.js'

const tasks = [
	{ id: 'a', title: 'Export to CSV', dueDate: '2026-10-16' },
	{ id: 'b', title: 'Import from CSV', dueDate: '2026-10-23T00:00:00+02:00', startDate: '2026-10-19' },
	{ id: 'c', title: 'Kick-off', startDate: '2026-10-05' },
	{ id: 'd', title: 'Someday' },
]

describe('calendarHelpers', () => {
	it('places tasks on their due date', () => {
		const days = tasksByDay(tasks)
		expect(days.get('2026-10-16').map((item) => item.task.id)).toEqual(['a'])
		expect(days.get('2026-10-23').map((item) => item.task.id)).toEqual(['b'])
		expect(days.has('2026-10-19')).toBe(false)
		expect([...days.values()].flat().some((item) => item.task.id === 'd')).toBe(false)
	})

	it('start-only task sits on its start date', () => {
		expect(placement(tasks[2])).toEqual({ date: '2026-10-05', starts: true })
		expect(placement(tasks[0])).toEqual({ date: '2026-10-16', starts: false })
		expect(placement(tasks[3])).toBeNull()
	})

	it('week range starts on Monday', () => {
		expect(weekDays('2026-10-16')).toEqual(['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18'])
		expect(weekDays('2026-10-18')[0]).toBe('2026-10-12')
		expect(weekDays('2026-10-19')[0]).toBe('2026-10-19')
	})

	it('month grid covers whole weeks from Monday', () => {
		const weeks = monthWeeks(2026, 9)
		expect(weeks[0][0]).toEqual({ date: '2026-09-28', inMonth: false })
		expect(weeks[0][3]).toEqual({ date: '2026-10-01', inMonth: true })
		expect(weeks.at(-1).at(-1)).toEqual({ date: '2026-11-01', inMonth: false })
		expect(weeks.every((week) => week.length === 7)).toBe(true)
	})

	it('moves by month across the year', () => {
		expect(shiftMonth(2026, 11, 1)).toEqual({ year: 2027, month: 0 })
		expect(shiftMonth(2026, 0, -1)).toEqual({ year: 2025, month: 11 })
	})

	it('the list shows the same tasks under their dates, in date order', () => {
		const list = listByDate(tasks, '2026-10-01', '2026-10-31')
		expect(list.map((group) => group.date)).toEqual(['2026-10-05', '2026-10-16', '2026-10-23'])
		expect(list[0].items[0]).toMatchObject({ starts: true })
	})

	it('reads a date without shifting it across a time zone', () => {
		expect(dateKey('2026-10-23T23:30:00-05:00')).toBe('2026-10-23')
		expect(dateKey(new Date(2026, 9, 5))).toBe('2026-10-05')
		expect(dateKey('')).toBe('')
	})
})
