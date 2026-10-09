/**
 * Vitest unit tests for task date handling: local-day parsing, the
 * start-before-due check, the picker round trip and the edit/create payloads.
 *
 * @spec openspec/changes/tasks-dates/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { fromPickerDate, parseLocalDate, toPickerDate, validateTaskDates } from '../../src/utils/taskDates.js'
import { editPatch, newLaneTask } from '../../src/utils/taskEditing.js'
import { dueDateStatus } from '../../src/utils/taskHelpers.js'
import { parseDay, toScheduled } from '../../src/utils/timelineHelpers.js'

describe('parseLocalDate', () => {
	it('reads a date-only string as that local calendar day', () => {
		const date = parseLocalDate('2026-09-30')
		expect([date.getFullYear(), date.getMonth(), date.getDate()]).toEqual([2026, 8, 30])
	})

	it('returns null for empty or unreadable values', () => {
		expect(parseLocalDate('')).toBeNull()
		expect(parseLocalDate(null)).toBeNull()
		expect(parseLocalDate('not a date')).toBeNull()
	})
})

describe('dueDateStatus and the timeline keep the stored day', () => {
	it('a task due today is approaching, not overdue', () => {
		const now = new Date(2026, 8, 30, 8, 0, 0)
		expect(dueDateStatus({ dueDate: '2026-09-30' }, now)).toBe('approaching')
		expect(dueDateStatus({ dueDate: '2026-09-29' }, now)).toBe('overdue')
	})

	it('adjacent dates are exactly one day apart', () => {
		expect(parseDay('2026-10-10') - parseDay('2026-10-01')).toBe(9)
	})

	it('places a bar from the start to the due date', () => {
		const [bar] = toScheduled([{ id: 't', startDate: '2026-10-01', dueDate: '2026-10-10' }])
		expect(bar.endDay - bar.startDay).toBe(9)
	})
})

describe('validateTaskDates', () => {
	it('refuses a start after the due date', () => {
		expect(validateTaskDates('2026-10-12', '2026-10-10')).toBe('startAfterDue')
	})

	it('accepts equal, earlier or missing dates', () => {
		expect(validateTaskDates('2026-10-10', '2026-10-10')).toBeNull()
		expect(validateTaskDates('2026-10-01', '2026-10-10')).toBeNull()
		expect(validateTaskDates('', '2026-10-10')).toBeNull()
		expect(validateTaskDates('2026-10-12', null)).toBeNull()
	})
})

describe('picker round trip', () => {
	it('stores a picked day without a time', () => {
		expect(fromPickerDate(new Date(2026, 8, 30, 23, 59))).toBe('2026-09-30')
		expect(fromPickerDate(toPickerDate('2026-01-05'))).toBe('2026-01-05')
		expect(fromPickerDate(null)).toBe('')
	})
})

describe('edit and create payloads', () => {
	const task = { title: 'A', description: '', status: 'open', priority: 'normal', dueDate: '2026-10-10' }

	it('patches only a changed date and clears with null', () => {
		expect(editPatch(task, { ...task, dueDate: '2026-10-10' })).toEqual({})
		expect(editPatch(task, { ...task, dueDate: '2026-10-11' })).toEqual({ dueDate: '2026-10-11' })
		expect(editPatch(task, { ...task, dueDate: '' })).toEqual({ dueDate: null })
		expect(editPatch(task, { ...task, startDate: '2026-10-01' })).toEqual({ startDate: '2026-10-01' })
	})

	it('a new task carries the dates that were set', () => {
		const created = newLaneTask({ title: 'B', startDate: '2026-10-01', dueDate: '2026-10-05' }, 'p1', null)
		expect(created.startDate).toBe('2026-10-01')
		expect(created.dueDate).toBe('2026-10-05')
	})
})
