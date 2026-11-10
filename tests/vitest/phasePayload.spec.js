/**
 * Vitest tests for the phase the edit form writes (phasePayload): which
 * fields it carries, how budget hours are read, and the order of a new phase.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { phasePayload } from '../../src/utils/phaseHelpers.js'

describe('phasePayload', () => {
	it.each([
		['blank form, no phases', {}, [], { title: '', project: 'p1', order: 1 }],
		['trimmed texts, in their order', { title: ' A ', endDate: ' 2026-02-01 ', description: ' d ', startDate: '2026-01-01' }, null, { title: 'A', project: 'p1', description: 'd', startDate: '2026-01-01', endDate: '2026-02-01', order: 1 }],
		['zero budget hours', { title: 'A', budgetHours: 0 }, [], { title: 'A', project: 'p1', budgetHours: 0, order: 1 }],
		['budget hours as text', { title: 'A', budgetHours: '12.5' }, [], { title: 'A', project: 'p1', budgetHours: 12.5, order: 1 }],
		['empty budget hours', { title: 'A', budgetHours: '' }, [], { title: 'A', project: 'p1', order: 1 }],
		['null budget hours', { title: 'A', budgetHours: null }, [], { title: 'A', project: 'p1', order: 1 }],
		['unreadable budget hours', { title: 'A', budgetHours: 'many' }, [], { title: 'A', project: 'p1', order: 1 }],
		['a new phase comes last', { title: 'A' }, [{ order: 3 }, { order: '7' }, { order: 'x' }], { title: 'A', project: 'p1', order: 8 }],
		['negative orders count from zero', { title: 'A' }, [{ order: -4 }], { title: 'A', project: 'p1', order: 1 }],
		['an existing phase keeps its order', { id: 'f1', title: 'A' }, [{ order: 3 }], { title: 'A', project: 'p1' }],
	])('%s', (name, fields, phases, expected) => {
		expect(phasePayload(fields, 'p1', phases)).toEqual(expected)
		expect(Object.keys(phasePayload(fields, 'p1', phases))).toEqual(Object.keys(expected))
	})
})
