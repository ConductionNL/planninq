/**
 * The timer helpers and the timer store actions (time-timer-and-work-type).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { elapsedMinutes, timerNeedsWarning, workTypesOf } from '../../src/utils/timer.js'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'olga' }) }))
vi.mock('@nextcloud/axios', () => ({ default: {} }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const START = '2026-10-08T09:00:00.000Z'
const at = (ms) => new Date(Date.parse(START) + ms)

describe('timer helpers', () => {
	it('rounds up to whole minutes', () => {
		expect(elapsedMinutes(START, at(61 * 1000))).toBe(2)
		expect(elapsedMinutes(START, at(120 * 1000))).toBe(2)
	})

	it('never books zero minutes', () => {
		expect(elapsedMinutes(START, at(0))).toBe(1)
		expect(elapsedMinutes(START, at(-5000))).toBe(1)
	})

	it('warns after twelve hours', () => {
		expect(timerNeedsWarning(START, at(12 * 3600 * 1000))).toBe(false)
		expect(timerNeedsWarning(START, at(12 * 3600 * 1000 + 60000))).toBe(true)
	})

	it('reads the work types from a JSON list', () => {
		expect(workTypesOf('["Advies","Beheer"]')).toEqual(['Advies', 'Beheer'])
		expect(workTypesOf('nope')).toEqual([])
		expect(workTypesOf(undefined)).toEqual([])
	})
})

describe('timer store actions', () => {
	let store
	let saved

	beforeEach(async () => {
		setActivePinia(createPinia())
		saved = []
		const { useSettingsStore } = await import('../../src/store/modules/settings.js')
		const settings = useSettingsStore()
		settings.settings = {}
		settings.saveUserSettings = vi.fn(async (body) => {
			saved.push(body)
			settings.settings = { ...settings.settings, ...body }
			return { success: true }
		})
		const { useTimeEntriesStore } = await import('../../src/store/timeEntries.js')
		store = useTimeEntriesStore()
	})

	it('starts a timer on a task and refuses a second one', async () => {
		expect(await store.startTimer('t1')).toBe(true)
		expect(saved[0].running_timer.task).toBe('t1')
		expect(await store.startTimer('t2')).toBe(false)
		expect(saved).toHaveLength(1)
	})

	it('discard clears the timer and books nothing', async () => {
		await store.startTimer('t1')
		await store.discardTimer()
		expect(saved[1]).toEqual({ running_timer: null })
	})

	it('stop reports the task and the minutes without clearing the timer', async () => {
		await store.startTimer('t1')
		const stopped = store.stopTimer(new Date(Date.now() + 90 * 1000))
		expect(stopped.task).toBe('t1')
		expect(stopped.minutes).toBe(2)
		expect(saved).toHaveLength(1)
	})
})
