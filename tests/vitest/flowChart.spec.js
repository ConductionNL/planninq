/**
 * The Flow tab's pure helpers (portfolio-flow-reports 2.1, 2.2).
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-2.1
 */
import { describe, expect, it } from 'vitest'
import { averageAndP85, bandPath, combineProjectFlows, flowTable, periodWindow, scatterPoints, stackBands, stackMax } from '../../src/utils/flowChart.js'

const columns = [
	{ id: 'todo', title: 'To do' },
	{ id: 'doing', title: 'Doing' },
	{ id: 'review', title: 'Review' },
	{ id: 'done', title: 'Done' },
]
const days = [
	{ date: '2026-09-01', counts: { todo: 5, doing: 3, review: 2, done: 1 } },
	{ date: '2026-09-14', counts: { todo: 1, doing: 2, review: 9, done: 6 } },
]

describe('flowChart', () => {
	it('stacks one band per column in board order, the last column at the bottom', () => {
		const bands = stackBands(columns, days)
		expect(bands.map((band) => band.title)).toEqual(['To do', 'Doing', 'Review', 'Done'])
		expect(bands[3].points[0]).toEqual({ date: '2026-09-01', low: 0, high: 1 })
		expect(bands[2].points[1]).toEqual({ date: '2026-09-14', low: 6, high: 15 })
		expect(bands[0].points[1].high).toBe(18)
	})

	it('scales to the highest stack, never zero', () => {
		expect(stackMax(days)).toBe(18)
		expect(stackMax([])).toBe(1)
	})

	it('draws a closed band path', () => {
		const path = bandPath([{ low: 0, high: 1 }, { low: 0, high: 2 }], 100, 50, 2)
		expect(path).toBe('M0,25 L100,0 L100,50 L0,50 Z')
		expect(bandPath([], 100, 50, 2)).toBe('')
	})

	it('a queue growing before review: the table shows 2 in Review on the first day and 9 on the last', () => {
		const rows = flowTable(columns, days)
		expect(rows[0]).toEqual({ date: '2026-09-01', cells: [5, 3, 2, 1] })
		expect(rows[1].cells[2]).toBe(9)
	})

	it('recalculates average and 85th percentile like the server', () => {
		expect(averageAndP85([7, 3])).toEqual({ average: 5, p85: 7 })
		expect(averageAndP85([])).toEqual({ average: 0, p85: 0 })
	})

	it('places finished tasks by finish time and cycle time', () => {
		const points = scatterPoints([
			{ id: 'a', finishedAt: '2026-09-01T00:00:00Z', cycleDays: 5 },
			{ id: 'b', finishedAt: '2026-09-11T00:00:00Z', cycleDays: 0 },
		], 100, 50)
		expect(points).toEqual([{ id: 'a', x: 0, y: 0 }, { id: 'b', x: 100, y: 50 }])
		expect(scatterPoints([], 100, 50)).toEqual([])
	})

	it('cycle time across a portfolio: adds projects up by column title, and leaving one out recalculates', () => {
		const project = (id, cycle) => ({
			projectId: id,
			columns: [{ id: id + '-todo', title: 'To do' }, { id: id + '-done', title: 'Done' }],
			days: [{ date: '2026-09-01', counts: { [id + '-todo']: 1, [id + '-done']: 2 } }],
			finished: [{ id: id + '-t', cycleDays: cycle, leadDays: cycle + 1, estimated: false }],
			withoutHistory: 0,
		})
		const all = combineProjectFlows([project('a', 2), project('b', 4), project('c', 9)])
		expect(all.columns.map((column) => column.title)).toEqual(['To do', 'Done'])
		expect(all.days[0].counts).toEqual({ 'To do': 3, Done: 6 })
		expect(all.summary.cycle).toEqual({ average: 5, p85: 9 })
		const withoutC = combineProjectFlows([project('a', 2), project('b', 4)])
		expect(withoutC.summary.cycle).toEqual({ average: 3, p85: 4 })
		expect(withoutC.summary.slowest.map((row) => row.id)).toEqual(['b-t', 'a-t'])
	})

	it('a period is the last n days up to today, in UTC', () => {
		expect(periodWindow(14, new Date('2026-09-30T10:00:00Z'))).toEqual({ from: '2026-09-17', to: '2026-09-30' })
	})
})
