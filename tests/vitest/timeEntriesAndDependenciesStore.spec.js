/**
 * The time entries store reads a task's or the current user's entries from
 * humaniq, decides who may change an entry, and deletes one; the dependencies store deletes an
 * edge and keeps the server's refusal. Only the object store, fetch and the
 * Nextcloud helpers are faked.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/time-tracking/spec.md
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const user = { current: { uid: 'anna', isAdmin: false } }
vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ 'X-Test': '1' }), isAppInstalled: (app) => app === 'humaniq' }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => user.current }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const objectStore = {
	registerObjectType: vi.fn(),
	objectTypeRegistry: {},
	fetchCollection: vi.fn(),
	deleteObject: vi.fn(),
}
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => objectStore }))

const { useTimeEntriesStore } = await import('../../src/store/timeEntries.js')
const { useDependenciesStore } = await import('../../src/store/dependencies.js')

describe('time entries store', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useTimeEntriesStore()
		user.current = { uid: 'anna', isAdmin: false }
		objectStore.fetchCollection.mockReset()
		objectStore.deleteObject.mockReset()
		vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	it('lets the author or an admin change an entry, nobody else', () => {
		expect(store.canModify({ user: 'anna' })).toBe(true)
		expect(store.canModify({ user: 'bob' })).toBe(false)
		expect(store.canModify(null)).toBe(false)

		user.current = { uid: 'root', isAdmin: true }
		expect(store.canModify({ user: 'bob' })).toBe(true)

		user.current = null
		expect(store.canModify({ user: '' })).toBe(false)
	})

	it('reads a task\'s entries from humaniq into the store', async () => {
		objectStore.fetchCollection.mockImplementation(async (type) => (type === 'humaniqTimeEntry'
			? [{ id: 'e1', hours: 0.5, userId: 'anna' }, { id: 'e2', hours: '0.25', userId: 'bob' }]
			: []))

		const entries = await store.fetchForTask('t1')

		expect(objectStore.fetchCollection).toHaveBeenCalledWith('humaniqTimeEntry', { domainObjectType: 'planninq:task', domainObjectRef: 't1', _limit: expect.any(Number), _page: 1 })
		expect(objectStore.fetchCollection).toHaveBeenCalledWith('plannedTimeEntry', { timeEntry: 'e1', _limit: expect.any(Number), _page: 1 })
		expect(entries).toEqual(store.entries)
		expect(store.totalMinutes).toBe(45)
		expect(store.loading).toBe(false)
		expect(store.error).toBeNull()
	})

	it('empties the list and keeps the reason when a task\'s entries cannot be read', async () => {
		store.entries = [{ id: 'old' }]
		objectStore.fetchCollection.mockRejectedValueOnce(new Error('offline'))

		expect(await store.fetchForTask('t1')).toEqual([])
		expect(store.entries).toEqual([])
		expect(store.error).toBe('offline')
		expect(store.loading).toBe(false)
	})

	it('keeps only the current user\'s own entries', async () => {
		objectStore.fetchCollection.mockImplementation(async (type) => (type === 'humaniqTimeEntry'
			? [{ id: 'e1', userId: 'anna', hours: 1 }, { id: 'e2', userId: 'bob', hours: 1 }]
			: []))

		const entries = await store.fetchForCurrentUser()

		expect(entries.map((e) => e.id)).toEqual(['e1'])
		expect(objectStore.fetchCollection).toHaveBeenCalledWith('humaniqTimeEntry', { userId: 'anna', _limit: expect.any(Number), _page: 1 })
	})

	it('keeps every entry when nobody is signed in, and falls back to a code for a failure without message', async () => {
		user.current = null
		objectStore.fetchCollection.mockImplementation(async (type) => (type === 'humaniqTimeEntry'
			? [{ id: 'e1', userId: 'anna' }, { id: 'e2', userId: 'bob' }]
			: []))
		expect(await store.fetchForCurrentUser()).toHaveLength(2)

		objectStore.fetchCollection.mockReset()
		objectStore.fetchCollection.mockRejectedValueOnce({})
		expect(await store.fetchForCurrentUser()).toEqual([])
		expect(store.error).toBe('fetch-error')
	})

	it('removes a deleted entry and its planninq row, and keeps it when the delete is refused or fails', async () => {
		store.entries = [{ id: 'e1', plannedId: 'pl1' }, { id: 'e2', plannedId: null }]
		objectStore.deleteObject.mockResolvedValueOnce(true).mockResolvedValueOnce(true).mockResolvedValueOnce(false).mockRejectedValueOnce(new Error('offline'))

		expect(await store.delete('e1')).toBe(true)
		expect(objectStore.deleteObject.mock.calls).toEqual([['humaniqTimeEntry', 'e1'], ['plannedTimeEntry', 'pl1']])
		expect(store.entries).toEqual([{ id: 'e2', plannedId: null }])

		expect(await store.delete('e2')).toBe(false)
		expect(store.entries).toEqual([{ id: 'e2', plannedId: null }])

		expect(await store.delete('e2')).toBe(false)
		expect(store.error).toBe('offline')
		expect(store.entries).toEqual([{ id: 'e2', plannedId: null }])
	})
})

describe('dependencies store deleteEdge', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useDependenciesStore()
		store.edges = [{ id: 'd1' }, { id: 'd2' }]
		globalThis.fetch = vi.fn()
	})

	afterEach(() => {
		delete globalThis.fetch
	})

	it('deletes the edge on the server and drops it from the list', async () => {
		fetch.mockResolvedValueOnce({ ok: true })

		await store.deleteEdge('d1')

		expect(fetch).toHaveBeenCalledWith('/apps/planninq/api/dependencies/d1', { method: 'DELETE', headers: { 'X-Test': '1' } })
		expect(store.edges).toEqual([{ id: 'd2' }])
		expect(store.error).toBeNull()
	})

	it('keeps the edge and throws the server\'s reason when refused', async () => {
		fetch.mockResolvedValueOnce({ ok: false, json: async () => ({ error: 'Only project members may unlink tasks.' }) })

		await expect(store.deleteEdge('d1')).rejects.toThrow('Only project members may unlink tasks.')
		expect(store.error).toBe('Only project members may unlink tasks.')
		expect(store.edges).toHaveLength(2)
	})

	it('falls back to a generic reason when the refusal has no readable body', async () => {
		const unreadable = async () => {
			throw new Error('not json')
		}
		fetch.mockResolvedValueOnce({ ok: false, json: unreadable })

		await expect(store.deleteEdge('d2')).rejects.toThrow('Failed to delete dependency.')
		expect(store.edges).toHaveLength(2)
	})
})
