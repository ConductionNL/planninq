/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Edge cases of the report builder: filter value types, defaults for an
 * unknown grouping or sum field, and missing inputs.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */
import { describe, expect, it } from 'vitest'
import { equalityFilters, groupedParams, mergeBuckets, reportDataSource, splitReports, visibleProjects } from '../../src/utils/reportBuilder.js'

describe('equalityFilters edges', () => {
	it('keeps non-empty strings, numbers and booleans on known fields only', () => {
		expect(equalityFilters({
			status: 'open',
			priority: '',
			column: 3,
			labels: false,
			assignedTo: null,
			issueType: ['bug'],
			secret: 'x',
		})).toEqual({ status: 'open', column: 3, labels: false })
		expect(equalityFilters(null)).toEqual({})
	})
})

describe('reportDataSource edges', () => {
	it('defaults an unknown grouping to status and an unknown metric to count', () => {
		expect(reportDataSource({ groupBy: 'nope', metric: 'avg', sumField: 'storyPoints' }, 'p1')).toEqual({
			register: 'planninq',
			schema: 'task',
			filter: { project: 'p1' },
			aggregate: { groupBy: 'status', metric: 'count' },
		})
	})

	it('defaults an unknown sum field to story points, and the project wins over a filter', () => {
		const source = reportDataSource({ groupBy: 'priority', metric: 'sum', sumField: 'hours', filters: { status: 'open' } }, 'p1')
		expect(source.aggregate).toEqual({ groupBy: 'priority', metric: 'sum', sumField: 'storyPoints' })
		expect(source.filter).toEqual({ status: 'open', project: 'p1' })
	})
})

describe('groupedParams edges', () => {
	it('adds the sum field only for a sum and every filter as filter[x]', () => {
		expect(groupedParams({ aggregate: { groupBy: 'status', metric: 'count' } })).toEqual({ groupBy: 'status', metric: 'count' })
		expect(groupedParams({ aggregate: { groupBy: 'status', metric: 'sum', sumField: 'estimatedDuration' }, filter: { project: 'p', status: 'open' } }))
			.toEqual({ groupBy: 'status', metric: 'sum', field: 'estimatedDuration', 'filter[project]': 'p', 'filter[status]': 'open' })
	})
})

describe('mergeBuckets, visibleProjects and splitReports edges', () => {
	it('adds buckets across projects, treats a missing value as 0 and sorts by value', () => {
		expect(mergeBuckets([[{ key: 'a', value: 2 }, { key: 'b', value: null }], null, [{ key: 'a', value: '3' }, { key: 'c', value: 4 }]]))
			.toEqual([{ key: 'a', value: 5 }, { key: 'c', value: 4 }, { key: 'b', value: 0 }])
		expect(mergeBuckets(null)).toEqual([])
	})

	it('counts the projects a reader cannot see', () => {
		expect(visibleProjects(['a', 'b', 'c'], ['b'])).toEqual({ hidden: 2, total: 3, visible: ['b'] })
		expect(visibleProjects(null, null)).toEqual({ hidden: 0, total: 0, visible: [] })
	})

	it('splits mine from shared-with-readers, taking the id from @self and sorting by title', () => {
		const out = splitReports([
			{ '@self': { id: 'r1' }, title: 'Zeta', owner: 'me' },
			{ id: 'r2', owner: 'me' },
			{ id: 'r3', title: 'Beta', owner: 'you', shared: 'readers' },
			{ id: 'r4', title: 'Alpha', owner: 'you', shared: 'none' },
		], 'me')
		expect(out.mine.map((r) => r.id)).toEqual(['r2', 'r1'])
		expect(out.shared.map((r) => r.id)).toEqual(['r3'])
		expect(splitReports(null, 'me')).toEqual({ mine: [], shared: [] })
	})
})
