/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The wiki page editor's session (projects-wiki, tasks 2.2 and 4.2): the Text
 * app's editor when it is there and the Markdown field when it is not or
 * fails, the lock taken on opening an existing page and released once on
 * save, cancel or close, and a save that sends the `updated` time the writer
 * opened and keeps the lock when it is refused. Also who holds a page's lock.
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-page-keeps-its-history-and-one-writer-at-a-time
 */
import { describe, expect, it, vi } from 'vitest'
import { lockHolder } from '../../src/api/wikiPages.js'
import { createEditingSession, openedUpdated, startTextEditor } from '../../src/utils/wikiEditing.js'
import { textEditorFactory } from '../../src/utils/wikiTree.js'

vi.mock('@nextcloud/axios', () => ({ default: {} }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (url) => url }))

const PAGE = { id: 'p1', title: 'Werkafspraken', body: 'Oud', '@self': { updated: '2026-11-14T10:00:00+00:00' } }

function api() {
	return {
		lock: vi.fn().mockResolvedValue({}),
		unlock: vi.fn().mockResolvedValue(undefined),
		save: vi.fn().mockImplementation(async (page) => ({ ...page, '@self': { updated: 'later' } })),
	}
}

describe('the Text editor or the Markdown fallback', () => {
	it('uses the Text app editor when it is there, and passes every change on', async () => {
		const editor = { destroy: vi.fn() }
		let onUpdate = null
		const factory = vi.fn().mockImplementation(async (options) => {
			onUpdate = options.onUpdate
			return editor
		})
		const changes = []
		const el = {}
		expect(await startTextEditor(factory, el, 'Oud', (markdown) => changes.push(markdown))).toBe(editor)
		expect(factory).toHaveBeenCalledWith(expect.objectContaining({ el, content: 'Oud' }))
		onUpdate({ markdown: 'Nieuw' })
		expect(changes).toEqual(['Nieuw'])
	})

	it('falls back to the Markdown field without the Text app or when its editor fails', async () => {
		expect(await startTextEditor(null, {}, 'x', () => {})).toBeNull()
		expect(await startTextEditor(vi.fn().mockRejectedValue(new Error('api changed')), {}, 'x', () => {})).toBeNull()
		expect(await startTextEditor(vi.fn(), null, 'x', () => {})).toBeNull()
	})

	it('finds the factory on window.OCA.Text only when it is a function', () => {
		expect(textEditorFactory()).toBeNull()
		globalThis.OCA = { Text: { createEditor: () => {} } }
		expect(typeof textEditorFactory()).toBe('function')
		globalThis.OCA = { Text: {} }
		expect(textEditorFactory()).toBeNull()
		delete globalThis.OCA
	})
})

describe('the editing session', () => {
	it('locks an existing page on open and saves with the updated time it opened', async () => {
		const calls = api()
		const session = createEditingSession(PAGE, calls)
		expect(await session.open()).toEqual({ ok: true })
		expect(calls.lock).toHaveBeenCalledWith('p1')
		expect(session.holdsLock()).toBe(true)

		const result = await session.save({ title: 'Werkafspraken', body: 'Nieuw' })
		expect(calls.save).toHaveBeenCalledWith({ id: 'p1', title: 'Werkafspraken', body: 'Nieuw' }, '2026-11-14T10:00:00+00:00')
		expect(result.ok).toBe(true)
		expect(calls.unlock).toHaveBeenCalledTimes(1)
		expect(session.holdsLock()).toBe(false)

		session.cancel()
		session.release()
		expect(calls.unlock).toHaveBeenCalledTimes(1)
	})

	it('keeps the lock and reports a conflict when someone saved in between', async () => {
		const calls = api()
		calls.save.mockRejectedValue({ response: { status: 409 } })
		const session = createEditingSession(PAGE, calls)
		await session.open()
		expect(await session.save({ title: 'T', body: 'B' })).toEqual({ ok: false, conflict: true })
		expect(calls.unlock).not.toHaveBeenCalled()
		expect(session.holdsLock()).toBe(true)

		calls.save.mockRejectedValue({ response: { status: 500 } })
		expect(await session.save({ title: 'T', body: 'B' })).toEqual({ ok: false, conflict: false })
	})

	it('releases the lock on cancel and on close', async () => {
		const calls = api()
		const session = createEditingSession(PAGE, calls)
		await session.open()
		session.cancel()
		expect(calls.unlock).toHaveBeenCalledWith('p1')
		const closing = createEditingSession(PAGE, calls)
		await closing.open()
		expect(closing.release()).toBe(true)
		expect(calls.unlock).toHaveBeenCalledTimes(2)
	})

	it('reports a refused lock and never unlocks what it did not take', async () => {
		const calls = api()
		calls.lock.mockRejectedValue({ response: { status: 423, data: { message: 'Locked by ada' } } })
		const session = createEditingSession(PAGE, calls)
		expect(await session.open()).toEqual({ ok: false, refusal: { message: 'Locked by ada' } })
		session.cancel()
		expect(calls.unlock).not.toHaveBeenCalled()
	})

	it('creates a new page without a lock or an expected time', async () => {
		const calls = api()
		const draft = { project: 'proj-1', parent: null, order: 1, title: '', body: '' }
		const session = createEditingSession(draft, calls)
		expect(await session.open()).toEqual({ ok: true })
		expect(calls.lock).not.toHaveBeenCalled()
		await session.save({ title: 'Werkafspraken', body: 'Tekst' })
		expect(calls.save).toHaveBeenCalledWith({ project: 'proj-1', parent: null, order: 1, title: 'Werkafspraken', body: 'Tekst' }, null)
		expect(openedUpdated({ updated: 'x' })).toBe('x')
	})
})

describe('who holds a page lock', () => {
	it('names the holder until the lock expires', () => {
		const now = new Date('2026-11-14T12:00:00Z')
		const locked = { '@self': { locked: { user: 'ada', displayName: 'Ada Jansen', expiration: '2026-11-14T12:30:00Z' } } }
		expect(lockHolder(locked, now)).toEqual({ user: 'ada', displayName: 'Ada Jansen' })
		expect(lockHolder({ '@self': { locked: { user: 'ada', expiration: '2026-11-14T11:00:00Z' } } }, now)).toBeNull()
		expect(lockHolder({ '@self': { locked: null } }, now)).toBeNull()
		expect(lockHolder({ locked: { userId: 'bob' } }, now)).toEqual({ user: 'bob', displayName: 'bob' })
	})
})
