// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
// SPDX-License-Identifier: EUPL-1.2

/**
 * One editing session of a wiki page (projects-wiki, decisions 1 and 4): the
 * Text app's editor or the Markdown fallback, the page lock taken on open and
 * released once on save, cancel or close, and a save that carries the
 * `updated` time the writer opened so it cannot overwrite a newer version.
 * The API calls are passed in, so the session is the same in the component
 * and in its tests.
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-page-keeps-its-history-and-one-writer-at-a-time
 */
import { pageId } from './wikiTree.js'

/**
 * Start the Text app's editor on an element, or answer null so the page
 * shows its Markdown field: when the Text app is missing or its editor fails.
 *
 * @param {((options: object) => Promise<object>)|null} factory `window.OCA.Text.createEditor`, or null.
 * @param {HTMLElement|null} el The element to mount on.
 * @param {string} content The Markdown to start from.
 * @param {function(string): void} onChange Called with the Markdown on every change.
 * @return {Promise<object|null>} The editor, or null for the fallback.
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 */
export async function startTextEditor(factory, el, content, onChange) {
	if (typeof factory !== 'function' || !el) {
		return null
	}
	try {
		const editor = await factory({
			el,
			content,
			onUpdate: ({ markdown }) => onChange(String(markdown ?? '')),
		})
		return editor || null
	} catch {
		return null
	}
}

/**
 * The `updated` time of the page as the writer opened it.
 *
 * @param {object} page The page.
 * @return {string|null}
 */
export function openedUpdated(page) {
	return page?.['@self']?.updated || page?.updated || null
}

/**
 * A session over one page.
 *
 * @param {object} page The page, or the fields of a new one.
 * @param {{lock: (id: string) => Promise<object>, unlock: (id: string) => Promise<void>, save: (page: object, expectedUpdated: (string|null)) => Promise<object>}} api The calls.
 * @return {object} The session: open, save, cancel, release and holdsLock.
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
 */
export function createEditingSession(page, api) {
	const id = pageId(page)
	let holds = false

	const release = () => {
		if (!holds) {
			return false
		}
		holds = false
		Promise.resolve(api.unlock(id)).catch(() => {})
		return true
	}

	return {
		/**
		 * Take the lock of an existing page. A new page needs none.
		 *
		 * @return {Promise<{ok: boolean, refusal?: object|null}>}
		 */
		async open() {
			if (!id) {
				return { ok: true }
			}
			try {
				await api.lock(id)
				holds = true
				return { ok: true }
			} catch (exception) {
				return { ok: false, refusal: exception?.response?.data ?? null }
			}
		},

		/**
		 * Save the fields; release the lock only when the save went through.
		 *
		 * @param {{title: string, body: string}} fields The new title and body.
		 * @return {Promise<{ok: boolean, saved?: object, conflict?: boolean}>}
		 */
		async save(fields) {
			const target = id ? { id, ...fields } : { ...page, ...fields }
			try {
				const saved = await api.save(target, id ? openedUpdated(page) : null)
				release()
				return { ok: true, saved }
			} catch (exception) {
				const status = exception?.response?.status
				return { ok: false, conflict: status === 409 || status === 412 }
			}
		},

		/** Leave without saving. */
		cancel() {
			release()
		},

		release,

		/** @return {boolean} */
		holdsLock() {
			return holds
		},
	}
}
