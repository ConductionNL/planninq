/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pins three small mixins: a dialog save reports the failure it is given
 * when the write returns nothing and emits what was saved otherwise;
 * reloadOn(key) loads on mount and whenever that key changes; projectRoute
 * reads the project id from the route and loads at once and on change.
 */

import { describe, expect, it, vi } from 'vitest'
import dialogSave from '../../src/mixins/dialogSave.js'
import projectRoute from '../../src/mixins/projectRoute.js'
import reloadOn from '../../src/mixins/reloadOn.js'

/**
 * A fake dialog with the mixin's data and submitSave.
 *
 * @return {object} The instance.
 */
function dialog() {
	const vm = { ...dialogSave.data(), $emit: vi.fn() }
	vm.submitSave = dialogSave.methods.submitSave.bind(vm)
	return vm
}

describe('dialogSave', () => {
	it('starts idle and without an error', () => {
		expect(dialogSave.data()).toEqual({ saving: false, submitError: '' })
	})

	it('emits what was saved and clears an earlier error', async () => {
		const vm = dialog()
		vm.submitError = 'old'
		let during = null
		await vm.submitSave(async () => {
			during = vm.saving
			return { id: 't-1' }
		}, 'Could not save')
		expect(during).toBe(true)
		expect(vm.saving).toBe(false)
		expect(vm.submitError).toBe('')
		expect(vm.$emit).toHaveBeenCalledWith('saved', { id: 't-1' })
	})

	it('shows the failure and emits nothing when the write returns nothing', async () => {
		const vm = dialog()
		await vm.submitSave(async () => null, 'Could not save the task')
		expect(vm.submitError).toBe('Could not save the task')
		expect(vm.saving).toBe(false)
		expect(vm.$emit).not.toHaveBeenCalled()
	})
})

describe('reloadOn', () => {
	it('watches the named key and loads on mount and on change', () => {
		const mixin = reloadOn('filters')
		expect(Object.keys(mixin.watch)).toEqual(['filters'])
		const vm = { load: vi.fn() }
		mixin.mounted.call(vm)
		mixin.watch.filters.call(vm)
		expect(vm.load).toHaveBeenCalledTimes(2)
	})
})

describe('projectRoute', () => {
	it('reads the project id from the route as a string, empty without one', () => {
		expect(projectRoute.computed.projectId.call({ $route: { params: { id: 42 } } })).toBe('42')
		expect(projectRoute.computed.projectId.call({ $route: { params: {} } })).toBe('')
	})

	it('loads at once and whenever the project changes', () => {
		expect(projectRoute.watch.projectId.immediate).toBe(true)
		const vm = { load: vi.fn() }
		projectRoute.watch.projectId.handler.call(vm)
		expect(vm.load).toHaveBeenCalledTimes(1)
	})
})
