/**
 * Vitest unit tests for a project's money (portfolio-finance): labour cost from
 * booked time, the category and phase tables with what remains, who sees the
 * amounts, and the payloads the Finance tab writes, validated against the real
 * register schemas.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.1
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	canEditTerms,
	canSeeProjectMoney,
	categoriesValid,
	categoryLines,
	financeLinePayload,
	financeTable,
	laborCost,
	LABOUR_ROW,
	parseCategories,
	termsForm,
	termsPatch,
} from '../../src/utils/finance.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))

/**
 * A validator for one register schema, prepared the way OpenRegister prepares it.
 *
 * @param {string} slug The schema slug.
 * @return {Function}
 */
function validatorFor(slug) {
	const schema = register.components.schemas[slug]
	const properties = {}
	for (const [name, property] of Object.entries(schema.properties)) {
		const { $ref, visible, nullable, ...rest } = property
		for (const key of Object.keys(rest)) {
			if (key.startsWith('x-')) {
				delete rest[key]
			}
		}
		if (nullable) {
			rest.type = [rest.type, 'null']
			if (rest.enum) {
				rest.enum = [...rest.enum, null]
			}
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false })
	addFormats(ajv)
	return ajv.compile({ type: 'object', required: schema.required, properties })
}

describe('laborCost (task 2.1)', () => {
	const project = { id: 'p1', hourlyRate: 100 }

	it('multiplies booked hours by the entry rate, else the project rate', () => {
		const entries = [
			{ duration: 600, hourlyRate: 0 },
			{ duration: 300 },
			{ duration: 300, hourlyRate: 120 },
		]
		expect(laborCost(entries, project)).toEqual({ available: true, hours: 20, amount: 1000 + 500 + 600 })
	})

	it('reads zero when nothing is booked', () => {
		expect(laborCost([], project)).toEqual({ available: true, hours: 0, amount: 0 })
	})

	it('says the hours are not available when their owner is missing, instead of zero', () => {
		expect(laborCost([{ duration: 60 }], project, { hoursAvailable: false })).toEqual({ available: false, hours: null, amount: null })
	})
})

describe('financeTable (scenario: budget against actual cost with booked time)', () => {
	const categories = ['Personnel', 'Materials']
	const project = { budgetAmount: 10000, hourlyRate: 100 }
	const labour = laborCost([{ duration: 1200 }], project)

	it('puts labour and a manual materials line in their rows, and totals spent and remaining', () => {
		const lines = [{ id: 'l1', kind: 'actual', category: 'Materials', amount: 1500 }]
		const table = financeTable({ lines, categories, labour, project })

		const labourRow = table.categoryRows.find((row) => row.id === LABOUR_ROW)
		expect(labourRow.actual).toBe(2000)
		expect(table.categoryRows.find((row) => row.id === 'Materials').actual).toBe(1500)
		expect(table.total.actual).toBe(3500)
		expect(table.total.budget).toBe(10000)
		expect(table.total.remaining).toBe(6500)
		expect(table.total.over).toBe(false)
	})

	it('shows budget no category holds as "not yet assigned", and remaining counts open commitments', () => {
		const lines = [
			{ kind: 'budget', category: 'Materials', amount: 4000 },
			{ kind: 'commitment', category: 'Materials', amount: 1000 },
			{ kind: 'actual', category: 'Materials', amount: 3500 },
			{ kind: 'forecast', category: 'Materials', amount: 5000 },
		]
		const table = financeTable({ lines, categories, labour: laborCost([], project), project })
		const materials = table.categoryRows.find((row) => row.id === 'Materials')
		expect(materials).toMatchObject({ budget: 4000, commitment: 1000, actual: 3500, forecast: 5000, remaining: -500, over: true })
		expect(table.unassignedBudget).toBe(6000)
		expect(table.total.remaining).toBe(10000 - 3500 - 1000)
	})

	it('keeps a category the admin removed as its own row, and splits by phase', () => {
		const lines = [
			{ kind: 'actual', category: 'Catering', amount: 50, phase: 'ph1' },
			{ kind: 'budget', category: 'Materials', amount: 100, phase: 'ph1' },
			{ kind: 'actual', category: 'Materials', amount: 30 },
		]
		const phases = [{ id: 'ph1', title: 'Ontwerp' }]
		const table = financeTable({ lines, categories, labour: laborCost([], project), project, phases })
		expect(table.categoryRows.map((row) => row.id)).toEqual(['Personnel', 'Materials', 'Catering', LABOUR_ROW])
		expect(table.phaseRows).toEqual([
			expect.objectContaining({ id: 'ph1', label: 'Ontwerp', budget: 100, actual: 50 }),
			expect.objectContaining({ id: '', actual: 30 }),
		])
	})

	it('without a project budget, the category budgets are the budget', () => {
		const lines = [{ kind: 'budget', category: 'Materials', amount: 400 }]
		const table = financeTable({ lines, categories, labour: laborCost([], {}), project: { budgetAmount: 0 } })
		expect(table.total.budget).toBe(400)
		expect(table.unassignedBudget).toBe(0)
	})

	it('leaves labour out of the totals when the hours are not available', () => {
		const table = financeTable({ lines: [], categories, labour: laborCost([], project, { hoursAvailable: false }), project })
		expect(table.categoryRows.find((row) => row.id === LABOUR_ROW).available).toBe(false)
		expect(table.total.actual).toBe(0)
	})
})

