/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The project wiki's page tree (projects-wiki, task 3.1 and 3.2): pages in
 * sibling order, the rows the ARIA tree shows, its keyboard handling, the
 * move targets that leave out a page and its subpages, the breadcrumb, and
 * the order patches of "Move up" and "Move down".
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-organises-pages-in-a-tree
 */
import { describe, expect, it } from 'vitest'
import {
	breadcrumb,
	childrenOf,
	descendantsOf,
	markdownHtml,
	moveTargets,
	nextOrder,
	pageId,
	renderBody,
	reorderPatches,
	treeKey,
	visibleRows,
} from '../../src/utils/wikiTree.js'

const PAGES = [
	{ id: 'w', title: 'Werkafspraken', order: 1 },
	{ id: 'o', title: 'Overleg', order: 2 },
	{ id: 'a', title: 'Agenda', parent: 'w', order: 2 },
	{ id: 'n', title: 'Notulen', parent: { id: 'w' }, order: 1 },
	{ id: 'd', title: 'Dinsdag', parent: 'n', order: 1 },
	{ id: 'x', title: 'Wees', parent: 'gone', order: 3 },
]

describe('wiki page tree', () => {
	it('orders siblings by order, and keeps a page whose parent is gone at the top', () => {
		expect(childrenOf(PAGES, '').map(pageId)).toEqual(['w', 'o', 'x'])
		expect(childrenOf(PAGES, 'w').map(pageId)).toEqual(['n', 'a'])
	})

	it('shows only open branches, with levels and expanded state', () => {
		expect(visibleRows(PAGES, new Set()).map((row) => row.id)).toEqual(['w', 'o', 'x'])
		const rows = visibleRows(PAGES, new Set(['w']))
		expect(rows.map((row) => [row.id, row.level, row.hasChildren, row.expanded])).toEqual([
			['w', 1, true, true],
			['n', 2, true, false],
			['a', 2, false, false],
			['o', 1, false, false],
			['x', 1, false, false],
		])
	})

	it('leaves a page and its subpages out of its move targets', () => {
		expect([...descendantsOf(PAGES, 'w')].sort()).toEqual(['a', 'd', 'n'])
		expect(moveTargets(PAGES, 'w').map(pageId)).toEqual(['o', 'x'])
		expect(moveTargets(PAGES, 'n').map(pageId)).toEqual(['w', 'o', 'a', 'x'])
	})

	it('survives a parent cycle in the data', () => {
		const cyclic = [{ id: 'p', title: 'P', parent: 'q' }, { id: 'q', title: 'Q', parent: 'p' }]
		expect([...descendantsOf(cyclic, 'p')]).toEqual(['q'])
		expect(breadcrumb(cyclic, 'p')).toBe('Q / P')
	})

	it('reads the breadcrumb from the top down', () => {
		expect(breadcrumb(PAGES, 'd')).toBe('Werkafspraken / Notulen / Dinsdag')
		expect(breadcrumb(PAGES, 'o')).toBe('Overleg')
	})

	it('moves a page up or down among its siblings, and not past the ends', () => {
		expect(reorderPatches(PAGES, 'a', -1)).toEqual([{ id: 'a', order: 1 }, { id: 'n', order: 2 }])
		expect(reorderPatches(PAGES, 'n', -1)).toEqual([])
		expect(reorderPatches(PAGES, 'x', 1)).toEqual([])
		const tied = [{ id: 'p', title: 'B', order: 0 }, { id: 'q', title: 'A', order: 0 }]
		expect(reorderPatches(tied, 'p', -1)).toEqual([{ id: 'p', order: 1 }, { id: 'q', order: 2 }])
		expect(nextOrder(PAGES, 'w')).toBe(3)
		expect(nextOrder([], '')).toBe(1)
	})

	it('handles the tree keys of the ARIA tree pattern', () => {
		const rows = visibleRows(PAGES, new Set(['w']))
		expect(treeKey(rows, 0, 'ArrowDown')).toEqual({ focus: 1, toggle: null, open: null })
		expect(treeKey(rows, 0, 'ArrowUp')).toEqual({ focus: null, toggle: null, open: null })
		expect(treeKey(rows, 4, 'Home').focus).toBe(0)
		expect(treeKey(rows, 0, 'End').focus).toBe(4)
		expect(treeKey(rows, 1, 'ArrowRight')).toEqual({ focus: null, toggle: 'n', open: null })
		expect(treeKey(rows, 0, 'ArrowRight').focus).toBe(1)
		expect(treeKey(rows, 0, 'ArrowLeft').toggle).toBe('w')
		expect(treeKey(rows, 2, 'ArrowLeft').focus).toBe(0)
		expect(treeKey(rows, 2, 'Enter').open).toBe('a')
		expect(treeKey(rows, 2, 'a')).toBeNull()
	})

	it('browses by keyboard as the scenario does: Down, Right, then Enter on the subpage', () => {
		const pages = [{ id: 'w', title: 'Werkafspraken', order: 1 }, { id: 'o', title: 'Overleg', parent: 'w' }]
		let expanded = new Set()
		let rows = visibleRows(pages, expanded)
		expect(treeKey(rows, 0, 'ArrowRight').toggle).toBe('w')
		expanded = new Set(['w'])
		rows = visibleRows(pages, expanded)
		expect(rows[0].expanded).toBe(true)
		const down = treeKey(rows, 0, 'ArrowDown').focus
		expect(treeKey(rows, down, 'Enter').open).toBe('o')
	})

	it('renders the body as Markdown, and never passes HTML through unsanitised', () => {
		const html = markdownHtml('# Werkafspraken\n\n**Vrijdag** vrij')
		expect(html).toContain('<h1>Werkafspraken</h1>')
		expect(html).toContain('<strong>Vrijdag</strong>')
		// No DOM here to sanitise in: the output is escaped, never raw.
		expect(renderBody('<script>alert(1)</script>')).not.toContain('<script')
	})
})
