/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Golden test for summariseByAssignee() in src/utils/portfolioHelpers.js:
 * seeded projects and tasks, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/changes/portfolio-people-capacity/tasks.md#task-1.1
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { summariseByAssignee } from '../../src/utils/portfolioHelpers.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'portfolioAssignees.golden.json')
const NOW = new Date(2026, 9, 15, 10, 0, 0)

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
 * A due date a number of days from NOW, or an odd value.
 *
 * @param {function(number): number} pick - the generator
 * @return {*} the due date
 */
function dueDate(pick) {
	const odd = [undefined, null, '', 'not a date']
	if (pick(4) === 0) {
		return odd[pick(odd.length)]
	}
	const day = new Date(2026, 9, 15 + pick(40) - 15)
	return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`
}

/**
 * One seeded input: projects, each with tasks.
 *
 * @param {number} seed - scenario seed
 * @return {Array<object>} projects with tasks
 */
function scenario(seed) {
	const pick = random(seed)
	const people = ['anna', 'bram', 'cor', '', 7, undefined]
	const result = []
	for (let p = 0; p < pick(4); p++) {
		const project = [{ id: `p${p}`, title: ['Beta', 'alpha', 'Gamma', ''][pick(4)] }, { id: p }, null][pick(3)]
		const tasks = []
		for (let t = 0; t < pick(7); t++) {
			tasks.push(pick(10) === 0
				? null
				: {
						status: ['open', 'in_progress', 'done', 'cancelled', 'blocked', undefined][pick(6)],
						assignedTo: people[pick(people.length)],
						sharedWith: [undefined, 'anna', ['bram', 'anna', 'bram'], ['', 'cor', 3], []][pick(5)],
						dueDate: dueDate(pick),
						remainingEstimate: [undefined, null, 30, 90, '45', 0, -5, 'x'][pick(8)],
						estimatedDuration: [undefined, 10, 120][pick(3)],
					})
		}
		result.push({ project, tasks: pick(8) === 0 ? null : tasks })
	}
	return result
}

describe('summariseByAssignee golden', () => {
	it('matches the recording', () => {
		const cases = []
		for (let seed = 1; seed <= 80; seed++) {
			cases.push({ seed, rows: summariseByAssignee(scenario(seed), NOW) })
		}
		cases.push({ seed: 'empty', rows: summariseByAssignee(null, NOW) })
		const actual = JSON.stringify(cases) + '\n'
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.mkdirSync(path.dirname(FIXTURE), { recursive: true })
			fs.writeFileSync(FIXTURE, actual)
		}
		expect(actual).toBe(fs.readFileSync(FIXTURE, 'utf8'))
	})
})
