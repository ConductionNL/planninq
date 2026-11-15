/**
 * bulkUpdateTasks: change several backlog tasks at once, with a per-task
 * result and at most BULK_CONCURRENCY PATCHes in flight
 * (tasks-search-and-bulk, task 2.2). Only fetch and the Nextcloud helpers are
 * faked.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.2
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ 'X-Test': '1' }) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {}, fetchCollection: vi.fn() }),
}))

const { BULK_CONCURRENCY, useProjectsStore } = await import('../../src/store/projects.js')

/**
 * The task id a PATCH url names.
 *
 * @param {string} url The url
 * @return {string}
 */
function idOf(url) {
	return url.split('/').pop()
}

describe('bulkUpdateTasks', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	it('PATCHes every task with the patch and reports the one that failed', async () => {
		const sent = []
		globalThis.fetch = vi.fn(async (url, init) => {
			sent.push([idOf(url), init.method, JSON.parse(init.body)])
			if (idOf(url) === 't2') {
				return { ok: false, json: async () => ({ message: 'Object not found' }) }
			}
			return { ok: true, json: async () => ({ id: idOf(url), status: 'in_progress' }) }
		})

		const outcome = await store.bulkUpdateTasks(['t1', 't2', 't3'], { status: 'in_progress' })

		expect(sent.map(([id, method, body]) => [id, method, body]).sort()).toEqual([
			['t1', 'PATCH', { status: 'in_progress' }],
			['t2', 'PATCH', { status: 'in_progress' }],
			['t3', 'PATCH', { status: 'in_progress' }],
		])
		expect(outcome.updated).toEqual(['t1', 't3'])
		expect(outcome.failed).toEqual(['t2'])
		expect(outcome.results).toEqual([
			{ id: 't1', ok: true, task: { id: 't1', status: 'in_progress' } },
			{ id: 't2', ok: false, task: null },
			{ id: 't3', ok: true, task: { id: 't3', status: 'in_progress' } },
		])
	})

	it('asks a function for each task\'s own patch, and a throwing or empty answer fails that task only', async () => {
		globalThis.fetch = vi.fn(async (url, init) => ({ ok: true, json: async () => ({ id: idOf(url), ...JSON.parse(init.body) }) }))
		const labels = { t1: ['urgent'], t2: [], t3: ['x'] }
		const op = (id) => {
			if (id === 't3') {
				throw new Error('task gone')
			}
			if (id === 't4') {
				return null
			}
			return { labels: [...labels[id], 'review'] }
		}

		const outcome = await store.bulkUpdateTasks(['t1', 't2', 't3', 't4'], op)

		expect(outcome.updated).toEqual(['t1', 't2'])
		expect(outcome.failed).toEqual(['t3', 't4'])
		expect(outcome.results[0].task).toEqual({ id: 't1', labels: ['urgent', 'review'] })
		expect(globalThis.fetch).toHaveBeenCalledTimes(2)
	})

	it('keeps at most BULK_CONCURRENCY PATCHes in flight', async () => {
		let inFlight = 0
		let most = 0
		globalThis.fetch = vi.fn(async (url) => {
			inFlight++
			most = Math.max(most, inFlight)
			await new Promise((resolve) => setTimeout(resolve, 1))
			inFlight--
			return { ok: true, json: async () => ({ id: idOf(url) }) }
		})
		const ids = Array.from({ length: 10 }, (unused, index) => `t${index}`)

		const outcome = await store.bulkUpdateTasks(ids, { priority: 'high' })

		expect(BULK_CONCURRENCY).toBe(4)
		expect(most).toBe(BULK_CONCURRENCY)
		expect(outcome.updated).toEqual(ids)
	})

	it('answers nothing for an empty selection without a request', async () => {
		globalThis.fetch = vi.fn()

		expect(await store.bulkUpdateTasks([], { status: 'done' })).toEqual({ updated: [], failed: [], results: [] })
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})
