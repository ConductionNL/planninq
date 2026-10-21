/**
 * Vitest tests for the projects store's duplicate and parent-delete actions
 * (tasks-subtasks-checklist), with the object store and fetch replaced.
 *
 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.1
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

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
		fetchCollection: async (schema, filters) => (filters._page > 1 ? [] : (collections[schema] || []).filter((row) => !filters.task || row.task === filters.task)),
	}),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

describe('projects store: duplicate and delete a task tree', () => {
	let store
	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		for (const key of Object.keys(collections)) {
			delete collections[key]
		}
	})

	it('copies a task tree in one server request (scenario: duplicate a task tree)', async () => {
		globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({ id: 'new-1', title: 'Copy of Prepare' }) }))
		const copy = await store.duplicateTask({ id: 't1', title: 'Prepare' })

		expect(copy.id).toBe('new-1')
		expect(globalThis.fetch.mock.calls[0][0]).toBe('/apps/planninq/api/tasks/t1/duplicate')
		expect(globalThis.fetch.mock.calls[0][1].method).toBe('POST')
	})

	it('deletes a tree in one server request with the subtask choice (scenario: keep the subtasks)', async () => {
		globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({ deleted: 1 }) }))
		expect(await store.deleteTaskTree({ id: 't1' }, 'detach')).toEqual({ deleted: true })
		expect(globalThis.fetch.mock.calls[0][0]).toBe('/apps/planninq/api/tasks/t1?subtasks=detach')
		expect(globalThis.fetch.mock.calls[0][1].method).toBe('DELETE')
	})

	it('names the server\'s refusal: logged time or not allowed', async () => {
		globalThis.fetch = vi.fn(async () => ({ ok: false, json: async () => ({ code: 'planninq-task-has-logged-time' }) }))
		expect(await store.deleteTaskTree({ id: 't1' }, 'delete')).toEqual({ deleted: false, reason: 'has-time' })

		globalThis.fetch = vi.fn(async () => ({ ok: false, json: async () => ({ code: 'planninq-task-delete-not-allowed' }) }))
		expect(await store.deleteTaskTree({ id: 't1' }, 'delete')).toEqual({ deleted: false, reason: 'not-allowed' })
	})
	it('deletes a project in one server request and drops it from the list', async () => {
		store.projects = [{ id: 'p1' }, { id: 'p2' }]
		globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({ deleted: 12 }) }))
		expect(await store.deleteProject('p1')).toBe(true)
		expect(globalThis.fetch.mock.calls[0]).toEqual(['/apps/planninq/api/projects/p1', { method: 'DELETE', headers: {} }])
		expect(store.projects.map((project) => project.id)).toEqual(['p2'])

		globalThis.fetch = vi.fn(async () => ({ ok: false, status: 403, json: async () => ({ code: 'planninq-project-delete-not-allowed' }) }))
		expect(await store.deleteProject('p2')).toBe(false)
		expect(store.projects.map((project) => project.id)).toEqual(['p2'])
	})
	it('moves a phase in one server request', async () => {
		globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ([]) }))
		expect(await store.reorderPhase('p1', 'ph-2', -1)).toBe(true)
		expect(globalThis.fetch.mock.calls[0][0]).toBe('/apps/planninq/api/projects/p1/phases/move')
		expect(JSON.parse(globalThis.fetch.mock.calls[0][1].body)).toEqual({ phase: 'ph-2', step: -1 })
	})

	it('creates and links a log entry action in one server request', async () => {
		globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({ task: { id: 't9' }, entry: { id: 'log-1', actions: ['t9'] } }) }))
		const result = await store.addLogAction('log-1', { title: 'Send the planning' })
		expect(result.entry.actions).toEqual(['t9'])
		expect(globalThis.fetch.mock.calls[0][0]).toBe('/apps/planninq/api/log-entries/log-1/actions')
		expect(JSON.parse(globalThis.fetch.mock.calls[0][1].body)).toEqual({ task: { title: 'Send the planning' } })

		globalThis.fetch = vi.fn(async () => ({ ok: false, json: async () => ({}) }))
		expect(await store.addLogAction('log-1', { title: 'Again' })).toBeNull()
	})
})
