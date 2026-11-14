// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
// SPDX-License-Identifier: EUPL-1.2

/**
 * The project wiki's page tree (projects-wiki, decision 2): pages by parent
 * and order, the rows an ARIA tree shows, the targets a move may offer, the
 * breadcrumb and the order patches of "Move up" and "Move down". Pure, so the
 * tree, the move dialog and the page share one reading of the pages.
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-organises-pages-in-a-tree
 */
import DOMPurify from 'dompurify'
import { marked } from 'marked'

/**
 * A page's id, wherever the response put it.
 *
 * @param {object} page The page.
 * @return {string}
 */
export function pageId(page) {
	return String(page?.id || page?.uuid || page?.['@self']?.id || '')
}

/**
 * A page's parent id, or '' for a top-level page.
 *
 * @param {object} page The page.
 * @return {string}
 */
export function parentOf(page) {
	const parent = page?.parent
	if (parent && typeof parent === 'object') {
		return pageId(parent)
	}
	return typeof parent === 'string' ? parent : ''
}

/**
 * Siblings in their order, then by title.
 *
 * @param {object} a A page.
 * @param {object} b Another page.
 * @return {number}
 */
function bySiblingOrder(a, b) {
	const order = (Number(a.order) || 0) - (Number(b.order) || 0)
	return order !== 0 ? order : String(a.title || '').localeCompare(String(b.title || ''))
}

/**
 * The children of a parent, in order. A page whose parent is not among the
 * pages is shown at the top, so no page is lost from the tree.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {string} parent The parent id, '' for the top level.
 * @return {Array<object>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-organises-pages-in-a-tree
 */
export function childrenOf(pages, parent) {
	const ids = new Set(pages.map(pageId))
	return pages
		.filter((page) => {
			const own = parentOf(page)
			return parent === '' ? own === '' || !ids.has(own) : own === parent
		})
		.sort(bySiblingOrder)
}

/**
 * The ids of a page's subpages, at any depth.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {string} id The page.
 * @return {Set<string>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-page-cannot-move-under-its-own-subpage
 */
export function descendantsOf(pages, id) {
	const found = new Set()
	const queue = [id]
	while (queue.length > 0) {
		const current = queue.shift()
		for (const page of pages) {
			const child = pageId(page)
			if (parentOf(page) === current && !found.has(child) && child !== id) {
				found.add(child)
				queue.push(child)
			}
		}
	}
	return found
}

/**
 * The rows the tree shows: every top-level page, and the children of each
 * expanded page, with their depth, whether they have children and whether
 * they are expanded.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {Set<string>} expanded The expanded page ids.
 * @return {Array<{id: string, title: string, level: number, hasChildren: boolean, expanded: boolean, parent: string}>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-browsing-the-tree-by-keyboard
 */
export function visibleRows(pages, expanded) {
	const rows = []
	const walk = (parent, level) => {
		for (const page of childrenOf(pages, parent)) {
			const id = pageId(page)
			const hasChildren = childrenOf(pages, id).length > 0 && parent !== id
			const open = hasChildren && expanded.has(id)
			rows.push({ id, title: String(page.title || ''), level, hasChildren, expanded: open, parent })
			if (open && level < 50) {
				walk(id, level + 1)
			}
		}
	}
	walk('', 1)
	return rows
}

/**
 * The ids from the top down to a page, the page last.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {string} id The page.
 * @return {Array<string>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-moving-a-page-under-another-page
 */
export function pathTo(pages, id) {
	const byId = new Map(pages.map((page) => [pageId(page), page]))
	const path = []
	let current = byId.get(id)
	while (current && !path.includes(pageId(current))) {
		path.unshift(pageId(current))
		current = byId.get(parentOf(current))
	}
	return path
}

/**
 * The breadcrumb of a page: its ancestors' titles and its own, joined.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {string} id The page.
 * @return {string}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-moving-a-page-under-another-page
 */
export function breadcrumb(pages, id) {
	const byId = new Map(pages.map((page) => [pageId(page), page]))
	return pathTo(pages, id).map((step) => String(byId.get(step)?.title || '')).join(' / ')
}

/**
 * The pages a page may move under: every page of the project except the page
 * itself and its subpages, as the parent guard refuses those.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {string} id The page to move.
 * @return {Array<object>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-page-cannot-move-under-its-own-subpage
 */
