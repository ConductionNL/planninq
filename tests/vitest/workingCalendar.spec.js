/**
 * The working calendar (planning-timeline-editing task 1.2).
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-1.2
 */
import { describe, expect, it } from 'vitest'
import { addWorkingDays, dutchHolidays, isWorkingDay, nextWorkingDay, normaliseCalendar, workingDaysBetween } from '../../src/utils/workingCalendar.js'

const calendar = normaliseCalendar({
	working_weekdays: '[1,2,3,4,5]',
	non_working_days: JSON.stringify([{ date: '2026-12-25', name: 'Christmas Day' }, { date: '2026-12-28', name: 'Office closed' }]),
})

describe('workingCalendar', () => {
	it('skips weekends and listed holidays', () => {
		expect(isWorkingDay('2026-12-24', calendar)).toBe(true)
		expect(isWorkingDay('2026-12-25', calendar)).toBe(false)
		expect(isWorkingDay('2026-12-26', calendar)).toBe(false)
		expect(isWorkingDay('2026-12-27', calendar)).toBe(false)
		expect(nextWorkingDay('2026-12-25', calendar)).toBe('2026-12-29')
		expect(nextWorkingDay('2026-12-24', calendar)).toBe('2026-12-24')
		expect(addWorkingDays('2026-12-29', 1, calendar)).toBe('2026-12-30')
		expect(addWorkingDays('2026-12-24', 1, calendar)).toBe('2026-12-29')
		expect(addWorkingDays('2026-12-29', 0, calendar)).toBe('2026-12-29')
	})

	it('counts working days between two dates', () => {
		expect(workingDaysBetween('2026-12-21', '2026-12-31', calendar)).toBe(7)
		expect(workingDaysBetween('2026-12-29', '2026-12-30', calendar)).toBe(2)
		expect(workingDaysBetween('2026-12-30', '2026-12-29', calendar)).toBe(0)
	})

	it('Dutch holidays 2027 include Easter Monday 29 March', () => {
		const days = dutchHolidays(2027)
		const byDate = Object.fromEntries(days.map((day) => [day.date, day.name]))
		expect(byDate['2027-03-29']).toBe('Easter Monday')
		expect(byDate['2027-04-27']).toBe('King\'s Day')
		expect(byDate['2027-05-06']).toBe('Ascension Day')
		expect(byDate['2027-05-17']).toBe('Whit Monday')
		expect(byDate['2027-01-01']).toBe('New Year\'s Day')
		expect(byDate['2027-12-26']).toBe('Boxing Day')
		expect(days.map((day) => day.date)).toEqual([...days.map((day) => day.date)].sort())
		// King's Day moves to Saturday 26 April when 27 April is a Sunday (2025).
		expect(dutchHolidays(2025).some((day) => day.date === '2025-04-26' && day.name === 'King\'s Day')).toBe(true)
		// Names go through the caller's translation.
		expect(dutchHolidays(2027, (name) => `nl:${name}`)[0].name).toBe('nl:New Year\'s Day')
	})

	it('reads a malformed setting as the defaults', () => {
		const fallback = normaliseCalendar({ working_weekdays: 'nonsense', non_working_days: '{"a":1}' })
		expect(fallback.weekdays).toEqual([1, 2, 3, 4, 5])
		expect(fallback.holidays.size).toBe(0)
		expect(calendar.holidays.get('2026-12-25')).toBe('Christmas Day')
	})
})
