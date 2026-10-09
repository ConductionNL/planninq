/**
 * Vitest unit tests for the wiki page tree (projects-wiki 3.1 and 3.2): the
 * ordering, the breadcrumb, the move targets and the keyboard handling.
 *
 * @spec openspec/changes/projects-wiki/tasks.md#task-3.1
 */
import { describe, expect, it } from 'vitest'
import { breadcrumb, buildTree, movePatches, moveTargets, reorderPatches, subtreeIds, treeKeyAction, visibleItems } from '../../src/utils/wiki.js'

const pages = [
	{ id: 'w', title: 'Werkafspraken', order: 0 },
	{ id: 'o', title: 'Overleg', order: 1 },
	{ id: 'o1', title: 'Weekstart', order: 0, parent: 'o' },
	{ id: 'o2', title: 'Retro', order: 1, parent: 'o' },
	{ id: 'x', title: 'Weeskind', order: 2, parent: 'gone' },
]

describe('buildTree and visibleItems', () => {
	it('orders siblings by order and puts a page with a missing parent at the top', () => {
		const tree = buildTree(pages)
		expect(tree.map((node) => node.page.id)).toEqual(['w', 'o', 'x'])
		expect(tree[1].children.map((node) => node.page.id)).toEqual(['o1', 'o2'])
	})

	it('shows the children of open pages only, with their level', () => {
		const tree = buildTree(pages)
		expect(visibleItems(tree, new Set()).map((item) => item.id)).toEqual(['w', 'o', 'x'])
		const open = visibleItems(tree, new Set(['o']))
		expect(open.map((item) => `${item.id}:${item.level}`)).toEqual(['w:1', 'o:1', 'o1:2', 'o2:2', 'x:1'])
		expect(open.find((item) => item.id === 'o').expanded).toBe(true)
	})

	it('does not loop on a cycle in the data', () => {
		const loop = [{ id: 'a', parent: 'b', title: 'A' }, { id: 'b', parent: 'a', title: 'B' }]
		expect(() => buildTree(loop)).not.toThrow()
	})
})

describe('breadcrumb (scenario: moving a page under another page)', () => {
	it('lists the pages from the top down to the page', () => {
		expect(breadcrumb(pages, 'o2').map((page) => page.title)).toEqual(['Overleg', 'Retro'])
		expect(breadcrumb(pages, 'nope')).toEqual([])
	})
})

describe('moveTargets (scenario: a page cannot move under its own subpage)', () => {
	it('leaves out the page, its subpages and its current parent', () => {
		expect(moveTargets(pages, 'o').map((page) => page.id)).toEqual(['w', 'x'])
		expect(moveTargets(pages, 'o1').map((page) => page.id)).toEqual(['w', 'o2', 'x'])
		expect(subtreeIds(pages, 'o')).toEqual(new Set(['o', 'o1', 'o2']))
	})

	it('refuses a move under a subpage and puts a moved page last under its new parent', () => {
		expect(movePatches(pages, 'o', 'o1')).toEqual([])
		expect(movePatches(pages, 'o', 'o')).toEqual([])
		expect(movePatches(pages, 'o', 'w')).toEqual([{ id: 'o', parent: 'w', order: 0 }])
		expect(movePatches(pages, 'w', 'o')).toEqual([{ id: 'w', parent: 'o', order: 2 }])
		expect(movePatches(pages, 'o1', '')).toEqual([{ id: 'o1', parent: null, order: 2 }])
	})
})

describe('reorderPatches', () => {
	it('swaps a page with its sibling and renumbers', () => {
		expect(reorderPatches(pages, 'o2', -1)).toEqual([{ id: 'o2', order: 0 }, { id: 'o1', order: 1 }])
		expect(reorderPatches(pages, 'o1', -1)).toEqual([])
		expect(reorderPatches(pages, 'o2', 1)).toEqual([])
	})

	it('repairs equal orders so a page cannot get stuck', () => {
		const same = [{ id: 'a', title: 'A', order: 0 }, { id: 'b', title: 'B', order: 0 }]
		expect(reorderPatches(same, 'b', -1)).toEqual([{ id: 'a', order: 1 }])
	})
})

describe('treeKeyAction (scenario: browsing the tree by keyboard)', () => {
	const closed = visibleItems(buildTree(pages), new Set())
	const open = visibleItems(buildTree(pages), new Set(['o']))

	it('moves with the arrow keys, Home and End', () => {
		expect(treeKeyAction(closed, 'w', 'ArrowDown')).toEqual({ focus: 'o' })
		expect(treeKeyAction(closed, 'o', 'ArrowUp')).toEqual({ focus: 'w' })
		expect(treeKeyAction(closed, 'w', 'ArrowUp')).toEqual({})
		expect(treeKeyAction(closed, 'w', 'End')).toEqual({ focus: 'x' })
		expect(treeKeyAction(closed, 'x', 'Home')).toEqual({ focus: 'w' })
	})

	it('opens a closed item with Right, enters it with a second Right, and closes with Left', () => {
		expect(treeKeyAction(closed, 'o', 'ArrowRight')).toEqual({ expand: 'o' })
		expect(treeKeyAction(open, 'o', 'ArrowRight')).toEqual({ focus: 'o1' })
		expect(treeKeyAction(open, 'o', 'ArrowLeft')).toEqual({ collapse: 'o' })
		expect(treeKeyAction(open, 'o1', 'ArrowLeft')).toEqual({ focus: 'o' })
		expect(treeKeyAction(closed, 'w', 'ArrowRight')).toEqual({})
	})

	it('opens the page with Enter and ignores other keys', () => {
		expect(treeKeyAction(open, 'o1', 'Enter')).toEqual({ open: 'o1' })
		expect(treeKeyAction(open, 'o1', 'a')).toBeNull()
	})

	it('takes the first item when nothing has focus yet', () => {
		expect(treeKeyAction(closed, '', 'ArrowDown')).toEqual({ focus: 'w' })
		expect(treeKeyAction([], '', 'ArrowDown')).toBeNull()
	})
})
