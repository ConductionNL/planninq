/**
 * Golden test for the report helpers: the flow chart bands and the combined
 * portfolio flow, the portfolio timeline layout, the status report
 * suggestions and payload, and the risk payload and scale. Seeded inputs are
 * compared with a recorded fixture of every answer.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
import fs from 'node:fs'
import path from 'node:path'
import { afterAll, describe, expect, it } from 'vitest'
import { combineProjectFlows, stackBands } from '../../src/utils/flowChart.js'
import { buildPortfolioLayout, sortBySpan } from '../../src/utils/portfolioTimeline.js'
import { parseRiskScale, riskPayload } from '../../src/utils/riskHelpers.js'
import { reportPayload, suggestMoney, suggestRisk, suggestTime } from '../../src/utils/statusReports.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'reportHelpers.golden.json')
const RECORD = process.env.PLANNINQ_GOLDEN_RECORD === '1'

/**
 * A small deterministic pseudo-random generator.
 *
 * @param {number} seed - start value
 * @return {function(Array): *} picks one element of a list
 */
function random(seed) {
	let state = seed
	return (list) => {
		state = (state * 1103515245 + 12345) % 2147483648
		return list[Math.floor((state / 2147483648) * list.length)]
	}
}

/**
 * A list of a picked length built by a maker.
 *
 * @param {function(Array): *} pick - the generator
 * @param {function(): *} make - builds one element
 * @return {Array} the list
 */
function listOf(pick, make) {
	return Array.from({ length: pick([0, 1, 2, 4, 6]) }, make)
}

const DATES = ['2026-01-05', '2026-02-10', '2026-02-10T12:00:00', '2026-03-01', '', null, undefined, 'garbage', '2025-12-31']
const NUMBERS = [0, 1, 2, 3, 5, '4', '', null, undefined, -2, 2.5, 'x']
const TEXTS = ['', '  padded  ', 'Title', null, undefined, 7]

/**
 * Every helper's answer for one seed; thrown errors are recorded by name.
 *
 * @param {number} seed - scenario seed
 * @return {object} the answers
 */
