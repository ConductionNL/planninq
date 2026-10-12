/**
 * Vitest unit tests for the wiki editor and the one-writer rule
 * (projects-wiki 2.2, 4.2): the Text editor path and the Markdown fallback,
 * the lock calls, and a save refused because the page changed meanwhile.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { canRestoreWiki, canWriteWiki, expectedUpdated, filterPages, historyEntries, lockedByOther, lockOwner, openTextEditor, savePayload, textEditorAvailable, wikiSidebarConfig } from '../../src/utils/wiki.js'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({
		objectTypeRegistry: {},
		registerObjectType: () => {},
		fetchCollection: async (schema, params) => (params._page === 1 ? [{ id: 'p1', title: 'Een' }] : []),
	}),
}))

const { useWikiStore } = await import('../../src/store/wiki.js')

describe('editor choice (task 2.2)', () => {
	it('uses the Text app editor when it exists and passes the Markdown out', async () => {
		const destroy = vi.fn()
		const createEditor = vi.fn(async (options) => {
			options.onUpdate({ markdown: '# Nieuw' })
			return { destroy }
		})
		const win = { OCA: { Text: { createEditor } } }
		const seen = []

		expect(textEditorAvailable(win)).toBe(true)
		const editor = await openTextEditor(win, { el: {}, content: '# Oud', readOnly: false, onUpdate: (markdown) => seen.push(markdown) })

		expect(createEditor).toHaveBeenCalledWith(expect.objectContaining({ content: '# Oud', readOnly: false }))
		expect(seen).toEqual(['# Nieuw'])
		editor.destroy()
		expect(destroy).toHaveBeenCalled()
	})

	it('falls back to the Markdown field when the Text app is off or its editor throws', async () => {
		expect(textEditorAvailable({})).toBe(false)
		expect(await openTextEditor({}, { el: {}, content: '' })).toBeNull()
		const failing = async () => {
			throw new Error('API changed')
		}
		const broken = { OCA: { Text: { createEditor: failing } } }
		expect(await openTextEditor(broken, { el: {}, content: '' })).toBeNull()
	})
})

describe('who writes (tasks 2.4 and 4.1)', () => {
	it('shows writing controls to owners, managers and members, never to viewers', () => {
		expect(['owner', 'manager', 'member'].every(canWriteWiki)).toBe(true)
		expect(['viewer', 'none'].some(canWriteWiki)).toBe(false)
		expect(canRestoreWiki('manager')).toBe(true)
		expect(canRestoreWiki('member')).toBe(false)
	})
})

describe('lock and version helpers (task 4.2)', () => {
	it('reads the lock holder from either shape and tells another writer from oneself', () => {
		expect(lockOwner({ locked: { user: 'ada' } })).toBe('ada')
		expect(lockOwner({ '@self': { locked: 'bram' } })).toBe('bram')
		expect(lockOwner({})).toBe('')
		expect(lockedByOther({ locked: { user: 'ada' } }, 'bram')).toBe(true)
		expect(lockedByOther({ locked: { user: 'ada' } }, 'ada')).toBe(false)
		expect(lockedByOther({}, 'ada')).toBe(false)
	})

	it('sends the version the page was loaded at with every save', () => {
		const page = { id: 'p', '@self': { updated: '2026-10-01T10:00:00Z' } }
		expect(expectedUpdated(page)).toBe('2026-10-01T10:00:00Z')
		expect(savePayload(page, { title: ' Titel ', body: 'tekst' })).toEqual({ title: 'Titel', body: 'tekst', _expectedUpdated: '2026-10-01T10:00:00Z' })
		expect(savePayload({ id: 'p' }, { title: 'T', body: '' })).not.toHaveProperty('_expectedUpdated')
	})
})

describe('wiki store (task 4.2)', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useWikiStore()
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('loads the pages of a project', async () => {
		expect((await store.fetchPages('proj-1')).map((page) => page.id)).toEqual(['p1'])
		expect(store.projectId).toBe('proj-1')
	})

	it('takes and releases the lock on the page endpoints', async () => {
		const calls = []
		vi.stubGlobal('fetch', vi.fn(async (target, init) => {
			calls.push(`${init.method} ${target}`)
			return { ok: true, status: 200, json: async () => ({}) }
		}))

		expect(await store.lock('p1')).toEqual({ ok: true, page: null })
		expect(await store.unlock('p1')).toBe(true)
		expect(calls).toEqual([
			'POST /apps/openregister/api/objects/planninq/wikiPage/p1/lock',
			'POST /apps/openregister/api/objects/planninq/wikiPage/p1/unlock',
		])
	})

	it('returns the page with its lock holder when the lock is refused', async () => {
		vi.stubGlobal('fetch', vi.fn(async (target, init) => (init.method === 'POST'
			? { ok: false, status: 423, json: async () => ({}) }
			: { ok: true, status: 200, json: async () => ({ id: 'p1', locked: { user: 'ada' } }) })))

		const result = await store.lock('p1')

		expect(result.ok).toBe(false)
		expect(lockOwner(result.page)).toBe('ada')
	})

	it('reports a save over a newer version as a conflict and keeps the stored page', async () => {
		store.pages = [{ id: 'p1', title: 'Een', body: 'oud' }]
		const bodies = []
		vi.stubGlobal('fetch', vi.fn(async (target, init) => {
			bodies.push(JSON.parse(init.body))
			return { ok: false, status: 409, json: async () => ({}) }
		}))

		const result = await store.savePage({ id: 'p1', '@self': { updated: 'T1' } }, { title: 'Een', body: 'nieuw' })

		expect(result).toEqual({ page: null, conflict: true })
		expect(bodies[0]._expectedUpdated).toBe('T1')
		expect(store.pages[0].body).toBe('oud')
	})

	it('moves the subpages of a deleted page up a level unless they go with it', async () => {
		store.pages = [{ id: 'a', parent: null }, { id: 'b', parent: 'a' }, { id: 'c', parent: 'b' }]
		const calls = []
		vi.stubGlobal('fetch', vi.fn(async (target, init) => {
			calls.push(`${init.method} ${target.split('/').pop()} ${init.body ?? ''}`.trim())
			return { ok: true, status: 200, json: async () => ({ id: target.split('/').pop(), parent: 'a' }) }
		}))

		expect(await store.deletePage('b', 'promote')).toBe(true)
		expect(calls).toEqual(['PATCH c {"parent":"a"}', 'DELETE b'])
		expect(store.pages.map((page) => page.id)).toEqual(['a', 'c'])

		calls.length = 0
		store.pages = [{ id: 'a', parent: null }, { id: 'b', parent: 'a' }, { id: 'c', parent: 'b' }]
		await store.deletePage('b', 'cascade')
		expect(calls).toEqual(['DELETE c', 'DELETE b'])
		expect(store.pages.map((page) => page.id)).toEqual(['a'])
	})
})

describe('history, search and attachments', () => {
	it('lists the history newest first', () => {
		const entries = historyEntries([{ id: '1', user: 'ada', created: '2026-10-01T10:00:00Z' }, { id: '2', user: 'bram', created: '2026-10-02T10:00:00Z', action: 'revert' }])
		expect(entries.map((entry) => entry.id)).toEqual(['2', '1'])
		expect(entries[0].action).toBe('revert')
	})

	it('searches titles and bodies and keeps the pages above a hit', () => {
		const pages = [{ id: 'a', title: 'Handboek' }, { id: 'b', title: 'Release', parent: 'a', body: 'Freeze op dinsdag' }, { id: 'c', title: 'Overig' }]
		expect(filterPages(pages, 'freeze').map((page) => page.id)).toEqual(['a', 'b'])
		expect(filterPages(pages, '  ').length).toBe(3)
		expect(filterPages(pages, 'zzz')).toEqual([])
	})

	it('puts the attachments tab of the object sidebar on the page', () => {
		expect(wikiSidebarConfig({ id: 'p1' })).toMatchObject({ objectId: 'p1', register: 'planninq', schema: 'wikiPage', objectType: 'planninq-wikiPage' })
		expect(wikiSidebarConfig(null).objectId).toBe('')
	})
})
