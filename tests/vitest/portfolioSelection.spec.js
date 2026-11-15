/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pins the portfolio selection mixin: options in portfolio order, the
 * selected portfolio from the route (the first one otherwise), its projects,
 * and picking another portfolio into the route.
 */

import { describe, expect, it, vi } from 'vitest'
import portfolioSelection from '../../src/mixins/portfolioSelection.js'

const portfolios = [
	{ id: 2, title: 'Infra', order: 2 },
	{ id: 1, title: 'Apps', order: 1 },
	{ id: 3, title: 'Beta', order: 1 },
]
const projects = [
	{ id: 'p-a', portfolio: '1' },
	{ id: 'p-b', portfolio: { id: '2' } },
	{ id: 'p-c' },
]

/**
 * A fake instance with the mixin's computed values as getters.
 *
 * @param {object} query The route query.
 * @param {object} extra Other fields.
 * @return {object} The instance.
 */
function vm(query = {}, extra = {}) {
	const instance = { portfolios, projects, $route: { query }, $router: { replace: vi.fn() }, ...extra }
	for (const [name, fn] of Object.entries(portfolioSelection.computed)) {
		Object.defineProperty(instance, name, { get: () => fn.call(instance) })
	}
	instance.pick = portfolioSelection.methods.pick.bind(instance)
	return instance
}

describe('portfolioSelection', () => {
	it('offers the portfolios in order, then by title', () => {
		expect(vm().portfolioOptions).toEqual([
			{ id: '1', label: 'Apps' },
			{ id: '3', label: 'Beta' },
			{ id: '2', label: 'Infra' },
		])
	})

	it('selects the portfolio the route names, else the first', () => {
		expect(vm({ portfolio: '2' }).portfolio.title).toBe('Infra')
		expect(vm().portfolio.title).toBe('Apps')
		expect(vm({ portfolio: '99' }).portfolio.title).toBe('Apps')
		expect(vm({}, { portfolios: [] }).portfolio).toBeNull()
	})

	it('marks the selected option', () => {
		expect(vm({ portfolio: '2' }).selectedOption).toEqual({ id: '2', label: 'Infra' })
		expect(vm({}, { portfolios: [] }).selectedOption).toBeNull()
	})

	it('lists the projects of the selected portfolio, and none without one', () => {
		expect(vm({ portfolio: '2' }).portfolioProjects.map((p) => p.id)).toEqual(['p-b'])
		expect(vm().portfolioProjects.map((p) => p.id)).toEqual(['p-a'])
		expect(vm({}, { portfolios: [] }).portfolioProjects).toEqual([])
	})

	it('puts a picked portfolio in the route, keeping the rest of the query', () => {
		const instance = vm({ portfolio: '1', tab: 'board' })
		instance.pick({ id: '2' })
		expect(instance.$router.replace).toHaveBeenCalledWith({ query: { portfolio: '2', tab: 'board' } })
	})

	it('ignores picking the current portfolio or nothing', () => {
		const instance = vm({ portfolio: '1' })
		instance.pick({ id: '1' })
		instance.pick(null)
		instance.pick({})
		expect(instance.$router.replace).not.toHaveBeenCalled()
	})
})
