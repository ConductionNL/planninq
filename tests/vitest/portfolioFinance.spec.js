/**
 * Vitest unit tests for money across a portfolio (portfolio-finance, task
 * 3.4): per-project budget, commitments, actual cost including labour,
 * forecast and remaining, the totals and their cross-check, and who is
 * totalled at all.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
 */
import { describe, expect, it } from 'vitest'
import { portfolioFinance, projectMoney } from '../../src/utils/finance.js'

const manager = { uid: 'mia' }
const projects = [
	{ id: 'p1', title: 'Omgevingsvisie', owner: 'carol', budgetAmount: 50000, hourlyRate: 100, portfolioReaders: ['mia'] },
	{ id: 'p2', title: 'Stadspark', owner: 'dave', budgetAmount: 30000, portfolioReaders: ['mia'] },
]
const lines = [
	{ project: 'p1', kind: 'actual', amount: 18000 },
	{ project: 'p1', kind: 'commitment', amount: 1000 },
	{ project: 'p2', kind: 'actual', amount: 35000 },
	{ project: 'p2', kind: 'forecast', amount: 36000 },
]
const entries = { p1: [{ duration: 1200 }] }

describe('portfolioFinance (scenario: totals for a portfolio)', () => {
	it('lists both projects with their figures, labour counted as actual cost', () => {
		const result = portfolioFinance({ projects, lines, entriesByProject: entries, user: manager })
		expect(result.rows.map((row) => [row.project.id, row.budget, row.actual])).toEqual([
			['p1', 50000, 20000],
			['p2', 30000, 35000],
		])
	})

	it('totals a budget of 80000 and an actual cost of 55000, and marks the second project over budget', () => {
		const result = portfolioFinance({ projects, lines, entriesByProject: entries, user: manager })
		expect(result.total).toMatchObject({ budget: 80000, actual: 55000, commitment: 1000, forecast: 36000 })
		expect(result.total.remaining).toBe(80000 - 55000 - 1000)
		expect(result.rows[1]).toMatchObject({ remaining: -5000, over: true })
		expect(result.rows[0].over).toBe(false)
		expect(result.consistent).toBe(true)
	})

	it('flags lines of projects outside the portfolio, a sign the portfolio filter was not applied', () => {
		const stray = portfolioFinance({ projects, lines: [...lines, { project: 'p9', kind: 'actual', amount: 5 }], entriesByProject: entries, user: manager })
		expect(stray.consistent).toBe(false)
		expect(stray.total.actual).toBe(55000)
	})
})

describe('who is totalled (scenario: a project outside your reach is not totalled)', () => {
	it('totals nothing for a member who holds no role on the portfolio', () => {
		const result = portfolioFinance({ projects: [{ ...projects[0], members: ['bob'] }], lines, entriesByProject: entries, user: { uid: 'bob' } })
		expect(result.rows).toEqual([])
		expect(result.hidden).toBe(1)
		expect(result.total.actual).toBe(0)
	})

	it('totals only the projects whose money the viewer may see', () => {
		const result = portfolioFinance({ projects, lines, entriesByProject: entries, user: { uid: 'dave' } })
		expect(result.rows.map((row) => row.project.id)).toEqual(['p2'])
		expect(result.hidden).toBe(1)
	})
})

describe('projectMoney (the status report money suggestion)', () => {
	it('is the actual cost with labour', () => {
		expect(projectMoney(projects[0], lines.filter((line) => line.project === 'p1'), entries.p1).actual).toBe(20000)
	})
})
