/**
 * Golden test for fetchEvery() in src/utils/fetchEvery.js: seeded paged
 * collections, compared with a recorded fixture of the rows returned and the
 * arguments of every fetchCollection call.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/specs/projects/spec.md
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { fetchEvery } from '../../src/utils/fetchEvery.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'fetchEvery.golden.json')
const FULL = 1000

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
 * One row, keyed by id, uuid, @self.id, or not at all.
 *
 * @param {function(number): number} pick - the generator
 * @return {*} the row
 */
function row(pick) {
	const key = `r${pick(1500)}`
	return [{ id: key }, { uuid: key }, { '@self': { id: key } }, { title: 'no key' }, null, { id: 0 }][pick(6)]
}

/**
 * The pages one seeded server answers with, and whether it ignores `_page`.
 * Seeds 101 and 102 are full pages that add no rows and one row.
 *
 * @param {number} seed - scenario seed
 * @return {{pages: Array<*>, ignoresPage: boolean}} the server's behaviour
 */
function scenario(seed) {
	const keyed = (from) => Array.from({ length: FULL }, (_, i) => ({ id: `k${from + i}` }))
	if (seed === 101) {
		return { pages: [keyed(0)], ignoresPage: true }
	}
	if (seed === 102) {
		return { pages: [keyed(0), keyed(1), [{ id: 'last' }]], ignoresPage: false }
	}
	const pick = random(seed)
	const pages = []
	for (let p = 0; p < pick(4); p++) {
		const kind = pick(5)
		if (kind === 0) {
			pages.push([undefined, null, { results: [] }, 'x'][pick(4)])
			continue
		}
		const size = kind === 1 ? FULL : pick(30)
		pages.push(Array.from({ length: size }, () => row(pick)))
	}
	return { pages, ignoresPage: pick(5) === 0 }
}

/**
 * Run fetchEvery against one seeded server, recording the passed arguments.
 *
 * @param {number} seed - scenario seed
 * @return {Promise<object>} the recorded calls and result
 */
async function run(seed) {
	const pick = random(seed + 7)
	const { pages, ignoresPage } = scenario(seed)
	const calls = []
	const store = {
		fetchCollection(...args) {
			calls.push(args)
			if (calls.length > 50) {
				throw new Error('runaway paging')
			}
			const index = ignoresPage ? 0 : args[1]._page - 1
			return Promise.resolve(pages[index] ?? [])
		},
	}
	const filters = [{ project: 'p1' }, {}, { project: 'p2', _limit: 5 }][pick(3)]
	let rows
	try {
		rows = pick(4) === 0
			? await fetchEvery(store, 'task')
			: await fetchEvery(store, 'task', filters)
	} catch (error) {
		return { seed, calls: calls.slice(0, 3), callCount: calls.length, error: error.message }
	}
	return { seed, calls, count: rows.length, rows: rows.slice(0, 40), tail: rows.slice(-5) }
}

describe('fetchEvery golden', () => {
	it('matches the recorded fixture', async () => {
		const results = []
		for (let seed = 1; seed <= 60; seed++) {
			results.push(await run(seed))
		}
		results.push(await run(101), await run(102))
		const actual = JSON.parse(JSON.stringify(results))
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual) + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	})
})
