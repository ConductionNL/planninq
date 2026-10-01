/**
 * Reports a user builds (portfolio-flow-reports 3.2, 3.3): the built
 * dataSource per display, equality filters only, and the hidden-project count.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */
import { describe, expect, it } from 'vitest'
import { donutSegments, equalityFilters, groupedParams, mergeBuckets, reportDataSource, splitReports, visibleProjects } from '../../src/utils/reportBuilder.js'

const OPERATORS = ['$gt', '$gte', '$lt', '$lte', '$ne', '$in', '$nin', '$like', 'gt', 'gte', 'lt', 'lte', 'ne', 'in', 'like']

/**
 * Every key anywhere in a value.
 *
 * @param {*} value Any value
 * @return {string[]}
 */
function keysDeep(value) {
	if (!value || typeof value !== 'object') {
		return []
	}
	return Object.entries(value).flatMap(([key, inner]) => [key, ...keysDeep(inner)])
}

describe('reportBuilder', () => {
	it('open tasks per assignee: counts grouped by assignee, filtered on status and the project', () => {
		const report = { filters: { status: 'open' }, groupBy: 'assignedTo', metric: 'count', display: 'bar' }
		expect(reportDataSource(report, 'p1')).toEqual({
			register: 'planninq',
			schema: 'task',
			filter: { status: 'open', project: 'p1' },
			aggregate: { groupBy: 'assignedTo', metric: 'count' },
		})
	})

	it('sums story points or estimated duration, never another field', () => {
		expect(reportDataSource({ groupBy: 'project', metric: 'sum', sumField: 'estimatedDuration' }, 'p1').aggregate).toEqual({ groupBy: 'project', metric: 'sum', sumField: 'estimatedDuration' })
		expect(reportDataSource({ groupBy: 'project', metric: 'sum', sumField: 'title' }, 'p1').aggregate.sumField).toBe('storyPoints')
	})

	it('builds the same aggregate for every display', () => {
		for (const display of ['table', 'bar', 'donut']) {
			expect(reportDataSource({ groupBy: 'status', metric: 'count', display }, 'p1').aggregate).toEqual({ groupBy: 'status', metric: 'count' })
		}
	})

	it('builds no operator but equality, whatever the stored filters hold', () => {
		const filters = { status: { $ne: 'done' }, priority: 'high', dueDate: { gte: '2026-01-01' }, labels: ['a', 'b'], title: 'x', issueType: '' }
		expect(equalityFilters(filters)).toEqual({ priority: 'high' })
		const built = reportDataSource({ filters, groupBy: 'status', metric: 'count' }, 'p1')
		expect(keysDeep(built.filter).filter((key) => OPERATORS.includes(key))).toEqual([])
		expect(Object.values(built.filter).every((value) => typeof value !== 'object')).toBe(true)
	})

	it('adds buckets up across projects, largest first', () => {
		expect(mergeBuckets([[{ key: 'alice', value: 2 }, { key: 'bob', value: 1 }], [{ key: 'bob', value: 3 }]])).toEqual([{ key: 'bob', value: 4 }, { key: 'alice', value: 2 }])
	})

	it('a viewer on fewer projects: counts the projects they cannot see', () => {
		expect(visibleProjects(['p1', 'p2', 'p3', 'p4', 'p5'], ['p2', 'p4', 'other'])).toEqual({ hidden: 3, total: 5, visible: ['p2', 'p4'] })
	})

	it('sends the grouped aggregation the parameters CnChartWidget sends', () => {
		const dataSource = reportDataSource({ filters: { status: 'open' }, groupBy: 'assignedTo', metric: 'sum', sumField: 'storyPoints' }, 'p1')
		expect(groupedParams(dataSource)).toEqual({ groupBy: 'assignedTo', metric: 'sum', field: 'storyPoints', 'filter[status]': 'open', 'filter[project]': 'p1' })
	})

	it('lists my reports and the ones others shared, by title', () => {
		const reports = [
			{ id: 'r1', title: 'Zeta', owner: 'ann', shared: 'private' },
			{ id: 'r2', title: 'Alpha', owner: 'ann', shared: 'readers' },
			{ '@self': { id: 'r3' }, title: 'Open work per person', owner: 'bob', shared: 'readers' },
			{ id: 'r4', title: 'Hidden', owner: 'bob', shared: 'private' },
		]
		const { mine, shared } = splitReports(reports, 'ann')
		expect(mine.map((report) => report.title)).toEqual(['Alpha', 'Zeta'])
		expect(shared.map((report) => report.id)).toEqual(['r3'])
	})

	it('splits a donut into shares that start at the top', () => {
		expect(donutSegments([{ key: 'a', value: 3 }, { key: 'b', value: 1 }])).toEqual([
			{ key: 'a', value: 3, dash: '75 25', offset: 25 },
			{ key: 'b', value: 1, dash: '25 75', offset: -50 },
		])
		expect(donutSegments([])).toEqual([])
	})
})
