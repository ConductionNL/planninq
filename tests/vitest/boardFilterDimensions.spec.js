/**
 * Vitest tests for the select dimensions of the board filter bar
 * (BoardFilterBar selectDimensions): assignee, priority and due date, with
 * their options in order.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const { options } = await loadSfcWithImports(new URL('../../src/components/BoardFilterBar.vue', import.meta.url), { vue: async () => ({}) })

describe('BoardFilterBar selectDimensions', () => {
	it('offers assignee, priority and due date with their options in order', () => {
		const vm = { people: [{ id: 'anna', label: 'Anna' }], t: (app, text) => text }
		for (const [name, getter] of Object.entries(options.computed)) {
			Object.defineProperty(vm, name, { get: getter.bind(vm), configurable: true })
		}

		expect(vm.selectDimensions.map((dimension) => [dimension.id, dimension.label, dimension.options.map((option) => option.label)])).toEqual([
			['assignee', 'Assignee', ['Me', 'Unassigned', 'Anna']],
			['priority', 'Priority', ['Urgent', 'High', 'Normal', 'Low']],
			['due', 'Due date', ['Overdue', 'Due this week', 'No date']],
		])
		expect(vm.selectDimensions[0].options.slice(0, 2).map((option) => option.id)).toEqual(['me', 'unassigned'])
		expect(vm.selectDimensions[1].options.map((option) => option.id)).toEqual(['urgent', 'high', 'normal', 'low'])
		expect(vm.selectDimensions[2].options.map((option) => option.id)).toEqual(['overdue', 'thisWeek', 'none'])
		expect(vm.selectDimensions[0].options[2]).toBe(vm.people[0])
	})
})
