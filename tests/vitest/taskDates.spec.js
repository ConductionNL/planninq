/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * A stored `YYYY-MM-DD` keeps its calendar day in every time zone, and a start
 * date after the due date is refused.
 *
 * Node reads `process.env.TZ` on each date operation, so each case runs in the
 * zone it names; the zone is restored after.
 *
 * @spec openspec/changes/tasks-dates/specs/task-dates/spec.md#requirement-a-date-only-due-date-keeps-its-day-in-every-time-zone
 */
import { afterEach, describe, expect, it } from 'vitest'
import { dueDateStatus, localDate, validateTaskDates } from '../../src/utils/taskHelpers.js'
import { parseDay } from '../../src/utils/timelineHelpers.js'

const ORIGINAL_TZ = process.env.TZ

afterEach(() => {
	if (ORIGINAL_TZ === undefined) {
		delete process.env.TZ
	} else {
		process.env.TZ = ORIGINAL_TZ
	}
})

describe.each(['America/New_York', 'Europe/Amsterdam', 'Pacific/Auckland', 'UTC'])('in %s', (zone) => {
	it('shows a task due today as due soon, not overdue', () => {
		process.env.TZ = zone
		const morning = new Date(2026, 8, 30, 8, 0, 0)
		const evening = new Date(2026, 8, 30, 23, 0, 0)

		expect(dueDateStatus({ dueDate: '2026-09-30' }, morning)).toBe('approaching')
		expect(dueDateStatus({ dueDate: '2026-09-30' }, evening)).toBe('approaching')
	})

	it('shows yesterday as overdue and three days out as no badge', () => {
		process.env.TZ = zone
		const now = new Date(2026, 8, 30, 12, 0, 0)

		expect(dueDateStatus({ dueDate: '2026-09-29' }, now)).toBe('overdue')
		expect(dueDateStatus({ dueDate: '2026-10-03' }, now)).toBe(null)
	})

	it('reads YYYY-MM-DD as local midnight of that day', () => {
		process.env.TZ = zone
		const day = localDate('2026-09-30')

		expect([day.getFullYear(), day.getMonth(), day.getDate(), day.getHours()]).toEqual([2026, 8, 30, 0])
	})

	it('indexes a timeline day from the date itself', () => {
		process.env.TZ = zone

		expect(parseDay('1970-01-02')).toBe(1)
		expect(parseDay('2026-10-10') - parseDay('2026-10-01')).toBe(9)
	})
})

describe('localDate', () => {
	it('refuses anything but a real calendar day', () => {
		expect(localDate('2026-02-30')).toBe(null)
		expect(localDate('2026-9-30')).toBe(null)
		expect(localDate('2026-09-30T10:00:00Z')).toBe(null)
		expect(localDate(' 2026-09-30 ').getDate()).toBe(30)
	})
})

describe('parseDay', () => {
	it('still reads a full timestamp and refuses garbage', () => {
		expect(parseDay('1970-01-03T12:00:00Z')).toBe(2)
		expect(parseDay('not a date')).toBe(null)
		expect(parseDay('')).toBe(null)
		expect(parseDay(null)).toBe(null)
	})
})

/**
 * @spec openspec/changes/tasks-dates/specs/task-dates/spec.md#requirement-a-project-member-can-set-a-start-date
 */
describe('validateTaskDates', () => {
	it('refuses a start date after the due date', () => {
		expect(validateTaskDates('2026-10-12', '2026-10-10')).toBe('startAfterDue')
	})

	it('accepts a start on or before the due date', () => {
		expect(validateTaskDates('2026-10-01', '2026-10-10')).toBe(null)
		expect(validateTaskDates('2026-10-10', '2026-10-10')).toBe(null)
	})

	it('has no order to check while either date is empty', () => {
		expect(validateTaskDates('2026-10-12', null)).toBe(null)
		expect(validateTaskDates('', '2026-10-10')).toBe(null)
		expect(validateTaskDates(undefined, undefined)).toBe(null)
	})
})
