/**
 * The project wiki (projects-wiki): the page tree, moves and reorders, the
 * keyboard handling of the tree, the editor choice and the lock and history
 * helpers. Everything here is pure, so the tree behaves the same in the page
 * and in the tests.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */

/**
 * The id of a page, wherever the response put it.
 *
 * @param {object} page A wiki page.
 * @return {string}
 */
export function pageId(page) {
	return String(page?.id ?? page?.uuid ?? page?.['@self']?.id ?? '')
}

/**
 * The parent id of a page, or '' for a top-level page.
 *
 * @param {object} page A wiki page.
 * @return {string}
 */
export function parentId(page) {
	const parent = page?.parent
	return String((parent && typeof parent === 'object' ? parent.id : parent) ?? '')
}

/**
 * Sort pages among their siblings: by order, then by title.
 *
 * @param {Array<object>} pages The pages.
 * @return {Array<object>} A new array.
 */
function sortSiblings(pages) {
	return [...pages].sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0) || String(a.title ?? '').localeCompare(String(b.title ?? '')))
}

/**
 * The pages as a tree. A page whose parent is missing from the list is shown
 * at the top level, so a page is never lost.
 *
 * @param {Array<object>} pages Every page of the project.
 * @return {Array<{page: object, children: Array}>} The top-level nodes
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
export function buildTree(pages) {
	const known = new Set((pages || []).map(pageId))
	const byParent = new Map()
	for (const page of pages || []) {
		const parent = known.has(parentId(page)) && parentId(page) !== pageId(page) ? parentId(page) : ''
		byParent.set(parent, [...(byParent.get(parent) || []), page])
	}
	const build = (parent, seen) => sortSiblings(byParent.get(parent) || [])
		.filter((page) => !seen.has(pageId(page)))
		.map((page) => ({ page, children: build(pageId(page), new Set([...seen, pageId(page)])) }))
	return build('', new Set())
}

/**
 * The tree items in the order they are shown: a node's children follow it
 * only when it is open.
 *
 * @param {Array<{page: object, children: Array}>} tree The tree from buildTree.
 * @param {Set<string>} open Ids of the open pages.
 * @return {Array<{id: string, level: number, hasChildren: boolean, expanded: boolean, parent: string}>}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
export function visibleItems(tree, open) {
	const items = []
	const walk = (nodes, level, parent) => {
		for (const node of nodes) {
			const id = pageId(node.page)
			const expanded = node.children.length > 0 && open.has(id)
			items.push({ id, level, hasChildren: node.children.length > 0, expanded, parent })
			if (expanded) {
				walk(node.children, level + 1, id)
			}
		}
	}
	walk(tree, 1, '')
	return items
}

/**
 * The pages from the top of the tree down to a page, the page last.
 *
 * @param {Array<object>} pages Every page of the project.
 * @param {string} id The page.
 * @return {Array<object>} The path; empty when the page is unknown
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
 */
export function breadcrumb(pages, id) {
	const byId = new Map((pages || []).map((page) => [pageId(page), page]))
	const path = []
	const seen = new Set()
	let current = byId.get(String(id))
	while (current && !seen.has(pageId(current))) {
		path.unshift(current)
		seen.add(pageId(current))
		current = byId.get(parentId(current))
	}
	return path
}

/**
 * The ids of a page and everything under it.
 *
 * @param {Array<object>} pages Every page of the project.
 * @param {string} id The page.
 * @return {Set<string>}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
 */
export function subtreeIds(pages, id) {
	const ids = new Set([String(id)])
	let grew = true
	while (grew) {
		grew = false
		for (const page of pages || []) {
			if (!ids.has(pageId(page)) && ids.has(parentId(page))) {
				ids.add(pageId(page))
				grew = true
			}
		}
	}
	return ids
}

/**
 * The pages a page may move under: not itself, not one of its subpages, and
 * nothing that is already its parent.
 *
 * @param {Array<object>} pages Every page of the project.
 * @param {string} id The page to move.
 * @return {Array<object>} The allowed new parents, in tree order
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
 */
export function moveTargets(pages, id) {
	const blocked = subtreeIds(pages, id)
	const current = parentId((pages || []).find((page) => pageId(page) === String(id)))
	const order = []
	const flatten = (nodes) => nodes.forEach((node) => {
		order.push(node.page)
		flatten(node.children)
	})
	flatten(buildTree(pages))
	return order.filter((page) => !blocked.has(pageId(page)) && pageId(page) !== current)
}

