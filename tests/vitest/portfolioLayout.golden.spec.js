/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Golden test for buildPortfolioLayout() in src/utils/portfolioTimeline.js:
 * seeded portfolios, open projects, dependency edges and zoom levels,
 * compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.2
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { buildPortfolioLayout } from '../../src/utils/portfolioTimeline.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'portfolioLayout.golden.json')

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
 * A date in October or November 2026, or an odd value.
 *
 * @param {function(number): number} pick - the generator
 * @return {*} the date
 */
function day(pick) {
	if (pick(5) === 0) {
		return [undefined, null, '', 'not a date', 20261010][pick(5)]
	}
	return `2026-${pick(2) ? '10' : '11'}-${String(1 + pick(28)).padStart(2, '0')}`
}

/**
 * One seeded project with phases and tasks.
 *
 * @param {function(number): number} pick - the generator
 * @param {number} p - project index
 * @return {object} the project
 */
function project(pick, p) {
	const phases = []
	for (let i = 0; i < pick(3); i++) {
		phases.push({ id: `ph${p}-${i}`, title: ['Design', '', undefined][pick(3)], status: ['open', '', undefined][pick(3)], startDate: day(pick), endDate: day(pick) })
	}
	const tasks = []
	for (let i = 0; i < pick(5); i++) {
		tasks.push({ id: `t${p}-${i}`, title: ['Build', '', undefined][pick(3)], status: ['open', 'in_progress', 'done', 'blocked', undefined][pick(5)], startDate: day(pick), dueDate: day(pick) })
	}
	return {
		id: `p${p}`,
		title: ['Beta', 'alpha', 'Gamma', '', undefined][pick(5)],
		status: ['active', '', undefined][pick(3)],
		spanStart: day(pick),
		spanEnd: day(pick),
		phases: pick(4) === 0 ? undefined : phases,
		tasks: pick(4) === 0 ? undefined : tasks,
	}
}

/**
 * One seeded call: how many arguments it passed, and the layout.
 *
 * @param {number} seed - scenario seed
 * @return {object} the recorded case
 */
function run(seed) {
	const pick = random(seed)
	const projects = Array.from({ length: pick(5) }, (_, p) => project(pick, p))
	const endpointIds = projects.flatMap((one) => [
		...(one.tasks || []).map((task) => task.id),
		...(pick(3) === 0 ? [one.id, ...(one.phases || []).map((phase) => phase.id)] : []),
	])
	const openIds = projects.filter(() => pick(2)).map((one) => one.id)
	const dependencies = Array.from({ length: pick(5) }, (_, i) => ({
		id: pick(3) ? `d${i}` : undefined,
		blocker: endpointIds[pick(endpointIds.length + 1)] ?? 'missing',
		blocked: endpointIds[pick(endpointIds.length + 1)] ?? 'missing',
	}))
	const zoom = [undefined, 44, 18, 6][pick(4)]
	const args = [
		[projects, openIds, dependencies, zoom],
		[projects, openIds, dependencies],
		[projects, openIds],
		[projects, null, null],
		[null],
	][seed % 5]
	return { seed, args: args.length, layout: buildPortfolioLayout(...args) }
}

describe('buildPortfolioLayout golden', () => {
	it('matches the recorded fixture', () => {
		const results = [{ seed: 'none', layout: buildPortfolioLayout() }]
		for (let seed = 1; seed <= 120; seed++) {
			results.push(run(seed))
		}
		const actual = JSON.parse(JSON.stringify(results))
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual) + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	})
})
