/**
 * Vitest unit tests for templates and project copy
 * (projects-templates-shared-workflow 1.3): the parts a copy keeps, the
 * request body, the template list and the copyProject store action.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { consistentParts, copyPayload, defaultCopyParts, mayCopyProject, templateOptions } from '../../src/utils/projectCopy.js'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'olga' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { useProjectsStore } = await import('../../src/store/projects.js')

describe('copyPayload (scenario: copying a project without its people)', () => {
	it('keeps the structure and leaves the people behind by default', () => {
		expect(defaultCopyParts()).toEqual({ columns: true, phases: true, tasks: true, dependencies: true, people: false })
		expect(copyPayload({ title: ' Kopie ', parts: { people: false } }).parts.people).toBe(false)
	})

	it('trims the title, upper-cases the key and sends the start date only when given', () => {
		expect(copyPayload({ title: ' Wegbeheer ', key: ' weg ', startDate: '2026-06-01' })).toMatchObject({ title: 'Wegbeheer', key: 'WEG', startDate: '2026-06-01' })
		const bare = copyPayload({ title: 'x', key: '', startDate: '' })
		expect(bare).not.toHaveProperty('key')
		expect(bare).not.toHaveProperty('startDate')
	})

	it('drops dependencies when the tasks do not come along', () => {
		expect(consistentParts({ tasks: false, dependencies: true }).dependencies).toBe(false)
		expect(consistentParts({ tasks: true, dependencies: true }).dependencies).toBe(true)
	})
})

describe('templateOptions (scenario: starting a project from a template)', () => {
	it('lists the templates by title and leaves out working projects and archived templates', () => {
		const projects = [
			{ id: 'p1', title: 'Zomerschool', isTemplate: true, startDate: '2026-03-01' },
			{ id: 'p2', title: 'Aanbesteding', isTemplate: true },
			{ id: 'p3', title: 'Wegbeheer', isTemplate: false },
			{ id: 'p4', title: 'Oud', isTemplate: true, status: 'archived' },
		]
		expect(templateOptions(projects).map((option) => option.id)).toEqual(['p2', 'p1'])
		expect(templateOptions(null)).toEqual([])
	})
})

describe('mayCopyProject', () => {
	it('is for the owner and managers', () => {
		expect(['owner', 'manager'].every(mayCopyProject)).toBe(true)
		expect(['member', 'viewer', 'none'].some(mayCopyProject)).toBe(false)
	})
})

describe('copyProject store action', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('posts the body to the copy endpoint of the source and returns the new id', async () => {
		const fetchMock = vi.fn(async () => ({ ok: true, json: async () => ({ id: 'new-1', counts: { tasks: 12 } }) }))
		vi.stubGlobal('fetch', fetchMock)

		const result = await store.copyProject('tpl-1', copyPayload({ title: 'Aanbesteding wegbeheer', startDate: '2026-06-01' }))

		expect(result.id).toBe('new-1')
		expect(fetchMock.mock.calls[0][0]).toBe('/apps/planninq/api/projects/tpl-1/copy')
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toMatchObject({ title: 'Aanbesteding wegbeheer', startDate: '2026-06-01' })
		expect(store.loading).toBe(false)
	})

	it('throws with the server message and code when the copy is refused', async () => {
		vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, json: async () => ({ error: 'This key is already used by another project.', code: 'planninq-project-key-used' }) })))

		await expect(store.copyProject('tpl-1', { title: 'x', parts: {} })).rejects.toMatchObject({ code: 'planninq-project-key-used' })
		expect(store.loading).toBe(false)
	})
})