/**
 * The patches that put a page last under a new parent.
 *
 * @param {Array<object>} pages Every page of the project.
 * @param {string} id The page to move.
 * @param {string} newParent The new parent id, '' for the top level.
 * @return {Array<{id: string, parent: string|null, order: number}>} One patch, or none for a refused move
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.2
 */
export function movePatches(pages, id, newParent) {
	if (newParent !== '' && !moveTargets(pages, id).some((page) => pageId(page) === newParent)) {
		const stays = parentId((pages || []).find((page) => pageId(page) === String(id))) === newParent
		if (!stays) {
			return []
		}
	}
	const siblings = (pages || []).filter((page) => parentId(page) === newParent && pageId(page) !== String(id))
	const last = siblings.reduce((max, page) => Math.max(max, Number(page.order) || 0), -1)
	return [{ id: String(id), parent: newParent || null, order: last + 1 }]
}

/**
 * The patches that move a page one place up or down among its siblings. The
 * siblings are renumbered 0..n so equal orders never leave a page stuck.
 *
 * @param {Array<object>} pages Every page of the project.
 * @param {string} id The page.
 * @param {-1|1} direction -1 for up, 1 for down.
 * @return {Array<{id: string, order: number}>} Patches for the siblings whose order changes
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
export function reorderPatches(pages, id, direction) {
	const page = (pages || []).find((candidate) => pageId(candidate) === String(id))
	if (!page) {
		return []
	}
	const siblings = sortSiblings((pages || []).filter((candidate) => parentId(candidate) === parentId(page)))
	const from = siblings.findIndex((candidate) => pageId(candidate) === String(id))
	const to = from + direction
	if (to < 0 || to >= siblings.length) {
		return []
	}
	const next = [...siblings]
	next.splice(to, 0, next.splice(from, 1)[0])
	return next
		.map((candidate, order) => ({ id: pageId(candidate), order, was: Number(candidate.order) || 0 }))
		.filter((patch) => patch.order !== patch.was)
		.map(({ id: patchId, order }) => ({ id: patchId, order }))
}

/**
 * What a key press does on the tree, as an ARIA tree pattern: Up and Down move
 * between visible items, Right opens a closed item or enters its first child,
 * Left closes an open item or goes to its parent, Home and End jump, Enter opens
 * the page.
 *
 * @param {Array<object>} items The visible items from visibleItems.
 * @param {string} focused Id of the focused item.
 * @param {string} key The `KeyboardEvent.key`.
 * @return {{focus?: string, expand?: string, collapse?: string, open?: string}|null} The action, or null for an unhandled key
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
export function treeKeyAction(items, focused, key) {
	const index = items.findIndex((item) => item.id === focused)
	const item = items[index]
	if (!item) {
		return items.length && ['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(key) ? { focus: items[0].id } : null
	}
	switch (key) {
		case 'ArrowDown':
			return index + 1 < items.length ? { focus: items[index + 1].id } : {}
		case 'ArrowUp':
			return index > 0 ? { focus: items[index - 1].id } : {}
		case 'Home':
			return { focus: items[0].id }
		case 'End':
			return { focus: items[items.length - 1].id }
		case 'ArrowRight':
			if (item.hasChildren && !item.expanded) {
				return { expand: item.id }
			}
			return item.hasChildren && items[index + 1] ? { focus: items[index + 1].id } : {}
		case 'ArrowLeft':
			if (item.expanded) {
				return { collapse: item.id }
			}
			return item.parent ? { focus: item.parent } : {}
		case 'Enter':
		case ' ':
			return { open: item.id }
		default:
			return null
	}
}

/**
 * Whether the Text app's editor can be used on this page.
 *
 * @param {object} win The window object.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 */
export function textEditorAvailable(win) {
	return typeof win?.OCA?.Text?.createEditor === 'function'
}

/**
 * Open the editor for a page body: the Text app's editor when it is there,
 * else nothing (the caller shows its Markdown field).
 *
 * @param {object} win The window object.
 * @param {object} options `{ el, content, readOnly, onUpdate(markdown) }`.
 * @return {Promise<{destroy: function(): void}|null>} The editor, or null for the fallback
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.2
 */
