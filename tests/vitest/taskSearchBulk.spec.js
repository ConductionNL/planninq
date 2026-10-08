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
