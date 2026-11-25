/**
 * The delete dialog switches to the logged-time branch when the task's time
 * entries arrive after it opened.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

/**
 * The dialog's options.
 *
 * @return {Promise<object>} the component options
 */
async function dialog() {
	const { options } = await loadSfcWithImports(new URL('../../src/dialogs/TaskDeleteDialog.vue', import.meta.url))
	return options
}

describe('TaskDeleteDialog logged time', () => {
	it('opens on the logged-time branch when the task already has time', async () => {
		const options = await dialog()
		expect(options.data.call({ hasTime: true }).reason).toBe('has-time')
		expect(options.data.call({ hasTime: false }).reason).toBe('')
	}, 60000)

	it('switches to the logged-time branch when time arrives after it opened', async () => {
		const options = await dialog()
		const vm = { ...options.data.call({ hasTime: false }) }
		options.watch.hasTime.call(vm, true)
		expect(vm.reason).toBe('has-time')
	}, 60000)

	it('keeps a server answer when the entries turn out empty', async () => {
		const options = await dialog()
		const vm = { reason: 'has-time' }
		options.watch.hasTime.call(vm, false)
		expect(vm.reason).toBe('has-time')
	}, 60000)
})
