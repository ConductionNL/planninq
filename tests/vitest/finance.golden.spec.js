/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Golden test for financeTable() in src/utils/finance.js: seeded finance
 * lines, categories, phases, labour and agreed budgets, compared with a
 * recorded fixture. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { financeTable } from '../../src/utils/finance.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'finance.golden.json')

const KINDS = ['budget', 'commitment', 'actual', 'actual', 'actual', 'forecast', 'other', undefined]
const AMOUNTS = [0, 100, 250.5, -40, '75', 'abc', null, 1e6]

/**
 * A small deterministic pseudo-random generator.
 *
 * @param {number} seed - start value
 * @return {function(number): number} an integer below the given bound
 */
function random(seed) {
	let state = seed
	return (below) => {
		state = (state * 1103515245 + 12345) % 2147483648
		return Math.floor((state / 2147483648) * below)
	}
}

/**
 * One seeded input for financeTable().
 *
 * @param {number} seed - scenario seed
 * @return {object} the input
 */
function scenario(seed) {
	const pick = random(seed)
	const categoryNames = ['Hardware', 'Licences', 'Travel', 'Hardware', '']
	const categories = pick(5) === 0 ? null : categoryNames.slice(0, pick(6))
	const phases = pick(4) === 0 ? undefined : [{ id: 'ph1', title: 'Design' }, { id: 'ph2' }, { id: 'ph3', title: 'Build' }].slice(0, pick(4))
	const lines = []
	for (let i = 0; i < pick(9); i++) {
		lines.push(pick(12) === 0
			? null
			: {
					category: ['Hardware', 'Licences', 'Extra', '', undefined][pick(5)],
					phase: ['ph1', 'ph2', 'ph9', '', undefined][pick(5)],
					kind: KINDS[pick(KINDS.length)],
					amount: AMOUNTS[pick(AMOUNTS.length)],
				})
	}
	const labours = [undefined, null, { available: false, hours: 5, amount: 300 }, { hours: 12, amount: 960 }, { available: true, amount: '80' }]
	const projects = [undefined, {}, { budgetAmount: 0 }, { budgetAmount: 500 }, { budgetAmount: '2000' }, { budgetAmount: -10 }]
	return { lines: pick(10) === 0 ? null : lines, categories, labour: labours[pick(labours.length)], project: projects[pick(projects.length)], phases }
}

describe('financeTable golden', () => {
	it('matches the recording', () => {
		const cases = []
		for (let seed = 1; seed <= 70; seed++) {
			cases.push({ seed, table: financeTable(scenario(seed)) })
		}
		const actual = JSON.stringify(cases) + '\n'
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.mkdirSync(path.dirname(FIXTURE), { recursive: true })
			fs.writeFileSync(FIXTURE, actual)
		}
		expect(actual).toBe(fs.readFileSync(FIXTURE, 'utf8'))
	})
})