function answers(seed) {
	const pick = random(seed)
	const ids = ['c1', 'c2', 'c3', 'p1', 'p2', 't1', 't2']
	const columns = pick([null, undefined, 'list', 'list', 'list']) === 'list' ? listOf(pick, () => ({ id: pick(ids), title: pick(['Open', 'Doing', 'Done', 'open']) })) : pick([null, undefined])
	const counts = () => pick([null, undefined, 'obj', 'obj']) === 'obj' ? Object.fromEntries(listOf(pick, () => [pick(ids), pick(NUMBERS)])) : pick([null, undefined])
	const days = pick([null, 'list', 'list']) === 'list' ? listOf(pick, () => ({ date: pick(['2026-01-01', '2026-01-02', '2026-01-03']), counts: counts() })) : null
	const flow = () => ({
		columns: pick([undefined, null, 'c']) === 'c' ? listOf(pick, () => ({ id: pick(ids), title: pick(['Open', 'Doing', 'Done']) })) : undefined,
		days: pick([undefined, 'd', 'd']) === 'd' ? listOf(pick, () => ({ date: pick(['2026-01-01', '2026-01-02', '2026-01-03']), counts: counts() })) : undefined,
		withoutHistory: pick(NUMBERS),
		finished: pick([undefined, 'f']) === 'f' ? listOf(pick, () => ({ leadDays: pick([1, 2, 5, 9]), cycleDays: pick([0, 1, 3, 8]), estimated: pick([true, false]) })) : undefined,
	})
	const task = () => pick([null, 't', 't', 't']) && { id: pick(ids), title: pick(TEXTS), status: pick(['open', 'done', 'cancelled', 'in_progress', undefined]), startDate: pick(DATES), dueDate: pick(DATES) }
	const project = () => ({
		id: pick(ids),
		title: pick(TEXTS),
		status: pick(['active', '', undefined]),
		spanStart: pick(DATES),
		spanEnd: pick(DATES),
		endDate: pick(DATES),
		phases: pick([undefined, 'p']) === 'p' ? listOf(pick, () => ({ id: pick(ids), title: pick(TEXTS), status: pick(['open', undefined]), startDate: pick(DATES), endDate: pick(DATES) })) : undefined,
		tasks: pick([undefined, 't']) === 't' ? listOf(pick, task) : undefined,
	})
	const projects = listOf(pick, project)
	const risk = () => ({ title: pick(TEXTS), score: pick([...NUMBERS, 6, 9, 12, 16, 25]), status: pick(['open', 'mitigating', 'closed', undefined]) })
	const fields = pick([null, undefined, 'f', 'f', 'f']) === 'f'
		? {
				title: pick(TEXTS),
				likelihood: pick(NUMBERS),
				impact: pick(NUMBERS),
				status: pick(['open', 'mitigating', 'closed', 'occurred', 'other', undefined]),
				response: pick(['avoid', 'reduce', 'transfer', 'accept', 'other', undefined]),
				description: pick(TEXTS),
				category: pick(TEXTS),
				countermeasures: pick(TEXTS),
				owner: pick(['', 'anna', undefined]),
				reviewDate: pick(['', '2026-05-01', undefined]),
				reportDate: pick(DATES),
				money: pick([undefined, { status: pick(['onTrack', 'atRisk', 'offTrack', 'bad', undefined]), note: pick(TEXTS) }]),
				time: { status: pick(['onTrack', 'offTrack', undefined]), note: pick(TEXTS) },
				risk: pick([undefined, null, { status: 'atRisk' }]),
				quality: { note: pick(TEXTS) },
			}
		: pick([null, undefined])
	const scale = pick([
		undefined,
		'{"levels":3,"likelihood":["a","b","c"],"impact":["a","b","c"],"thresholds":{"medium":3,"high":6}}',
		{ levels: 4, likelihood: [1, 2, 3, 4], impact: [1, 2, 3, 4], thresholds: { medium: 4, high: 9 } },
		{ levels: 4, likelihood: [1, 2, 3], impact: [1, 2, 3, 4], thresholds: { medium: 4, high: 9 } },
		{ levels: 4, likelihood: [1, 2, 3, 4], impact: [1, 2, 3, 4, 5], thresholds: { medium: 4, high: 9 } },
		{ levels: 6, likelihood: [1, 2, 3, 4, 5, 6], impact: [1, 2, 3, 4, 5, 6], thresholds: { medium: 4, high: 9 } },
		{ levels: 2, likelihood: [1, 2], impact: [1, 2], thresholds: { medium: 1, high: 2 } },
		{ levels: 3.5, likelihood: [], impact: [], thresholds: {} },
		{ levels: 3, likelihood: 'abc', impact: [1, 2, 3], thresholds: { medium: 2, high: 4 } },
		{ levels: 3, likelihood: [1, 2, 3], impact: 'abc', thresholds: { medium: 2, high: 4 } },
		{ levels: 3, likelihood: [1, 2, 3], impact: [1, 2, 3], thresholds: { medium: 2.5, high: 4 } },
		{ levels: 3, likelihood: [1, 2, 3], impact: [1, 2, 3], thresholds: { medium: 2 } },
		{ levels: 5, likelihood: [1, 2, 3, 4, 5], impact: [1, 2, 3, 4, 5], thresholds: { medium: 5, high: 12 } },
		'not json',
		null,
	])
	const out = {}
	const record = (name, fn) => {
		try {
			out[name] = fn() ?? null
		} catch (err) {
			out[name] = `throws ${err.name}`
		}
	}
	record('stackBands', () => stackBands(columns, days))
	record('combineProjectFlows', () => combineProjectFlows(pick([null, undefined, 'l', 'l', 'l']) === 'l' ? listOf(pick, flow) : null))
	record('sortBySpan', () => sortBySpan(pick([undefined, null, 'l', 'l']) === 'l' ? projects : undefined).map((p) => p.id))
	record('buildPortfolioLayout', () => buildPortfolioLayout(projects, pick([[], ['p1'], ids, null]), [{ blocker: 't1', blocked: 't2' }, { id: 'd1', blocker: 't2', blocked: 't1' }, { blocker: 'p1', blocked: 't1' }], pick([undefined, 6, 44])))
	record('suggestMoney', () => suggestMoney(pick([undefined, {}, { budget: pick(NUMBERS), cost: pick([...NUMBERS, 1000, 95, 90, 91, 101, 50]) }, { budget: 100, cost: pick([null, undefined, 0, 89, 90, 91, 100, 101, '95', 'x']) }])))
	record('suggestTime', () => suggestTime(pick([undefined, null, 'l']) === 'l' ? listOf(pick, task) : undefined, pick([undefined, null, project()]), pick([undefined, '2026-02-10', '2026-01-01', '2027-01-01'])))
	record('suggestTimeEnd', () => suggestTime([{ status: 'open', dueDate: pick(DATES) }], { endDate: pick(DATES) }, '2026-02-10'))
	record('suggestMoneyShare', () => suggestMoney({ budget: pick([3, 7, 9]), cost: pick([1, 2, 5, 8]) }))
	record('suggestRisk', () => suggestRisk(pick([undefined, null, 'l', 'l']) === 'l' ? listOf(pick, risk) : null, parseRiskScale(scale)))
	record('parseRiskScale', () => parseRiskScale(scale))
	record('reportPayload', () => {
		const payload = reportPayload(fields, pick(['p1', undefined]))
		return { ...payload, reportDate: payload.reportDate.length }
	})
	record('reportPayloadDate', () => (fields?.reportDate && typeof fields.reportDate === 'string' && fields.reportDate.length >= 10 ? reportPayload(fields, 'p1').reportDate : 'today'))
	record('riskPayload', () => riskPayload(fields, pick(['p1', undefined])))
	return JSON.parse(JSON.stringify(out))
}

describe('report helpers (golden)', () => {
	const recorded = RECORD ? {} : JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))

	for (let seed = 1; seed <= 250; seed++) {
		it(`seed ${seed}`, () => {
			const outcome = answers(seed)
			if (RECORD) {
				recorded[seed] = outcome
			} else {
				expect(outcome).toEqual(recorded[seed])
			}
		})
	}

	afterAll(() => {
		if (RECORD) {
			fs.writeFileSync(FIXTURE, JSON.stringify(recorded, null, '\t') + '\n')
		}
	})
})
