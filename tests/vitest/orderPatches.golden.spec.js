/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Golden test for orderPatchesForStep() in src/utils/columnHelpers.js:
 * seeded lanes, moved cards and directions, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.4
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { orderPatchesForStep } from '../../src/utils/columnHelpers.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'orderPatches.golden.json')

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
 * A card's stored order: spaced, crowded, missing or odd.
 *
 * @param {function(number): number} pick - the generator
 * @param {number} i - the card's index
 * @return {*} the columnOrder value
 */
function order(pick, i) {
	return [
		(i + 1) * 1000,
		i,
		i * 2,
		undefined,
		null,
		'x',
		String((i + 1) * 500),
		-1000 + i * 3,
	][pick(8)]
}

/**
 * One seeded call and its patches.
 *
 * @param {number} seed - scenario seed
 * @return {object} the recorded case
 */
function run(seed) {
	const pick = random(seed)
	const style = pick(4)
	const lane = Array.from({ length: pick(6) }, (_, i) => {
		const key = `c${i}`
		const card = [{ id: key }, { uuid: key }, { '@self': { id: key } }][pick(3)]
		card.columnOrder = style === 0 ? order(pick, i) : [(i + 1) * 1000, i, i * 3][style - 1]
		return card
	})
	const task = pick(6) === 0 ? { id: 'stranger' } : lane[pick(lane.length + 1)] ?? { id: 'c0' }
	const direction = [-1, 1, 0, 2, -2][pick(5)]
	const laneTasks = pick(10) === 0 ? null : lane
	return { seed, laneTasks, task, direction, patches: orderPatchesForStep(laneTasks, task, direction) }
}

describe('orderPatchesForStep golden', () => {
	it('matches the recorded fixture', () => {
		const results = []
		for (let seed = 1; seed <= 200; seed++) {
			results.push(run(seed))
		}
		const actual = JSON.parse(JSON.stringify(results))
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual) + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	})
})
