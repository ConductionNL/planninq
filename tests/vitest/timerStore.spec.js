/**
 * Unit tests for the running timer actions of the time entries store.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-1.2
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = { timer: null, saves: [], refuse: false }

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {}, saveObject: vi.fn() }),
}))
vi.mock('../../src/store/modules/settings.js', () => ({
	useSettingsStore: () => ({
		fetchSettings: async () => ({ running_timer: server.timer }),
		saveUserSettings: async (settings) => {
			server.saves.push(settings)
			if (server.refuse) {
				return null
			}
			server.timer = settings.running_timer
			return { success: true, config: { running_timer: server.timer } }
		},
	}),
}))

const { useTimeEntriesStore } = await import('../../src/store/timeEntries.js')

const START = Date.parse('2026-11-14T09:00:00.000Z')

describe('the running timer', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		server.timer = null
		server.saves = []
		server.refuse = false
	})

	it('starts on a task and is stored in the person\'s settings, booking nothing', async () => {
		const store = useTimeEntriesStore()
		expect(await store.startTimer('task-1', START)).toBe(true)
		expect(server.saves).toEqual([{ running_timer: { task: 'task-1', startedAt: '2026-11-14T09:00:00.000Z' } }])
		expect(store.runningTimer).toEqual({ task: 'task-1', startedAt: '2026-11-14T09:00:00.000Z' })
		expect(store.entries).toEqual([])
	})

	it('survives a reload: a new page reads the same timer', async () => {
		server.timer = { task: 'task-1', startedAt: '2026-11-14T09:00:00+00:00' }
		const store = useTimeEntriesStore()
		expect(await store.loadTimer()).toEqual(server.timer)
		expect(store.runningTimer).toEqual(server.timer)
	})

	it('stops into what the form is prefilled with, and clears the timer', async () => {
		const store = useTimeEntriesStore()
		await store.startTimer('task-1', START)
		const stopped = await store.stopTimer(START + (42 * 60 + 10) * 1000)
		expect(stopped).toMatchObject({ task: 'task-1', minutes: 43, long: false })
		expect(server.saves.at(-1)).toEqual({ running_timer: null })
		expect(store.runningTimer).toBeNull()
	})

	it('discard clears the timer without a form', async () => {
		const store = useTimeEntriesStore()
		await store.startTimer('task-1', START)
		expect(await store.discardTimer()).toBe(true)
		expect(store.runningTimer).toBeNull()
		expect(await store.stopTimer()).toBeNull()
	})

	it('keeps the timer when the server refuses to clear it', async () => {
		const store = useTimeEntriesStore()
		await store.startTimer('task-1', START)
		server.refuse = true
		expect(await store.stopTimer(START + 60000)).toBeNull()
		expect(store.runningTimer).toEqual({ task: 'task-1', startedAt: '2026-11-14T09:00:00.000Z' })
		expect(store.error).toBe('timer-error')
	})

	it('starts nothing without a task', async () => {
		const store = useTimeEntriesStore()
		expect(await store.startTimer('')).toBe(false)
		expect(server.saves).toEqual([])
	})
})
