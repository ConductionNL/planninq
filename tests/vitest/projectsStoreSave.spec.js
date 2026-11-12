/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for the projects store's plain save actions: POST a new
 * record, PATCH the given fields of an existing one, null on a refusal or a
 * network failure, which is logged under the action's name.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ 'X-Test': '1' }) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {}, fetchCollection: async () => [] }),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

const ACTIONS = [
	['saveColumn', 'column'],
	['saveLogEntry', 'projectLogEntry'],
	['saveRisk', 'risk'],
	['saveFinanceLine', 'financeLine'],
	['saveBoardFilter', 'boardFilter'],
	['saveBoardView', 'boardView'],
	['saveRelease', 'projectRelease'],
]

describe('projects store: plain save actions', () => {
	let store
	let calls
	let answer
	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		calls = []
		answer = { ok: true, status: 200 }
		globalThis.fetch = vi.fn(async (url, init) => {
			calls.push([init.method, url, init.headers, init.body])
			if (answer === 'throw') {
				throw new Error('offline')
			}
			return { ...answer, json: async () => ({ saved: url }) }
		})
	})
	afterEach(() => {
		delete globalThis.fetch
		vi.restoreAllMocks()
	})

	it.each(ACTIONS)('%s creates, edits, and answers null when refused or offline', async (action, schema) => {
		const errors = vi.spyOn(console, 'error').mockImplementation(() => {})
		const base = `/apps/openregister/api/objects/planninq/${schema}`

		const created = await store[action]({ title: 'New' })
		const edited = await store[action]({ id: 'x1', title: 'Edited' })
		answer = { ok: false, status: 403 }
		const refused = await store[action]({ id: 'x1', title: 'Again' })
		answer = 'throw'
		const offline = await store[action]({ title: 'Lost' })

		expect([created, edited, refused, offline]).toEqual([{ saved: base }, { saved: `${base}/x1` }, null, null])
		expect(calls).toEqual([
			['POST', base, { 'X-Test': '1' }, '{"title":"New"}'],
			['PATCH', `${base}/x1`, { 'X-Test': '1' }, '{"title":"Edited"}'],
			['PATCH', `${base}/x1`, { 'X-Test': '1' }, '{"title":"Again"}'],
			['POST', base, { 'X-Test': '1' }, '{"title":"Lost"}'],
		])
		expect(errors.mock.calls.map(([label, error]) => [label, error.message])).toEqual([[`${action} error:`, 'offline']])
	})
})
