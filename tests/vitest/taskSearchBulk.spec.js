/**
 * Task search and bulk update (tasks-search-and-bulk).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-1.1
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { matchesSearch } from '../../src/utils/taskHelpers.js'
import { labelChangePatch, responsiblePatch } from '../../src/utils/taskPeople.js'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'olga' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { useProjectsStore } = await import('../../src/store/projects.js')

describe('matchesSearch', () => {
	const task = { title: 'Fix the Roof', description: 'Leak above the kitchen', key: 'HUIS-12' }

	it('ignores case and surrounding whitespace', () => {
		expect(matchesSearch(task, '  ROOF ')).toBe(true)
	})

	it('looks in the description and the key', () => {
		expect(matchesSearch(task, 'kitchen')).toBe(true)
		expect(matchesSearch(task, 'huis-12')).toBe(true)
	})

	it('an empty term matches everything, a miss matches nothing', () => {
		expect(matchesSearch(task, '   ')).toBe(true)
		expect(matchesSearch(task, 'garden')).toBe(false)
		expect(matchesSearch({ title: 'x' }, 'y')).toBe(false)
	})
})

describe('bulkUpdateTasks', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		vi.stubGlobal('fetch', vi.fn(async (url) => (url.endsWith('/t2') ? { ok: false, json: async () => ({}) } : { ok: true, json: async () => ({}) })))
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('tries every task and reports the failing one', async () => {
		const result = await store.bulkUpdateTasks(['t1', 't2', 't3'], { status: 'done' }, 2)
		expect(result).toEqual({ done: ['t1', 't3'], failed: ['t2'] })
		expect(fetch).toHaveBeenCalledTimes(3)
	})
})

describe('bulk assign and labels (tasks-search-and-bulk 2.3)', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('builds the patch per task and skips a task that already matches', async () => {
		const tasks = { t1: { labels: ['a'] }, t2: { labels: ['a', 'b'] }, t3: {} }
		const bodies = {}
		vi.stubGlobal('fetch', vi.fn(async (url, init) => {
			bodies[url.split('/').pop()] = JSON.parse(init.body)
			return { ok: true, json: async () => ({}) }
		}))

		const result = await store.bulkUpdateTasks(['t1', 't2', 't3'], (id) => labelChangePatch(tasks[id], 'b', 'add'))

		expect([...result.done].sort()).toEqual(['t1', 't2', 't3'])
		expect(result.failed).toEqual([])
		expect(bodies).toEqual({ t1: { labels: ['a', 'b'] }, t3: { labels: ['b'] } })
	})

	it('keeps a task whose write failed as failed while the others are updated (assign to Bram)', async () => {
		vi.stubGlobal('fetch', vi.fn(async (url) => (url.endsWith('/t2') ? { ok: false, json: async () => ({}) } : { ok: true, json: async () => ({}) })))

		const result = await store.bulkUpdateTasks(['t1', 't2', 't3'], () => responsiblePatch({ assignedTo: '' }, 'bram'))

		expect([...result.done].sort()).toEqual(['t1', 't3'])
		expect(result.failed).toEqual(['t2'])
	})
})

describe('labelChangePatch', () => {
	it('adds a label once and keeps the others', () => {
		expect(labelChangePatch({ labels: ['a'] }, 'b', 'add')).toEqual({ labels: ['a', 'b'] })
		expect(labelChangePatch({ labels: ['a', 'b'] }, 'b', 'add')).toEqual({})
	})

	it('removes a label and leaves a task without it alone', () => {
		expect(labelChangePatch({ labels: ['a', 'b'] }, 'b', 'remove')).toEqual({ labels: ['a'] })
		expect(labelChangePatch({ labels: ['a'] }, 'b', 'remove')).toEqual({})
		expect(labelChangePatch({}, 'b', 'remove')).toEqual({})
	})
})

describe('responsiblePatch for the bulk Assign to', () => {
	it('sets the assignee, clears it for an empty id, and writes nothing when unchanged', () => {
		expect(responsiblePatch({ assignedTo: 'anna' }, 'bram')).toEqual({ assignedTo: 'bram' })
		expect(responsiblePatch({ assignedTo: 'anna' }, '')).toEqual({ assignedTo: '' })
		expect(responsiblePatch({ assignedTo: 'bram' }, 'bram')).toEqual({})
	})
})
