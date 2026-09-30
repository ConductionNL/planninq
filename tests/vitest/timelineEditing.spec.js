/**
 * Editing dates on the timeline (planning-timeline-editing task 2.2).
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { keyStep, moveTo, resizeTo, stepWorkingDays } from '../../src/utils/timelineEditing.js'
import { normaliseCalendar } from '../../src/utils/workingCalendar.js'

const calendar = normaliseCalendar({ non_working_days: [{ date: '2026-12-28', name: 'Office closed' }] })
const exportTask = { title: 'Export to CSV', startDate: '2026-10-05', dueDate: '2026-10-09' }

describe('timelineEditing', () => {
	it('a bar dragged one week keeps its working length', () => {
		expect(moveTo(exportTask, '2026-10-12', calendar)).toEqual({ startDate: '2026-10-12', dueDate: '2026-10-16' })
	})

	it('a dropped start on a holiday moves to the next working day', () => {
		const twoDays = { startDate: '2026-12-21', dueDate: '2026-12-22' }
		expect(moveTo(twoDays, '2026-12-28', calendar)).toEqual({ startDate: '2026-12-29', dueDate: '2026-12-30' })
	})

	it('resizing the due date keeps the start, and an end never crosses the other', () => {
		expect(resizeTo(exportTask, 'due', '2026-10-13')).toEqual({ startDate: '2026-10-05', dueDate: '2026-10-13' })
		expect(resizeTo(exportTask, 'due', '2026-10-01')).toEqual({ startDate: '2026-10-05', dueDate: '2026-10-05' })
		expect(resizeTo(exportTask, 'start', '2026-10-07')).toEqual({ startDate: '2026-10-07', dueDate: '2026-10-09' })
		expect(resizeTo(exportTask, 'start', '2026-10-20')).toEqual({ startDate: '2026-10-09', dueDate: '2026-10-09' })
	})

	it('the keyboard moves a task by one working day, or only its due date with Shift', () => {
		expect(keyStep(exportTask, 1, false, calendar)).toEqual({ startDate: '2026-10-06', dueDate: '2026-10-12' })
		expect(keyStep(exportTask, -1, false, calendar)).toEqual({ startDate: '2026-10-02', dueDate: '2026-10-08' })
		expect(keyStep(exportTask, 1, true, calendar)).toEqual({ startDate: '2026-10-05', dueDate: '2026-10-12' })
		expect(stepWorkingDays('2026-12-29', -1, calendar)).toBe('2026-12-25')
	})
})
