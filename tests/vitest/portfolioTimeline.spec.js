/**
 * Vitest unit tests for the portfolio timeline layout: summary bars sorted by
 * start date on one axis, a project that opens into its phases and tasks, and
 * dependency lines across projects only when both ends are drawn
 * (portfolio-status-overview, section 3).
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
import { describe, expect, it } from 'vitest'
import { buildPortfolioLayout, sortBySpan } from '../../src/utils/portfolioTimeline.js'
import { PX_PER_DAY } from '../../src/utils/timelineHelpers.js'

const projects = [
	{ id: 'b', title: 'Bestemmingsplan', spanStart: '2026-03-01', spanEnd: '2026-03-10', phases: [], tasks: [{ id: 'b1', title: 'B1', status: 'open', startDate: '2026-03-02', dueDate: '2026-03-04' }] },
	{ id: 'a', title: 'Omgevingsvisie', spanStart: '2026-02-01', spanEnd: '2026-02-20', phases: [{ id: 'ph', title: 'Initiatie', startDate: '2026-02-01', endDate: '2026-02-05' }], tasks: [{ id: 'a1', title: 'A1', status: 'done', startDate: '2026-02-03', dueDate: '2026-02-06' }] },
	{ id: 'c', title: 'Zonder datum', spanStart: null, spanEnd: null, phases: [], tasks: [] },
]
const edges = [{ id: 'e1', blocker: 'a1', blocked: 'b1' }]

describe('sortBySpan (scenario: a portfolio on one axis)', () => {
	it('sorts by start date with undated projects last', () => {
		expect(sortBySpan(projects).map((p) => p.id)).toEqual(['a', 'b', 'c'])
	})
})

describe('buildPortfolioLayout', () => {
	it('draws one summary bar per project on one axis, closed by default', () => {
		const layout = buildPortfolioLayout(projects, [], edges, PX_PER_DAY.week)
		expect(layout.rows.map((row) => [row.kind, row.id])).toEqual([['project', 'a'], ['project', 'b'], ['project', 'c']])
		const [a, b, c] = layout.rows
		expect(a.left).toBe(0)
		expect(b.left).toBe(28 * PX_PER_DAY.week)
		expect(b.top).toBeGreaterThan(a.top)
		expect(c.dated).toBe(false)
		expect(layout.edgeLines).toEqual([])
	})

	it('opens a project into its phases and task bars under it', () => {
		const layout = buildPortfolioLayout(projects, ['a'], edges, PX_PER_DAY.week)
		expect(layout.rows.map((row) => [row.kind, row.id])).toEqual([
			['project', 'a'],
			['phase', 'ph'],
			['task', 'a1'],
			['project', 'b'],
			['project', 'c'],
		])
		expect(layout.rows[2].projectId).toBe('a')
	})

	it('draws a dependency across projects only when both task bars are shown', () => {
		expect(buildPortfolioLayout(projects, ['a'], edges, PX_PER_DAY.week).edgeLines).toEqual([])
		const both = buildPortfolioLayout(projects, ['a', 'b'], edges, PX_PER_DAY.week)
		expect(both.edgeLines).toHaveLength(1)
		expect(both.edgeLines[0].key).toBe('e1')
	})

	it('has an empty axis when nothing is dated', () => {
		const layout = buildPortfolioLayout([projects[2]], [], [], PX_PER_DAY.week)
		expect(layout.dated).toBe(false)
	})
})
