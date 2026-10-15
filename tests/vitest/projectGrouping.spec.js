/**
 * Vitest unit tests for the portfolio helpers: who sees a project, the
 * grouping and order of project lists, the portfolio filter, the read-only
 * board for a portfolio manager and the portfolio's own risk scale
 * (projects-grouping-hierarchy-fields).
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
 */
import { describe, expect, it } from 'vitest'
import {
	canSeeProject,
	filterByPortfolio,
	groupByPortfolio,
	isReadOnlyFor,
	NO_PORTFOLIO,
	portfolioRiskScale,
} from '../../src/utils/portfolioGrouping.js'
import { DEFAULT_RISK_SCALE } from '../../src/utils/riskHelpers.js'

const ruimte = { id: 'p-r', title: 'Ruimte', order: 1 }
const dienst = { id: 'p-d', title: 'Dienstverlening', order: 2 }
const zero = { id: 'p-z', title: 'Algemeen', order: 0 }

describe('canSeeProject', () => {
	it('lets members and portfolio managers see a project', () => {
		expect(canSeeProject({ members: ['bob'] }, 'bob')).toBe(true)
		expect(canSeeProject({ members: ['bob'], portfolioReaders: ['mia'] }, 'mia')).toBe(true)
		expect(canSeeProject({ members: ['bob'] }, 'mia')).toBe(false)
		expect(canSeeProject({}, 'mia')).toBe(false)
	})
})

describe('groupByPortfolio (scenario: the project list groups by portfolio)', () => {
	it('puts projects under their portfolio in the portfolio order, with no portfolio last', () => {
		const projects = [
			{ id: '1', title: 'Omgevingsvisie', portfolio: 'p-r' },
			{ id: '2', title: 'Bestemmingsplan', portfolio: 'p-r' },
			{ id: '3', title: 'Intranet' },
			{ id: '4', title: 'Loket', portfolio: 'p-d' },
		]
		const groups = groupByPortfolio(projects, [dienst, ruimte, zero])
		expect(groups.map((g) => g.id)).toEqual(['p-r', 'p-d', NO_PORTFOLIO])
		expect(groups[0].projects.map((p) => p.title)).toEqual(['Omgevingsvisie', 'Bestemmingsplan'])
		expect(groups[2].projects.map((p) => p.id)).toEqual(['3'])
	})

	it('treats a project whose portfolio is gone as outside any portfolio', () => {
		const groups = groupByPortfolio([{ id: '1', title: 'A', portfolio: 'gone' }], [ruimte])
		expect(groups).toEqual([{ id: NO_PORTFOLIO, title: '', color: '', projects: [{ id: '1', title: 'A', portfolio: 'gone' }] }])
	})

	it('reads a resolved portfolio reference', () => {
		const groups = groupByPortfolio([{ id: '1', title: 'A', portfolio: { id: 'p-r', title: 'Ruimte' } }], [ruimte])
		expect(groups[0].id).toBe('p-r')
	})
})

describe('filterByPortfolio', () => {
	const projects = [{ id: '1', portfolio: 'p-r' }, { id: '2' }]
	it('keeps every project, one portfolio, or the ones outside any', () => {
		expect(filterByPortfolio(projects, '').map((p) => p.id)).toEqual(['1', '2'])
		expect(filterByPortfolio(projects, 'p-r').map((p) => p.id)).toEqual(['1'])
		expect(filterByPortfolio(projects, NO_PORTFOLIO).map((p) => p.id)).toEqual(['2'])
	})
})

describe('isReadOnlyFor (scenario: a portfolio manager sees a project they are not on)', () => {
	const project = { owner: 'carol', members: ['bob'], portfolioReaders: ['mia'] }
	it('opens the board read-only for a portfolio manager who is not on the project', () => {
		expect(isReadOnlyFor(project, { uid: 'mia' })).toBe(true)
		expect(isReadOnlyFor(project, { uid: 'bob' })).toBe(false)
		expect(isReadOnlyFor(project, { uid: 'carol' })).toBe(false)
		expect(isReadOnlyFor(project, { uid: 'root', isAdmin: true })).toBe(false)
		expect(isReadOnlyFor(null, { uid: 'mia' })).toBe(false)
	})
})

describe('portfolioRiskScale (scenario: a portfolio with a three-level scale)', () => {
	const three = { levels: 3, likelihood: ['Low', 'Medium', 'High'], impact: ['Low', 'Medium', 'High'], thresholds: { medium: 3, high: 6 } }
	it('uses the portfolio scale when it has one, else the app-wide scale', () => {
		const portfolios = [{ id: 'p-d', riskScale: JSON.stringify(three) }, { id: 'p-r', riskScale: '' }]
		expect(portfolioRiskScale({ portfolio: 'p-d' }, portfolios, DEFAULT_RISK_SCALE)).toEqual(three)
		expect(portfolioRiskScale({ portfolio: 'p-r' }, portfolios, DEFAULT_RISK_SCALE)).toBe(DEFAULT_RISK_SCALE)
		expect(portfolioRiskScale({}, portfolios, DEFAULT_RISK_SCALE)).toBe(DEFAULT_RISK_SCALE)
	})
})
