/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Golden test for the portfolio selection of PortfolioFinance, PortfolioStatus
 * and PortfolioTimeline: the options, the chosen portfolio, the selected
 * option, its projects and what picking another portfolio does, over seeded
 * portfolios and addresses, plus fingerprints of each compiled template and
 * its styles. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'portfolioSelection.golden.json')
const VIEWS = ['PortfolioFinance', 'PortfolioStatus', 'PortfolioTimeline']
const SELECTION = {
	PortfolioFinance: ['portfolioOptions', 'portfolio', 'selectedOption', 'portfolioProjects'],
	PortfolioStatus: ['portfolioOptions', 'portfolio', 'selectedOption', 'portfolioProjects'],
	PortfolioTimeline: ['portfolioOptions', 'portfolio', 'selectedOption'],
}

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
 * The component's options of one kind with its mixins applied, own entries last.
 *
 * @param {object} options - the component options
 * @param {string} kind - 'computed' or 'methods'
 * @return {object} the merged entries
 */
function merged(options, kind) {
	return Object.assign({}, ...(options.mixins || []).map((mixin) => mixin[kind] || {}), options[kind] || {})
}

/**
 * A component instance stand-in over seeded data.
 *
 * @param {object} options - the component options
 * @param {number} seed - scenario seed
 * @return {{vm: object, replaced: Array}} the instance and its router calls
 */
function instance(options, seed) {
	const pick = random(seed)
	const replaced = []
	const portfolios = Array.from({ length: pick(5) }, (_, i) => ({ id: [i + 1, `pf${i}`, String(i + 1)][pick(3)], title: ['Zeta', 'alpha', '', undefined, 'Beta'][pick(5)] }))
	const projects = Array.from({ length: pick(6) }, (_, i) => ({ id: `p${i}`, portfolio: [1, 'pf0', '2', undefined, '1', { id: 'pf1' }][pick(6)] }))
	const query = [{}, { portfolio: '1' }, { portfolio: 'pf0' }, { portfolio: 'none' }, { portfolio: '2', tab: 'x' }][pick(5)]
	const vm = {
		portfolios,
		projects,
		openIds: ['p0'],
		$route: { query },
		$router: { replace: (to) => replaced.push(to) },
		t: (app, text) => text,
	}
	for (const [name, getter] of Object.entries(merged(options, 'computed'))) {
		Object.defineProperty(vm, name, { get: () => getter.call(vm), enumerable: false })
	}
	for (const [name, method] of Object.entries(merged(options, 'methods'))) {
		vm[name] = method.bind(vm)
	}
	return { vm, replaced }
}

/**
 * Record the selection of one view over one seeded instance.
 *
 * @param {object} options - the component options
 * @param {{view: string, seed: number}} at - the view's name and the scenario seed
 * @return {object} the recorded case
 */
function record(options, at) {
	const seed = at.seed
	const { vm, replaced } = instance(options, seed)
	const values = {}
	for (const name of SELECTION[at.view]) {
		values[name] = vm[name]
	}
	const picked = [undefined, null, {}, { id: '1' }, { id: 'pf0' }, { id: vm.$route.query.portfolio }][seed % 6]
	vm.pick(picked)
	return { seed, values, picked, replaced, openIds: vm.openIds }
}

describe('portfolio selection golden', () => {
	it('matches the recorded fixture', async () => {
		const actual = {}
		for (const view of VIEWS) {
			const { options, templateHash, styleHash } = await loadSfcWithImports(new URL(`../../src/views/${view}.vue`, import.meta.url))
			const cases = []
			for (let seed = 1; seed <= 60; seed++) {
				cases.push(record(options, { view, seed }))
			}
			actual[view] = JSON.parse(JSON.stringify({ templateHash, styleHash, cases }))
		}
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual) + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	}, 60000)
})
