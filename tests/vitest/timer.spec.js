/**
 * Unit tests for the running timer helpers.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
import { describe, expect, it } from 'vitest'
import { bookedMinutes, elapsedClock, elapsedHours, elapsedMs, isLongTimer, newTimer, stoppedTimer } from '../../src/utils/timer.js'

const START = '2026-11-14T09:00:00.000Z'
const at = (iso) => Date.parse(iso)

describe('bookedMinutes', () => {
	it('rounds up to whole minutes', () => {
		// 42 minutes and 10 seconds books 43 minutes.
		expect(bookedMinutes(START, at('2026-11-14T09:42:10Z'))).toBe(43)
		expect(bookedMinutes(START, at('2026-11-14T09:42:00Z'))).toBe(42)
		expect(bookedMinutes(START, at('2026-11-14T10:00:00.001Z'))).toBe(61)
	})

	it('never books zero minutes', () => {
		expect(bookedMinutes(START, at(START))).toBe(1)
		expect(bookedMinutes(START, at('2026-11-14T09:00:05Z'))).toBe(1)
		// A clock behind the start (another device) still books one minute.
		expect(bookedMinutes(START, at('2026-11-14T08:59:00Z'))).toBe(1)
		expect(bookedMinutes('not a date', at(START))).toBe(1)
	})
})

describe('isLongTimer', () => {
	it('warns after twelve hours', () => {
		// Started yesterday at 17:00, stopped today at 07:00: 14 hours.
		const start = '2026-11-13T17:00:00+01:00'
		const stop = at('2026-11-14T07:00:00+01:00')
		expect(isLongTimer(start, stop)).toBe(true)
		expect(elapsedHours(start, stop)).toBe(14)
	})

	it('does not warn at exactly twelve hours or less', () => {
		expect(isLongTimer(START, at('2026-11-14T21:00:00Z'))).toBe(false)
		expect(isLongTimer(START, at('2026-11-14T21:00:01Z'))).toBe(true)
		expect(isLongTimer(START, at('2026-11-14T09:30:00Z'))).toBe(false)
	})
})

describe('elapsed time', () => {
	it('is null for a start that is not a date, and never negative', () => {
		expect(elapsedMs('yesterday', at(START))).toBeNull()
		expect(elapsedMs(undefined, at(START))).toBeNull()
		expect(elapsedMs(START, at('2026-11-14T08:00:00Z'))).toBe(0)
	})

	it('shows as a clock', () => {
		expect(elapsedClock(START, at('2026-11-14T09:20:05Z'))).toBe('0:20:05')
		expect(elapsedClock(START, at('2026-11-15T11:03:00Z'))).toBe('26:03:00')
	})
})

describe('starting and stopping', () => {
	it('starts a timer on a task at now', () => {
		expect(newTimer('task-1', at(START))).toEqual({ task: 'task-1', startedAt: START })
	})

	it('hands the form the measured minutes, the start and stop, and the warning', () => {
		expect(stoppedTimer({ task: 'task-1', startedAt: START }, at('2026-11-14T09:42:10Z'))).toEqual({
			task: 'task-1',
			minutes: 43,
			startedAt: START,
			endedAt: '2026-11-14T09:42:10.000Z',
			long: false,
			hours: 1,
		})
	})
})
