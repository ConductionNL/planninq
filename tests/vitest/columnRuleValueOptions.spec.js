/**
 * Vitest tests for which values a column rule's picker offers
 * (ColumnRulesDialog valueOptionsFor), per rule action.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-column-automation/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { loadSfc } from './support/sfc.js'

describe('ColumnRulesDialog valueOptionsFor', () => {
	const { options } = loadSfc(new URL('../../src/dialogs/ColumnRulesDialog.vue', import.meta.url))
	const vm = { priorityOptions: ['high'], peopleOptions: ['anna'], labelOptions: ['bug'] }
	const valueOptionsFor = options.methods.valueOptionsFor.bind(vm)

	it.each([
		['setPriority', ['high']],
		['assign', ['anna']],
		['addLabel', ['bug']],
		['assignMover', null],
		['unassign', null],
		['', null],
		[undefined, null],
		['toString', null],
		['constructor', null],
	])('%s', (action, expected) => {
		expect(valueOptionsFor({ action })).toEqual(expected)
	})
})
