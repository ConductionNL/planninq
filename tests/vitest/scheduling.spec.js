/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Auto-scheduling (planning-timeline-editing tasks 3.2 and 3.3).
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.2
 */
import { describe, expect, it, vi } from 'vitest'
import { cascade, runError } from '../../src/utils/scheduling.js'
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
		const moves = cascade({ tasks, edges, calendar }, { id: 'A', dates: { startDate: '2026-10-05', dueDate: '2026-10-13' } })
		expect(moves.map((move) => [move.id, move.to.startDate, move.to.dueDate])).toEqual([
			['B', '2026-10-14', '2026-10-16'],
			['C', '2026-10-19', '2026-10-20'],
		])
		expect(moves[0].from).toEqual({ startDate: '2026-10-12', dueDate: '2026-10-14' })
	})

	it('relates edge moves nothing', () => {
		const moves = cascade({ tasks, edges, calendar }, { id: 'A', dates: { startDate: '2026-10-05', dueDate: '2026-10-16' } })
		expect(moves.map((move) => move.id)).not.toContain('D')
	})

	it('earlier move pulls nothing', () => {
		expect(cascade({ tasks, edges, calendar }, { id: 'A', dates: { startDate: '2026-10-05', dueDate: '2026-10-07' } })).toEqual([])
	})

	it('successor that already starts later stays', () => {
		const moves = cascade({ tasks, edges, calendar }, { id: 'A', dates: { startDate: '2026-10-05', dueDate: '2026-10-13' } })
		expect(moves.map((move) => move.id)).not.toContain('E')
	})
})

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (match, key) => (key in vars ? String(vars[key]) : match)),
}))

describe('runError', () => {
	const tasks = [{ id: 'A', title: 'Design' }, { id: 'B', title: 'Build' }, { id: 'C', title: 'Test' }]

	it('reports nothing when the whole run was written', () => {
		expect(runError({ written: 3, failed: null }, tasks)).toBe('')
	})

	it('write run stops at the first failure and reports it', () => {
		expect(runError({ written: 1, failed: 'B' }, tasks)).toBe('Stopped at Build: its dates could not be saved. The tasks before it were moved.')
	})

	it('says nothing was saved when the run was refused or not sent', () => {
		expect(runError(null, tasks)).toBe('The dates could not be saved.')
	})
})
