/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * The date half of task editing: what the dialog and the task page PATCH,
 * what a new task carries, and the round trip between a stored `YYYY-MM-DD`
 * and the Date a picker holds.
 *
 * @spec openspec/changes/tasks-dates/specs/task-dates/spec.md#requirement-a-project-member-can-set-a-due-date-without-a-time
 */
import { afterEach, describe, expect, it } from 'vitest'
import { datesPatch, editPatch, newLaneTask, pickerDate, storedDate } from '../../src/utils/taskEditing.js'

const ORIGINAL_TZ = process.env.TZ

afterEach(() => {
	if (ORIGINAL_TZ === undefined) {
		delete process.env.TZ
	} else {
		process.env.TZ = ORIGINAL_TZ
	}
})

describe('datesPatch', () => {
	it('sets a due date on a task without one, with no time of day', () => {
		expect(datesPatch({ title: 'A' }, { dueDate: '2026-09-30' })).toEqual({ dueDate: '2026-09-30' })
	})

	it('clears a date as null, never an empty string', () => {
		expect(datesPatch({ dueDate: '2026-09-30', startDate: '2026-09-01' }, { dueDate: '', startDate: '' }))
			.toEqual({ dueDate: null, startDate: null })
	})

	it('leaves out what did not change and what the draft does not carry', () => {
		expect(datesPatch({ dueDate: '2026-09-30' }, { dueDate: '2026-09-30' })).toEqual({})
		expect(datesPatch({ dueDate: null }, { dueDate: '' })).toEqual({})
		expect(datesPatch({ dueDate: '2026-09-30' }, { startDate: '2026-09-01' })).toEqual({ startDate: '2026-09-01' })
	})
})

describe('editPatch with dates', () => {
	it('carries a changed date beside the other changed fields', () => {
		const task = { title: 'A', description: '', status: 'open', priority: 'normal', dueDate: '2026-09-30' }
		const draft = { title: 'A', description: '', status: 'open', priority: 'high', startDate: '2026-09-01', dueDate: '' }

		expect(editPatch(task, draft)).toEqual({ priority: 'high', startDate: '2026-09-01', dueDate: null })
	})

	it('touches no date when the draft has none', () => {
		const task = { title: 'A', description: '', status: 'open', priority: 'normal', dueDate: '2026-09-30' }

		expect(editPatch(task, { title: 'B', description: '', status: 'open', priority: 'normal' })).toEqual({ title: 'B' })
	})
})

describe('newLaneTask with dates', () => {
	it('carries the dates that were set and none that were not', () => {
		const task = newLaneTask({ title: 'A', startDate: '2026-10-01', dueDate: '' }, 'project-1', null)

		expect(task.startDate).toBe('2026-10-01')
		expect('dueDate' in task).toBe(false)
	})
})

describe.each(['America/New_York', 'Asia/Tokyo'])('picker round trip in %s', (zone) => {
	it('stores the day the picker shows', () => {
		process.env.TZ = zone
		const picked = pickerDate('2026-09-30')

		expect(picked.getDate()).toBe(30)
		expect(storedDate(picked)).toBe('2026-09-30')
		expect(storedDate(new Date(2026, 0, 5, 23, 59))).toBe('2026-01-05')
	})
})

describe('pickerDate and storedDate edges', () => {
	it('answers nothing for no date', () => {
		expect(pickerDate(null)).toBe(null)
		expect(pickerDate('')).toBe(null)
		expect(storedDate(null)).toBe('')
		expect(storedDate(new Date('nonsense'))).toBe('')
	})
})
