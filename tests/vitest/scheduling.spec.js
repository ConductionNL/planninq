/**
 * Auto-scheduling (planning-timeline-editing tasks 3.2 and 3.3).
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.2
 */
import { describe, expect, it } from 'vitest'
import { cascade, writeRun } from '../../src/utils/scheduling.js'
import { normaliseCalendar } from '../../src/utils/workingCalendar.js'

const calendar = normaliseCalendar({})
const tasks = [
	{ id: 'A', title: 'A', startDate: '2026-10-05', dueDate: '2026-10-09' },
	{ id: 'B', title: 'B', startDate: '2026-10-12', dueDate: '2026-10-14' },
	{ id: 'C', title: 'C', startDate: '2026-10-15', dueDate: '2026-10-16' },
	{ id: 'D', title: 'D', startDate: '2026-10-12', dueDate: '2026-10-13' },
	{ id: 'E', title: 'E', startDate: '2026-11-02', dueDate: '2026-11-03' },
]
const edges = [
	{ blocker: 'A', blocked: 'B' },
	{ blocker: 'B', blocked: 'C', type: 'blocks' },
	{ blocker: 'A', blocked: 'D', type: 'relates' },
	{ blocker: 'A', blocked: 'E', type: 'blocks' },
]

describe('cascade', () => {
	it('slip pushes a chain', () => {
		const moves = cascade(tasks, edges, 'A', { startDate: '2026-10-05', dueDate: '2026-10-13' }, calendar)
		expect(moves.map((move) => [move.id, move.to.startDate, move.to.dueDate])).toEqual([
			['B', '2026-10-14', '2026-10-16'],
			['C', '2026-10-19', '2026-10-20'],
		])
		expect(moves[0].from).toEqual({ startDate: '2026-10-12', dueDate: '2026-10-14' })
	})

	it('relates edge moves nothing', () => {
		const moves = cascade(tasks, edges, 'A', { startDate: '2026-10-05', dueDate: '2026-10-16' }, calendar)
		expect(moves.map((move) => move.id)).not.toContain('D')
	})

	it('earlier move pulls nothing', () => {
		expect(cascade(tasks, edges, 'A', { startDate: '2026-10-05', dueDate: '2026-10-07' }, calendar)).toEqual([])
	})

	it('successor that already starts later stays', () => {
		const moves = cascade(tasks, edges, 'A', { startDate: '2026-10-05', dueDate: '2026-10-13' }, calendar)
		expect(moves.map((move) => move.id)).not.toContain('E')
	})
})

describe('writeRun', () => {
	it('write run stops at the first failure and reports it', async () => {
		const written = []
		const result = await writeRun([{ id: 'A', to: {} }, { id: 'B', to: {} }, { id: 'C', to: {} }], async (id) => {
			written.push(id)
			return id !== 'B'
		})
		expect(written).toEqual(['A', 'B'])
		expect(result.written).toBe(1)
		expect(result.failed.id).toBe('B')
	})
})
