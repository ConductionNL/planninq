/**
 * Deleting a project ends its board links (projects-public-share task 3.2):
 * after the server deleted the project, the store revokes the project's links
 * and deletes its share view, with only fetch and the Nextcloud globals stood in for.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const showError = vi.fn()

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: (...args) => showError(...args) }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { useProjectsStore } = await import('../../src/store/projects.js')

const VIEW = { id: 12, uuid: 'v-fiets', query: { registers: ['planninq'], schemas: ['column', 'task'], filters: { project: 'p-1' } } }

/**
 * A fetch stand-in that answers each call from a list, and records the calls.
 *
 * @param {Array<[number, object]>} answers status and body per call, in order
 * @return {Function} the mock
 */
function answering(answers) {
	const queue = [...answers]
	const mock = vi.fn(async () => {
		const [status, body] = queue.shift() ?? [500, {}]
		return { ok: status >= 200 && status < 300, status, text: async () => JSON.stringify(body), json: async () => body }
	})
	globalThis.fetch = mock
	return mock
}

/**
 * The calls a fetch mock received, as `METHOD url`.
 *
 * @param {Function} fetch the mock
 * @return {string[]}
 */
function calls(fetch) {
	return fetch.mock.calls.map(([url, init]) => `${init?.method ?? 'GET'} ${url}`)
}

beforeEach(() => {
	setActivePinia(createPinia())
	showError.mockClear()
})

afterEach(() => {
	delete globalThis.fetch
})

describe('deleteProject and the project\'s board links', () => {
	it('revokes each live link on the project\'s view and then deletes the view', async () => {
		const fetch = answering([
			[200, {}],
			[200, { results: [VIEW] }],
			[200, { results: [{ id: 7, subjectType: 'view', subjectId: 'v-fiets', live: true }, { id: 9, subjectType: 'view', subjectId: 'v-fiets', live: true }] }],
			[200, {}],
			[200, {}],
			[200, {}],
		])
		const store = useProjectsStore()
		store.projects = [{ id: 'p-1', title: 'Fietspad' }]

		expect(await store.deleteProject('p-1')).toBe(true)

		expect(calls(fetch)).toEqual([
			'DELETE /apps/planninq/api/projects/p-1',
			'GET /apps/openregister/api/views',
			'GET /apps/openregister/api/access-links',
			'DELETE /apps/openregister/api/access-links/7',
			'DELETE /apps/openregister/api/access-links/9',
			'DELETE /apps/openregister/api/views/v-fiets',
		])
		expect(store.projects).toEqual([])
		expect(showError).not.toHaveBeenCalled()
	})

	it('touches no link or view when the server keeps the project', async () => {
		const fetch = answering([[403, { code: 'forbidden' }]])
		const store = useProjectsStore()

		expect(await store.deleteProject('p-1')).toBe(false)

		expect(calls(fetch)).toEqual(['DELETE /apps/planninq/api/projects/p-1'])
	})

	it('still reports the project deleted, with a warning, when a link cannot be switched off', async () => {
		answering([
			[200, {}],
			[200, { results: [VIEW] }],
			[200, { results: [{ id: 7, subjectType: 'view', subjectId: 'v-fiets', live: true }] }],
			[404, {}],
			[200, {}],
		])
		const store = useProjectsStore()

		expect(await store.deleteProject('p-1')).toBe(true)

		expect(showError).toHaveBeenCalledWith('The project is deleted, but its board links could not all be switched off.')
	})
})
