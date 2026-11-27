/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The board's view (colour cards, swimlanes) loads after the cards render. A
 * choice the person makes in that moment is newer than the stored view, so the
 * stored one arriving later must not undo it.
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const store = { settings: null, stored: { board_views: { p1: { colour: 'priority', group: 'none' } } }, saved: [] }
let release = () => {}

const { options } = await loadSfcWithImports(new URL('../../src/views/ProjectBoard.vue', import.meta.url), {
	modules: {
		'../store/modules/settings.js': {
			useSettingsStore: () => ({
				get settings() {
					return store.settings
				},
				fetchSettings: () => new Promise((resolve) => {
					release = () => resolve(store.stored)
				}),
				saveUserSettings: async (data) => {
					store.saved.push(data)
					return true
				},
			}),
		},
	},
	vue: async () => ({}),
})

/**
 * A board as far as the view methods need it.
 *
 * @return {object}
 */
function board() {
	const vm = {
		project: { id: 'p1' },
		boardView: { colour: 'none', group: 'none' },
		boardViewChosen: false,
		t: (app, text) => text,
	}
	for (const [name, method] of Object.entries(options.methods)) {
		vm[name] = method.bind(vm)
	}
	vm.loadPersonNames = async () => {}
	return vm
}

describe('ProjectBoard board view', () => {
	it('applies the stored view when the person has not chosen one', async () => {
		const vm = board()
		const loading = vm.loadBoardView('p1')
		release()
		await loading
		expect(vm.boardView).toEqual({ colour: 'priority', group: 'none' })
	})

	it('keeps a view chosen while the stored one was still loading', async () => {
		const vm = board()
		const loading = vm.loadBoardView('p1')
		await vm.setBoardView({ colour: 'label' })
		release()
		await loading
		expect(vm.boardView).toEqual({ colour: 'label', group: 'none' })
		expect(store.saved.at(-1)).toEqual({ board_view: { project: 'p1', colour: 'label', group: 'none' } })
	})
})