export function moveTargets(pages, id) {
	const excluded = descendantsOf(pages, id)
	excluded.add(id)
	return pages.filter((page) => !excluded.has(pageId(page)))
}

/**
 * The order a new last child of a parent gets.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {string} parent The parent id, '' for the top level.
 * @return {number}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-organises-pages-in-a-tree
 */
export function nextOrder(pages, parent) {
	const siblings = childrenOf(pages, parent)
	return siblings.reduce((max, page) => Math.max(max, Number(page.order) || 0), 0) + 1
}

/**
 * The patches that move a page one place up or down among its siblings:
 * every sibling gets its position as its order, with the two swapped, so
 * siblings that shared an order are put in a stable order too. Only changed
 * orders are patched; none when the page is already first or last.
 *
 * @param {Array<object>} pages The project's pages.
 * @param {string} id The page.
 * @param {-1|1} direction -1 for up, 1 for down.
 * @return {Array<{id: string, order: number}>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-organises-pages-in-a-tree
 */
export function reorderPatches(pages, id, direction) {
	const page = pages.find((candidate) => pageId(candidate) === id)
	if (!page) {
		return []
	}
	const siblings = childrenOf(pages, parentOf(page)).map(pageId)
	const from = siblings.indexOf(id)
	const to = from + direction
	if (from < 0 || to < 0 || to >= siblings.length) {
		return []
	}
	[siblings[from], siblings[to]] = [siblings[to], siblings[from]]
	const byId = new Map(pages.map((candidate) => [pageId(candidate), candidate]))
	return siblings
		.map((sibling, index) => ({ id: sibling, order: index + 1 }))
		.filter((patch) => Number(byId.get(patch.id)?.order) !== patch.order)
}

/**
 * A page body as HTML, before sanitising.
 *
 * @param {string} markdown The body.
 * @return {string}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-writing-the-first-page
 */
export function markdownHtml(markdown) {
	return marked.parse(String(markdown || ''), { async: false })
}

/**
 * A page body as safe HTML. Without a DOM to sanitise in, the HTML is
 * escaped rather than passed through, so a body never reaches the page
 * unsanitised.
 *
 * @param {string} markdown The body.
 * @return {string}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-writing-the-first-page
 */
export function renderBody(markdown) {
	const html = markdownHtml(markdown)
	if (typeof DOMPurify.sanitize !== 'function') {
		return html.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
	}
	return DOMPurify.sanitize(html)
}

/**
 * The Text app's editor factory, `window.OCA.Text.createEditor`, when the
 * Text app offers it; null otherwise, and the page uses its Markdown field.
 *
 * @return {((options: object) => Promise<object>)|null}
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 */
export function textEditorFactory() {
	const factory = globalThis.OCA?.Text?.createEditor
	return typeof factory === 'function' ? factory : null
}

/**
 * What a key does in the page tree (WAI-ARIA tree view): which row gets the
 * focus, which row opens or closes, which page opens. Null for a key the
 * tree leaves alone.
 *
 * @param {Array<{id: string, hasChildren: boolean, expanded: boolean, parent: string}>} rows The visible rows.
 * @param {number} index The focused row.
 * @param {string} key The key name.
 * @return {{focus: number|null, toggle: string|null, open: string|null}|null}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-browsing-the-tree-by-keyboard
 */
export function treeKey(rows, index, key) {
	const row = rows[index]
	if (!row) {
		return null
	}
	const clamp = (to) => (to >= 0 && to < rows.length ? to : null)
	const action = { focus: null, toggle: null, open: null }
	switch (key) {
		case 'ArrowDown':
			return { ...action, focus: clamp(index + 1) }
		case 'ArrowUp':
			return { ...action, focus: clamp(index - 1) }
		case 'Home':
			return { ...action, focus: 0 }
		case 'End':
			return { ...action, focus: rows.length - 1 }
		case 'ArrowRight':
			if (row.hasChildren && !row.expanded) {
				return { ...action, toggle: row.id }
			}
			return { ...action, focus: row.expanded ? clamp(index + 1) : null }
		case 'ArrowLeft':
			if (row.expanded) {
				return { ...action, toggle: row.id }
			}
			return { ...action, focus: row.parent ? clamp(rows.findIndex((candidate) => candidate.id === row.parent)) : null }
		case 'Enter':
			return { ...action, open: row.id }
		default:
			return null
	}
}
