/**
 * The projects store's lifecycle and phase actions: the project key check,
 * the live-updated project list, shipping a release, the phase list, phase
 * reordering and closing, the available actions and the archive and restore
 * transitions. Only fetch, the object store and the Nextcloud helpers are
 * faked; the phase and lifecycle helpers are the real ones.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/projects/spec.md
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const world = vi.hoisted(() => ({ uid: 'anna', collection: [] }))

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ 'X-Test': '1' }) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => (world.uid ? { uid: world.uid } : null) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: () => [] }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({
		registerObjectType: () => {},
		objectTypeRegistry: {},
		fetchCollection: async (schema, filters) => {
			if (world.collection instanceof Error) {
				throw world.collection
			}
			return filters?._page > 1 ? [] : world.collection
		},
	}),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

/**
 * A fetch answer.
 *
 * @param {boolean} ok Whether it succeeded.
 * @param {*} body The JSON body.
 * @param {number} status The HTTP status.
 * @return {object} The response.
 */
function answer(ok, body = {}, status = ok ? 200 : 400) {
	return { ok, status, json: async () => body, text: async () => JSON.stringify(body) }
}

describe('projects store lifecycle and phases', () => {
	let store
	let sent

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		world.uid = 'anna'
		world.collection = []
		sent = []
		vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	/**
	 * Answer fetches in order, recording each request.
	 *
	 * @param {...object} answers The answers.
	 */
	function serve(...answers) {
		globalThis.fetch = vi.fn(async (url, init = {}) => {
			sent.push([url, init.method ?? 'GET', init.body ? JSON.parse(init.body) : null])
			const next = answers.shift()
			if (next instanceof Error) {
				throw next
			}
			return next ?? answer(true)
		})
	}

	it('checks a project key and answers null when the check fails', async () => {
		serve(answer(true, { valid: true, available: false }), answer(false), new Error('offline'))

		expect(await store.checkProjectKey('PLN & co')).toEqual({ valid: true, available: false })
		expect(sent[0][0]).toBe('/apps/planninq/api/projects/key-available?key=PLN%20%26%20co')
		expect(await store.checkProjectKey('X')).toBe(null)
		expect(await store.checkProjectKey('X')).toBe(null)
	})

	it('keeps only the live projects the user may see, or all of them when nobody is signed in', () => {
		const list = [
			{ id: 'p1', owner: 'anna', members: ['anna'] },
			{ id: 'p2', owner: 'bram', members: ['bram'] },
		]
		expect(store.applyLiveProjects(list).map((p) => p.id)).toEqual(['p1'])
		expect(store.projects.map((p) => p.id)).toEqual(['p1'])
		expect(store.applyLiveProjects('odd')).toEqual([])

		world.uid = null
		expect(store.applyLiveProjects(list)).toHaveLength(2)
	})

	it('ships a release with the choice for its open tasks', async () => {
		serve(answer(true), answer(false), new Error('offline'))

		expect(await store.shipRelease({ id: 'r1' }, 'move', 'r2')).toEqual({ ok: true })
		expect(sent[0]).toEqual(['/apps/planninq/api/releases/r1/ship', 'POST', { choice: 'move', target: 'r2' }])
		expect(await store.shipRelease({ id: 'r1' }, 'keep')).toEqual({ ok: false })
		expect(await store.shipRelease({ id: 'r1' }, 'keep')).toEqual({ ok: false })
	})

	it('lists a project\'s phases, and none when the read fails', async () => {
		world.collection = [{ id: 'ph1' }, { id: 'ph2' }]
		expect(await store.fetchPhases('p1')).toEqual([{ id: 'ph1' }, { id: 'ph2' }])

		world.collection = new Error('down')
		expect(await store.fetchPhases('p1')).toEqual([])
	})

	it('swaps two phases\' order, and reports a failed or impossible move', async () => {
		const phases = [{ id: 'a', order: 1 }, { id: 'b', order: 2 }, { id: 'c', order: 3 }]
		serve(answer(true, { id: 'b' }), answer(true, { id: 'c' }))
		expect(await store.reorderPhase(phases, 'b', 1)).toBe(true)
		expect(sent.map(([url, method, body]) => [url.split('/').pop(), method, body]).sort()).toEqual([
			['b', 'PATCH', { order: 3 }],
			['c', 'PATCH', { order: 2 }],
		])

		serve(answer(true, {}), answer(false, { message: 'nope' }, 403))
		expect(await store.reorderPhase(phases, 'a', 1)).toBe(false)

		expect(await store.reorderPhase(phases, 'c', 1)).toBe(false)
	})

	it('closes a phase with its concluding document', async () => {
		serve(answer(true, { id: 'ph1', status: 'completed' }))

		expect(await store.closePhase('ph1', 42)).toEqual({ ok: true, phase: { id: 'ph1', status: 'completed' } })
		expect(sent[0]).toEqual(['/apps/openregister/api/objects/planninq/projectPhase/ph1', 'PATCH', { concludingDocument: '42', status: 'completed' }])
	})

	it('reads the available actions, leaving out blocked ones', async () => {
		serve(answer(true, { actions: [{ action: 'archive' }, { action: 'restore', blocked: true }] }), answer(false), new Error('x'))

		expect(await store.fetchProjectActions('p 1')).toEqual(['archive'])
		expect(sent[0][0]).toBe('/apps/openregister/api/objects/p%201/available-actions')
		expect(await store.fetchProjectActions('p1')).toBe(null)
		expect(await store.fetchProjectActions('p1')).toBe(null)
	})

	it('archives a project out of the active list, and restores one through its transition', async () => {
		store.projects = [{ id: 'p1', title: 'One' }, { id: 'p2', title: 'Two' }]
		serve(answer(true, { id: 'p1', lifecycle: 'archived' }), answer(false, { error: 'transition-not-allowed' }, 409), answer(true, { lifecycle: 'active' }))

		const archived = await store.archiveProject('p1')
		expect(archived).toMatchObject({ id: 'p1', lifecycle: 'archived' })
		expect(sent[0]).toEqual(['/apps/openregister/api/objects/p1/transition', 'POST', expect.objectContaining({ action: 'archive' })])
		expect(store.projects.map((p) => p.id)).toEqual(['p2'])

		expect(await store.archiveProject('p2')).toBe(null)
		expect(store.error).toBe('transition-not-allowed')
		expect(store.projects.map((p) => p.id)).toEqual(['p2'])

		expect(await store.restoreProject('p1')).toMatchObject({ id: 'p1', lifecycle: 'active' })
		expect(sent[2][2]).toEqual(expect.objectContaining({ action: 'restore' }))
	})
})