export async function openTextEditor(win, options) {
	if (!textEditorAvailable(win)) {
		return null
	}
	try {
		const editor = await win.OCA.Text.createEditor({
			el: options.el,
			content: options.content,
			readOnly: options.readOnly === true,
			onUpdate: (update) => options.onUpdate?.(typeof update === 'string' ? update : (update?.markdown ?? '')),
		})
		return editor && typeof editor.destroy === 'function' ? editor : { destroy: () => {} }
	} catch {
		return null
	}
}

/**
 * Who holds the lock on a page, or '' when nobody does.
 *
 * @param {object} page A wiki page as OpenRegister returns it.
 * @return {string} The user id
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
 */
export function lockOwner(page) {
	const lock = page?.locked ?? page?.['@self']?.locked
	if (!lock) {
		return ''
	}
	return String(typeof lock === 'object' ? (lock.user ?? lock.userId ?? '') : lock)
}

/**
 * Whether the page is being edited by someone else.
 *
 * @param {object} page A wiki page.
 * @param {string} uid The current user.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
 */
export function lockedByOther(page, uid) {
	const owner = lockOwner(page)
	return owner !== '' && owner !== uid
}

/**
 * The version marker a save sends, so a save over a newer version is refused.
 *
 * @param {object} page The page as it was loaded.
 * @return {string|undefined}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
 */
export function expectedUpdated(page) {
	return page?.['@self']?.updated ?? page?.updated ?? undefined
}

/**
 * The body of a save: the changed fields and the version they were made on.
 *
 * @param {object} page The page as it was loaded.
 * @param {{title: string, body: string}} draft The edited fields.
 * @return {object}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.2
 */
export function savePayload(page, draft) {
	const payload = { title: String(draft.title ?? '').trim(), body: String(draft.body ?? '') }
	const updated = expectedUpdated(page)
	if (updated) {
		payload._expectedUpdated = updated
	}
	return payload
}

/**
 * The history entries of a page, newest first, from OpenRegister's audit trail.
 *
 * @param {Array<object>} entries The audit trail rows.
 * @return {Array<{id: string, user: string, created: string, action: string}>}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
 */
export function historyEntries(entries) {
	return (entries || [])
		.map((entry) => ({
			id: String(entry.id ?? entry.uuid ?? ''),
			user: String(entry.user ?? entry.userName ?? ''),
			created: String(entry.created ?? entry.createdAt ?? ''),
			action: String(entry.action ?? 'update'),
		}))
		.sort((a, b) => b.created.localeCompare(a.created))
}

/**
 * The pages that match a search term in the title or the body, with the pages
 * above them, so a hit still shows where it sits in the tree.
 *
 * @param {Array<object>} pages Every page of the project.
 * @param {string} term The search term.
 * @return {Array<object>} The pages to show; all of them for an empty term
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
export function filterPages(pages, term) {
	const needle = String(term ?? '').trim().toLowerCase()
	if (needle === '') {
		return pages || []
	}
	const keep = new Set()
	for (const page of pages || []) {
		if (String(page.title ?? '').toLowerCase().includes(needle) || String(page.body ?? '').toLowerCase().includes(needle)) {
			breadcrumb(pages, pageId(page)).forEach((ancestor) => keep.add(pageId(ancestor)))
		}
	}
	return (pages || []).filter((page) => keep.has(pageId(page)))
}

/**
 * The props that put CnObjectSidebar on a wiki page for its attachments.
 * The page's own history and the editor replace the notes and audit tabs.
 *
 * @param {object|null} page The page.
 * @return {object} Props to spread onto CnObjectSidebar
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.3
 */
export function wikiSidebarConfig(page) {
	return {
		objectId: page ? pageId(page) : '',
		register: 'planninq',
		schema: 'wikiPage',
		objectType: 'planninq-wikiPage',
		useRegistry: false,
		hiddenTabs: ['tags', 'tasks', 'notes', 'auditTrail'],
	}
}

/**
 * Whether the person sees writing controls: the roles that may change tasks.
 * A viewer reads only; the server refuses a viewer's write as well.
 *
 * @param {string} role The person's role on the project (see projectRole).
 * @return {boolean}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-2.4
 */
export function canWriteWiki(role) {
	return ['owner', 'manager', 'member'].includes(role)
}

/**
 * Whether the person may restore an earlier version: the project's managers.
 *
 * @param {string} role The person's role on the project (see projectRole).
 * @return {boolean}
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-4.1
 */
export function canRestoreWiki(role) {
	return role === 'owner' || role === 'manager'
}
