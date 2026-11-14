/**
 * Logged hours live once, on humaniq's TimeEntry: logging 90 minutes writes one
 * humaniq entry of 1.5 hours and one plannedTimeEntry referencing it, each
 * checked against the real schema; a rate stays in planninq; without humaniq
 * nothing is written and the refusal names humaniq. Only the object store,
 * fetch and the Nextcloud helpers are faked.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md
 */
import fs from 'node:fs'
import path from 'node:path'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const hoursApp = { on: true }
vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}), isAppInstalled: (app) => app === 'humaniq' && hoursApp.on }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'mila' }) }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))

const objectStore = {
	registerObjectType: vi.fn(),
	objectTypeRegistry: {},
	fetchCollection: vi.fn(async () => []),
	saveObject: vi.fn(),
	deleteObject: vi.fn(async () => true),
}
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => objectStore }))

const { useTimeEntriesStore } = await import('../../src/store/timeEntries.js')
const hours = await import('../../src/utils/loggedHours.js')

const ROOT = path.join(__dirname, '..', '..')
const PLANNED = JSON.parse(fs.readFileSync(path.join(ROOT, 'lib', 'Settings', 'planninq_register.json'), 'utf8')).components.schemas.plannedTimeEntry
const OWNER = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures', 'humaniqTimeEntry.schema.json'), 'utf8'))

/**
 * The problems an object has against a schema: unknown properties, missing
 * required ones and values of the wrong JSON type or outside an enum.
 *
 * @param {object} schema The schema.
 * @param {object} object The object.
 * @return {Array<string>} The problems; empty when the object fits.
 */
function problems(schema, object) {
	const found = []
	for (const key of schema.required || []) {
		if (object[key] === undefined || object[key] === null || object[key] === '') {
			found.push(`missing ${key}`)
		}
	}
	for (const [key, value] of Object.entries(object)) {
		const property = schema.properties[key]
		if (!property) {
			found.push(`unknown ${key}`)
			continue
		}
		const type = typeof value === 'number' ? 'number' : typeof value
		const expected = property.type === 'integer' ? 'number' : property.type
		if (expected && expected !== type) {
			found.push(`${key} is ${type}, not ${property.type}`)
		}
		if (property.enum && !property.enum.includes(value)) {
			found.push(`${key} is outside its enum`)
		}
	}
	return found
}

describe('logged hours live on humaniq', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useTimeEntriesStore()
		hoursApp.on = true
		objectStore.saveObject.mockReset()
		objectStore.deleteObject.mockReset()
		objectStore.deleteObject.mockResolvedValue(true)
		objectStore.saveObject.mockImplementation(async (type, payload) => ({ id: type === 'humaniqTimeEntry' ? 'te-1' : 'pl-1', ...payload }))
	})

	afterEach(() => {
		vi.unstubAllGlobals()
		vi.restoreAllMocks()
	})

	it('writes one humaniq TimeEntry of 1.5 hours and one plannedTimeEntry referencing it for 90 minutes', async () => {
		const saved = await store.create({ task: 't1', project: 'p1', duration: 90, date: '2026-10-07', description: 'Review' })

		expect(objectStore.saveObject).toHaveBeenCalledTimes(2)
		const [[ownerType, owner], [plannedType, planned]] = objectStore.saveObject.mock.calls
		expect(ownerType).toBe('humaniqTimeEntry')
		expect(owner).toEqual({
			date: '2026-10-07',
			hours: 1.5,
			userId: 'mila',
			description: 'Review',
			origin: 'manual',
			projectId: 'p1',
			domainObjectType: 'planninq:task',
			domainObjectRef: 't1',
		})
		expect(problems(OWNER, owner)).toEqual([])

		expect(plannedType).toBe('plannedTimeEntry')
		expect(planned).toEqual({ timeEntry: 'te-1', user: 'mila', task: 't1' })
		expect(problems(PLANNED, planned)).toEqual([])
		expect(planned).not.toHaveProperty('duration')
		expect(planned).not.toHaveProperty('date')

		expect(saved).toMatchObject({ id: 'te-1', plannedId: 'pl-1', task: 't1', project: 'p1', user: 'mila', duration: 90, date: '2026-10-07' })
		expect(store.entries).toEqual([saved])
		expect(objectStore.registerObjectType).toHaveBeenCalledWith('humaniqTimeEntry', 'TimeEntry', 'humaniq', { registerSlug: 'humaniq', schemaSlug: 'TimeEntry' })
	})

	it('keeps a rate in planninq', async () => {
		await store.create({ task: 't1', duration: 60, date: '2026-10-07', hourlyRate: 95, contractorRef: 'c-1' })

		const [, planned] = objectStore.saveObject.mock.calls[1]
		const [, owner] = objectStore.saveObject.mock.calls[0]
		expect(planned).toMatchObject({ hourlyRate: 95, contractorRef: 'c-1' })
		expect(owner).not.toHaveProperty('hourlyRate')
		expect(problems(PLANNED, planned)).toEqual([])
	})

	it('changes only the planninq row when a rate is edited', async () => {
		store.entries = [{ id: 'te-1', plannedId: 'pl-1', duration: 60 }]
		const fetchMock = vi.fn(async () => ({ ok: true, json: async () => ({}) }))
		vi.stubGlobal('fetch', fetchMock)

		const saved = await store.update('te-1', { hourlyRate: 110 })

		expect(fetchMock).toHaveBeenCalledTimes(1)
		const [url, init] = fetchMock.mock.calls[0]
		expect(url).toBe('/apps/openregister/api/objects/planninq/plannedTimeEntry/pl-1')
		expect(JSON.parse(init.body)).toEqual({ hourlyRate: 110 })
		expect(saved).toMatchObject({ id: 'te-1', hourlyRate: 110, duration: 60 })
	})

	it('refuses a rate edit on an entry without a planninq row, writing nothing', async () => {
		store.entries = [{ id: 'te-1', plannedId: null }]
		const fetchMock = vi.fn()
		vi.stubGlobal('fetch', fetchMock)

		expect(await store.update('te-1', { hourlyRate: 110 })).toBeNull()
		expect(fetchMock).not.toHaveBeenCalled()
		expect(store.error).toBe('update-error')
	})

	it('removes the humaniq entry again when the planninq row cannot be written', async () => {
		objectStore.saveObject.mockImplementation(async (type, payload) => (type === 'humaniqTimeEntry' ? { id: 'te-1', ...payload } : null))

		expect(await store.create({ task: 't1', duration: 30, date: '2026-10-07' })).toBeNull()
		expect(objectStore.deleteObject).toHaveBeenCalledWith('humaniqTimeEntry', 'te-1')
		expect(store.entries).toEqual([])
		expect(store.error).toBe('create-error')
	})

	it('keeps the reason a thrown planninq write gave, and removes the humaniq entry', async () => {
		objectStore.saveObject.mockImplementation(async (type, payload) => {
			if (type === 'humaniqTimeEntry') {
				return { id: 'te-1', ...payload }
			}
			throw new Error('Forbidden')
		})

		expect(await store.create({ task: 't1', duration: 30 })).toBeNull()
		expect(objectStore.deleteObject).toHaveBeenCalledWith('humaniqTimeEntry', 'te-1')
		expect(store.error).toBe('Forbidden')
	})

	it('writes nothing when humaniq refuses the hours', async () => {
		objectStore.saveObject.mockResolvedValueOnce(null)

		expect(await store.create({ task: 't1', duration: 30 })).toBeNull()
		expect(objectStore.saveObject).toHaveBeenCalledTimes(1)
		expect(store.error).toBe('create-error')
	})

	it('writes nothing without humaniq, and says it needs humaniq', async () => {
		hoursApp.on = false

		expect(await store.create({ task: 't1', duration: 90 })).toBeNull()
		expect(objectStore.saveObject).not.toHaveBeenCalled()
		expect(store.error).toBe('Time logging needs the humaniq app')
	})

	it('reads nothing without humaniq, and says why', async () => {
		hoursApp.on = false
		objectStore.fetchCollection.mockClear()

		expect(await store.fetchForTask('t1')).toEqual([])
		expect(objectStore.fetchCollection).not.toHaveBeenCalled()
		expect(store.error).toBe('Time logging needs the humaniq app')
	})

	it('shows an hour corrected in humaniq on the next read', async () => {
		objectStore.fetchCollection.mockImplementation(async (type) => (type === 'humaniqTimeEntry'
			? [{ id: 'te-1', userId: 'mila', date: '2026-10-07', hours: 2, domainObjectType: 'planninq:task', domainObjectRef: 't1' }]
			: [{ id: 'pl-1', timeEntry: 'te-1', hourlyRate: 80 }]))

		const [entry] = await store.fetchForTask('t1')

		expect(entry).toMatchObject({ id: 'te-1', plannedId: 'pl-1', duration: 120, hourlyRate: 80, task: 't1' })
	})
})

