/**
 * The time entries store edits with PATCH, so the entry keeps its author and
 * project, and dates a new entry in Dutch local time.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/time-tracking/spec.md
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'olga' }) }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const saveObject = vi.fn(async (schema, payload) => ({ id: 'te-new', ...payload }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {}, saveObject }) }))

const { useTimeEntriesStore } = await import('../../src/store/timeEntries.js')
const { localIsoDate } = await import('../../src/utils/localDate.js')

describe('localIsoDate', () => {
	it('gives the Amsterdam date just after midnight, when UTC is still on the day before', () => {
		expect(localIsoDate(new Date('2026-10-20T22:30:00Z'))).toBe('2026-10-21')
		expect(localIsoDate(new Date('2026-01-14T23:30:00Z'))).toBe('2026-01-15')
	})

	it('gives the same date as UTC in the afternoon', () => {
		expect(localIsoDate(new Date('2026-10-21T13:00:00Z'))).toBe('2026-10-21')
	})
})

describe('timeEntries store', () => {
	let store
	let fetchMock

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useTimeEntriesStore()
		store.entries = [{ id: 'te-1', user: 'olga', project: 'p1', duration: 60 }]
		fetchMock = vi.fn(async (url, init) => ({ ok: true, json: async () => ({ id: 'te-1', user: 'olga', project: 'p1', ...JSON.parse(init.body) }) }))
		vi.stubGlobal('fetch', fetchMock)
	})

	afterEach(() => {
		vi.unstubAllGlobals()
		vi.useRealTimers()
	})

	it('PATCHes only the edited fields', async () => {
		const saved = await store.update('te-1', { duration: 90, description: 'Review' })
		const [url, init] = fetchMock.mock.calls[0]
		expect(url).toBe('/apps/openregister/api/objects/planninq/plannedTimeEntry/te-1')
		expect(init.method).toBe('PATCH')
		expect(JSON.parse(init.body)).toEqual({ duration: 90, description: 'Review' })
		expect(saved.user).toBe('olga')
		expect(store.entries[0]).toMatchObject({ duration: 90, project: 'p1' })
	})

	it('returns null and keeps the entry when the server refuses', async () => {
		fetchMock.mockResolvedValueOnce({ ok: false, json: async () => ({ message: 'Forbidden' }) })
		expect(await store.update('te-1', { duration: 90 })).toBeNull()
		expect(store.error).toBe('Forbidden')
		expect(store.entries[0].duration).toBe(60)
	})

	it('dates a new entry in Dutch local time', async () => {
		vi.useFakeTimers()
		vi.setSystemTime(new Date('2026-10-20T22:30:00Z'))
		await store.create({ task: 't1', duration: 30 })
		expect(saveObject.mock.calls.at(-1)[1].date).toBe('2026-10-21')
	})
})
