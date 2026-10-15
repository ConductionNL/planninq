/**
 * Vitest tests for the projects store's release actions
 * (backlog-releases-roadmap), with the object store and fetch replaced.
 *
 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const collections = {}
vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (m, k) => vars[k] ?? m) }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({
		registerObjectType: () => {},
		objectTypeRegistry: {},
		fetchCollection: async (schema, filters) => (filters._page > 1 ? [] : (collections[schema] || []).filter((row) => !filters.project || row.project === filters.project)),
	}),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

describe('projects store: releases', () => {
	let store
	let calls
	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		for (const key of Object.keys(collections)) {
			delete collections[key]
		}
		calls = []
		globalThis.fetch = vi.fn(async (url, init) => {
			calls.push([init.method, url, JSON.parse(init.body)])
			return { ok: true, status: 200, json: async () => ({ id: url.split('/').pop(), ...JSON.parse(init.body) }) }
		})
	})
	afterEach(() => {
		delete globalThis.fetch
	})

	it('reads the releases of one project', async () => {
		collections.projectRelease = [{ id: 'r1', project: 'p1' }, { id: 'r9', project: 'p2' }]
		expect((await store.fetchReleases('p1')).map((release) => release.id)).toEqual(['r1'])
	})

	it('creates a release with POST and edits one with PATCH', async () => {
		await store.saveRelease({ title: 'Version 2.0', project: 'p1', status: 'planned' })
		await store.saveRelease({ id: 'r1', title: 'Version 2.1' })
		expect(calls).toEqual([
			['POST', '/apps/openregister/api/objects/planninq/projectRelease', { title: 'Version 2.0', project: 'p1', status: 'planned' }],
			['PATCH', '/apps/openregister/api/objects/planninq/projectRelease/r1', { title: 'Version 2.1' }],
		])
	})

	it('ship with unfinished tasks moves them to the chosen release, then marks the release', async () => {
		const tasks = [{ id: 't1', release: 'r1', status: 'done' }, { id: 't2', release: 'r1', status: 'open' }]
		const result = await store.shipRelease({ id: 'r1' }, tasks, 'move', 'r2')
		expect(result).toEqual({ ok: true })
		expect(calls.map(([method, url, body]) => [method, url.split('/').slice(-2).join('/'), body.release ?? body.status])).toEqual([
			['PATCH', 'task/t2', 'r2'],
			['PATCH', 'projectRelease/r1', 'released'],
		])
	})

	it('ship with everything done only sets status', async () => {
		const result = await store.shipRelease({ id: 'r1' }, [{ id: 't1', release: 'r1', status: 'cancelled' }], 'keep', null)
		expect(result).toEqual({ ok: true })
		expect(calls).toHaveLength(1)
		expect(calls[0][2]).toMatchObject({ status: 'released' })
		expect(Object.keys(calls[0][2]).sort()).toEqual(['releasedAt', 'status'])
	})

	it('does not mark the release when a task could not be moved', async () => {
		globalThis.fetch = vi.fn(async (url, init) => {
			calls.push([init.method, url])
			return { ok: false, status: 403, json: async () => ({}) }
		})
		const result = await store.shipRelease({ id: 'r1' }, [{ id: 't2', release: 'r1', status: 'open' }], 'clear', null)
		expect(result).toEqual({ ok: false })
		expect(calls).toHaveLength(1)
	})

	it('epic picker refuses another project\'s epic, and sends only the epic field', async () => {
		store.updateTask = vi.fn(async (id, patch) => ({ id, ...patch }))
		expect(await store.setTaskEpic({ id: 't1', project: 'p1' }, { id: 'e2', project: 'p2', issueType: 'epic' })).toEqual({ ok: false, reason: 'other-project' })
		expect(store.updateTask).not.toHaveBeenCalled()
		expect(await store.setTaskEpic({ id: 't1', project: 'p1' }, { id: 'e1', project: 'p1', issueType: 'epic' })).toMatchObject({ ok: true })
		expect(store.updateTask.mock.calls).toEqual([['t1', { epic: 'e1' }]])
	})

	it('sets and clears a task\'s release with only that field', async () => {
		store.updateTask = vi.fn(async (id, patch) => ({ id, ...patch }))
		await store.setTaskRelease('t1', 'r1')
		await store.setTaskRelease('t1', null)
		expect(store.updateTask.mock.calls).toEqual([['t1', { release: 'r1' }], ['t1', { release: null }]])
	})
})