describe('loggedHours helpers', () => {
	it('converts minutes to hours as the repair step does, and back', () => {
		expect(hours.minutesToHours(45)).toBe(0.75)
		expect(hours.minutesToHours(90)).toBe(1.5)
		expect(hours.minutesToHours(20)).toBe(0.33)
		expect(hours.minutesToHours('x')).toBe(0)
		expect(hours.minutesToHours(-5)).toBe(0)
		expect(hours.hoursToMinutes(1.5)).toBe(90)
		expect(hours.hoursToMinutes('0.75')).toBe(45)
		expect(hours.hoursToMinutes(null)).toBe(0)
	})

	it('splits an edit into the owner\'s half and planninq\'s half, and drops what neither holds', () => {
		expect(hours.splitPatch({ duration: 30, date: '2026-10-08', description: 'x', hourlyRate: 70, contractorRef: 'c', billable: true, task: 't9' })).toEqual({
			owner: { hours: 0.5, date: '2026-10-08', description: 'x' },
			planned: { hourlyRate: 70, contractorRef: 'c' },
		})
		expect(hours.splitPatch(null)).toEqual({ owner: {}, planned: {} })
	})

	it('reads a humaniq entry that is not about a planninq task as having no task', () => {
		expect(hours.viewEntryOf({ id: 'te-1', domainObjectType: 'pipelinq:lead', domainObjectRef: 'l1', hours: 1 })).toMatchObject({ task: null, duration: 60, plannedId: null, hourlyRate: null })
	})

	it('joins by reference and skips a humaniq row without an id', () => {
		const joined = hours.joinEntries(
			[{ id: 'a', hours: 1 }, { hours: 2 }, { '@self': { id: 'b' }, hours: 0.5 }],
			[{ id: 'pa', timeEntry: 'a', hourlyRate: 50 }, { id: 'px' }],
		)
		expect(joined.map((e) => [e.id, e.plannedId, e.duration])).toEqual([['a', 'pa', 60], ['b', null, 30]])
		expect(hours.joinEntries(null, null)).toEqual([])
	})

	it('leaves no project or task on the humaniq entry when the page gave none', () => {
		expect(hours.ownerEntryOf({ user: 'mila', date: '2026-10-07', duration: 15 })).toEqual({ date: '2026-10-07', hours: 0.25, userId: 'mila', description: '', origin: 'manual' })
	})
})