describe('who sees and sets the money (scenario: members do not see the money)', () => {
	const project = { owner: 'carol', members: ['bob'], portfolioReaders: ['mia'] }

	it('owner, portfolio managers and admins see the amounts; members do not', () => {
		expect(canSeeProjectMoney(project, { uid: 'carol' })).toBe(true)
		expect(canSeeProjectMoney(project, { uid: 'mia' })).toBe(true)
		expect(canSeeProjectMoney(project, { uid: 'root', isAdmin: true })).toBe(true)
		expect(canSeeProjectMoney(project, { uid: 'bob' })).toBe(false)
		expect(canSeeProjectMoney(project, null)).toBe(false)
	})

	it('only the owner and admins set the terms', () => {
		expect(canEditTerms(project, { uid: 'carol' })).toBe(true)
		expect(canEditTerms(project, { uid: 'root', isAdmin: true })).toBe(true)
		expect(canEditTerms(project, { uid: 'mia' })).toBe(false)
	})
})

describe('the payloads the Finance tab writes', () => {
	const validateProject = validatorFor('project')
	const validateLine = validatorFor('financeLine')

	it('writes the terms of a fixed-price budget (scenario: setting a fixed-price budget)', () => {
		const form = { ...termsForm({}), billable: true, billingModel: 'fixedPrice', budgetAmount: '56000', budgetHours: '400' }
		const patch = termsPatch(form)
		expect(patch).toEqual({ billable: true, billingModel: 'fixedPrice', budgetAmount: 56000, budgetHours: 400, hourlyRate: 0, startDate: null, endDate: null })
		expect(validateProject({ title: 'Stadspark', status: 'active', ...patch }), JSON.stringify(validateProject.errors)).toBe(true)
	})

	it('reads empty or negative amounts as no budget', () => {
		expect(termsPatch({ ...termsForm({}), budgetAmount: '', budgetHours: '-3', startDate: '2026-10-01' })).toMatchObject({ budgetAmount: 0, budgetHours: 0, startDate: '2026-10-01' })
	})

	it('fills the form from a project with no terms', () => {
		expect(termsForm({})).toEqual({ billable: false, billingModel: 'none', budgetAmount: '', budgetHours: '', hourlyRate: '', startDate: '', endDate: '' })
	})

	it('writes a manual line the schema accepts', () => {
		const payload = financeLinePayload({ kind: 'actual', category: 'Materials', amount: '1500', date: '2026-09-29', description: ' Bricks ', phase: '' }, '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e')
		expect(payload).toEqual({ project: '5b0c7d8e-1f2a-4b3c-9d4e-5f6a7b8c9d0e', kind: 'actual', category: 'Materials', amount: 1500, date: '2026-09-29', description: 'Bricks', phase: null, source: 'manual' })
		expect(validateLine(payload), JSON.stringify(validateLine.errors)).toBe(true)
	})

	it('control: the validator refuses a line of an unknown kind', () => {
		expect(validateLine({ kind: 'spent', amount: 1 })).toBe(false)
	})
})

describe('the cost categories setting (task 1.2)', () => {
	it('reads the stored list and ignores what is not a name', () => {
		expect(parseCategories('["Personnel","Materials"]')).toEqual(['Personnel', 'Materials'])
		expect(parseCategories('not json')).toEqual([])
		expect(parseCategories('[1, " ", "Other"]')).toEqual(['Other'])
	})

	it('takes one name per line, each once', () => {
		const names = categoryLines(' Personeel \n\nInhuur\n')
		expect(names).toEqual(['Personeel', 'Inhuur'])
		expect(categoriesValid(names)).toBe(true)
		expect(categoriesValid(['Inhuur', 'inhuur'])).toBe(false)
		expect(categoriesValid([])).toBe(false)
	})
})
