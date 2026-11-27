/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * WikiTree's row click and the keys on a tree item: a click on the toggle
 * opens or closes the branch without opening the page, a click elsewhere
 * opens the page, and a key from inside a row's action menu is left alone.
 *
 * @spec openspec/changes/archive/2026-11-24-adopt-cnapproot-manifest-shell/tasks.md#task-5.2
 */
import { describe, expect, it, vi } from 'vitest'
import { loadSfc } from './support/sfc.js'

const { options } = loadSfc(new URL('../../src/components/WikiTree.vue', import.meta.url))

/**
 * A component instance with spied open and toggle.
 *
 * @return {object} the instance
 */
function instance() {
	const vm = { open: vi.fn(), toggle: vi.fn(), handleKey: vi.fn(() => true), rows: [{ id: 'w' }], focusIndex: 0 }
	vm.onRowClick = options.methods.onRowClick.bind(vm)
	vm.onKeydown = options.methods.onKeydown.bind(vm)
	return vm
}

/**
 * A click whose target is or is not inside the toggle.
 *
 * @param {boolean} onToggle whether the click landed on the toggle
 * @return {object} the event
 */
function click(onToggle) {
	return { target: { closest: (selector) => (onToggle && selector === '.wiki-tree__toggle' ? {} : null) } }
}

describe('WikiTree row click', () => {
	it('toggles the branch from the toggle and does not open the page', () => {
		const vm = instance()
		vm.onRowClick({ id: 'w' }, click(true))
		expect(vm.toggle).toHaveBeenCalledWith({ id: 'w' })
		expect(vm.open).not.toHaveBeenCalled()
	})

	it('opens the page from anywhere else on the row', () => {
		const vm = instance()
		vm.onRowClick({ id: 'w' }, click(false))
		expect(vm.open).toHaveBeenCalledWith({ id: 'w' })
		expect(vm.toggle).not.toHaveBeenCalled()
	})

	it('opens the page for a click without a target', () => {
		const vm = instance()
		vm.onRowClick({ id: 'w' }, undefined)
		expect(vm.open).toHaveBeenCalledWith({ id: 'w' })
	})
})

describe('WikiTree keys on a tree item', () => {
	it('handles a key on the tree item itself', () => {
		const vm = instance()
		const event = { key: 'ArrowDown', target: { getAttribute: () => 'treeitem' }, preventDefault: vi.fn() }
		vm.onKeydown(event)
		expect(vm.handleKey).toHaveBeenCalledWith('ArrowDown')
		expect(event.preventDefault).toHaveBeenCalled()
	})

	it('leaves a key from inside the action menu alone', () => {
		const vm = instance()
		const event = { key: 'ArrowDown', target: { getAttribute: () => 'menuitem' }, preventDefault: vi.fn() }
		vm.onKeydown(event)
		expect(vm.handleKey).not.toHaveBeenCalled()
		expect(event.preventDefault).not.toHaveBeenCalled()
	})
})
