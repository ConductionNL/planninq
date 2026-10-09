import { buildHeaders } from '@conduction/nextcloud-vue'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import { historyEntries, movePatches, pageId, reorderPatches, savePayload, subtreeIds } from '../utils/wiki.js'
import { useObjectStore } from './objectStore.js'

const REGISTER = 'planninq'
const SCHEMA = 'wikiPage'
const PAGE_SIZE = 100

/**
 * The OpenRegister object endpoint of a wiki page, or the collection.
 *
 * @param {string} id The page id; empty for the collection.
 * @param {string} suffix A trailing path such as `/lock`.
 * @return {string}
 */
function url(id = '', suffix = '') {
	const base = `/apps/openregister/api/objects/${REGISTER}/${SCHEMA}`
	return generateUrl(id ? `${base}/${id}${suffix}` : base)
}

/**
 * Wiki Pinia store (projects-wiki).
 *
 * Reads and writes `wikiPage` objects through OpenRegister's object API, and
 * uses its lock, audit trail and revert endpoints for the one-writer rule and
 * the history.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
 */
export const useWikiStore = defineStore('wiki', {
	state: () => ({
		/** @type {Array<object>} The pages of the open project. */
		pages: [],
		loading: false,
		/** @type {string} The project the pages belong to. */
		projectId: '',
	}),

	actions: {
		/**
		 * Load every page of a project.
		 *
		 * @param {string} projectId The project UUID.
		 * @return {Promise<Array<object>>} The pages (empty array on error)
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		async fetchPages(projectId) {
			this.loading = true
			this.projectId = projectId
			try {
				const objectStore = useObjectStore()
				if (!objectStore.objectTypeRegistry?.[SCHEMA]) {
					objectStore.registerObjectType(SCHEMA, SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: SCHEMA })
				}
				const pages = []
				const seen = new Set()
				for (let page = 1; ; page++) {
					const batch = await objectStore.fetchCollection(SCHEMA, { project: projectId, _limit: PAGE_SIZE, _page: page })
					const rows = Array.isArray(batch) ? batch : []
					const fresh = rows.filter((row) => !seen.has(pageId(row)))
					fresh.forEach((row) => seen.add(pageId(row)))
					pages.push(...fresh)
					if (fresh.length === 0 || rows.length < PAGE_SIZE) {
						break
					}
				}
				this.pages = pages
				return pages
			} catch (error) {
				console.error('fetchPages error:', error)
				this.pages = []
				return []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Replace one page in the list with a newer copy.
		 *
		 * @param {object} page The page as the server returned it.
		 * @return {void}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		remember(page) {
			const id = pageId(page)
			this.pages = this.pages.some((row) => pageId(row) === id)
				? this.pages.map((row) => pageId(row) === id ? { ...row, ...page } : row)
				: [...this.pages, page]
		},

		/**
		 * Write a request to the object API and return the parsed answer.
		 *
		 * @param {string} method HTTP verb.
		 * @param {string} target The endpoint.
		 * @param {object|undefined} body The JSON body.
		 * @return {Promise<{ok: boolean, status: number, data: object}>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		async send(method, target, body) {
			const response = await fetch(target, { method, headers: buildHeaders(), body: body === undefined ? undefined : JSON.stringify(body) })
			const data = await response.json().catch(() => ({}))
			return { ok: response.ok, status: response.status, data }
		},

		/**
		 * Add a page, last among its siblings.
		 *
		 * @param {{title: string, parent?: string}} draft The new page.
		 * @return {Promise<object|null>} The page, or null when it was refused
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-2.1
		 */
		async createPage(draft) {
			const siblings = this.pages.filter((page) => String(page.parent ?? '') === String(draft.parent ?? ''))
			const order = siblings.reduce((max, page) => Math.max(max, Number(page.order) || 0), -1) + 1
			const result = await this.send('POST', url(), {
				title: draft.title.trim(),
				body: '',
				project: this.projectId,
				parent: draft.parent || null,
				order,
			})
			if (!result.ok) {
				return null
			}
			this.remember(result.data)
			return result.data
		},

		/**
		 * Save the title and body of a page on the version they were edited on.
		 *
		 * @param {object} page The page as it was loaded.
		 * @param {{title: string, body: string}} draft The edited fields.
		 * @return {Promise<{page: object|null, conflict: boolean}>} The saved page, or a conflict when the page changed meanwhile
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		async savePage(page, draft) {
			const result = await this.send('PATCH', url(pageId(page)), savePayload(page, draft))
			if (!result.ok) {
				return { page: null, conflict: result.status === 409 || result.status === 412 }
			}
			this.remember(result.data)
			return { page: result.data, conflict: false }
		},

		/**
		 * Write the changed fields of several pages.
		 *
		 * @param {Array<object>} patches Each `{ id, ...fields }`.
		 * @return {Promise<boolean>} Whether every write was accepted
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		async patchMany(patches) {
			let allOk = true
			for (const { id, ...fields } of patches) {
				const result = await this.send('PATCH', url(id), fields)
				if (result.ok) {
					this.remember(result.data)
				} else {
					allOk = false
				}
			}
			return allOk
		},

		/**
		 * Move a page under another page, or to the top level.
		 *
		 * @param {string} id The page.
		 * @param {string} newParent The new parent id, '' for the top level.
		 * @return {Promise<boolean>} Whether the move was made
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
		 */
		async movePage(id, newParent) {
			const patches = movePatches(this.pages, id, newParent)
			return patches.length > 0 && await this.patchMany(patches)
		},

		/**
		 * Move a page one place up or down among its siblings.
		 *
		 * @param {string} id The page.
		 * @param {-1|1} direction -1 up, 1 down.
		 * @return {Promise<boolean>} Whether anything moved
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
		 */
		async reorderPage(id, direction) {
			const patches = reorderPatches(this.pages, id, direction)
			return patches.length > 0 && await this.patchMany(patches)
		},

		/**
		 * Delete a page. Its subpages move up a level, or go with it.
		 *
		 * @param {string} id The page.
		 * @param {'promote'|'cascade'} mode What happens to the subpages.
		 * @return {Promise<boolean>} Whether the page is gone
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
		 */
		async deletePage(id, mode) {
			const page = this.pages.find((row) => pageId(row) === id)
			const below = [...subtreeIds(this.pages, id)].filter((other) => other !== id)
			if (mode === 'cascade') {
				for (const other of below.reverse()) {
					await this.send('DELETE', url(other))
				}
			} else {
				const parent = String(page?.parent ?? '')
				const children = this.pages.filter((row) => String(row.parent ?? '') === id)
				if (!(await this.patchMany(children.map((child) => ({ id: pageId(child), parent: parent || null }))))) {
					return false
				}
			}
			const result = await this.send('DELETE', url(id))
			if (result.ok) {
				const gone = new Set(mode === 'cascade' ? [id, ...below] : [id])
				this.pages = this.pages.filter((row) => !gone.has(pageId(row)))
			}
			return result.ok
		},

		/**
		 * Take the lock on a page before editing it.
		 *
		 * @param {string} id The page.
		 * @return {Promise<{ok: boolean, page: object|null}>} Whether the lock is ours, and the page with the lock holder when it is not
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		async lock(id) {
			const result = await this.send('POST', url(id, '/lock'), {})
			if (result.ok) {
				return { ok: true, page: null }
			}
			const fresh = await this.send('GET', url(id))
			if (fresh.ok) {
				this.remember(fresh.data)
			}
			return { ok: false, page: fresh.ok ? fresh.data : null }
		},

		/**
		 * Release the lock on a page.
		 *
		 * @param {string} id The page.
		 * @return {Promise<boolean>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
		 */
		async unlock(id) {
			return (await this.send('POST', url(id, '/unlock'), {})).ok
		},

		/**
		 * The page's history, newest first, from OpenRegister's audit trail.
		 *
		 * @param {string} id The page.
		 * @return {Promise<Array<object>>}
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
		 */
		async history(id) {
			const result = await this.send('GET', url(id, '/audit-trails'))
			const rows = Array.isArray(result.data) ? result.data : (result.data?.results ?? [])
			return result.ok ? historyEntries(rows) : []
		},

		/**
		 * Restore an earlier version through OpenRegister's revert; the restore is a new history entry.
		 *
		 * @param {string} id The page.
		 * @param {string} entryId The audit trail entry to go back to.
		 * @return {Promise<object|null>} The restored page, or null when it was refused
		 *
		 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
		 */
		async restore(id, entryId) {
			const result = await this.send('POST', url(id, '/revert'), { auditTrailId: entryId })
			if (!result.ok) {
				return null
			}
			const fresh = await this.send('GET', url(id))
			if (fresh.ok) {
				this.remember(fresh.data)
			}
			return fresh.ok ? fresh.data : null
		},
	},
})
