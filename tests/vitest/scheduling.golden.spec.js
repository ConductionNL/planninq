/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Golden test for cascade() in src/utils/scheduling.js: seeded task graphs,
 * calendars and date changes, compared with a recorded fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.2
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { cascade } from '../../src/utils/scheduling.js'
import { normaliseCalendar } from '../../src/utils/workingCalendar.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'scheduling.golden.json')

const CALENDARS = [
	normaliseCalendar({}),
	normaliseCalendar({ working_weekdays: [1, 2, 3, 4], non_working_days: [{ date: '2026-10-14', name: 'Closed' }, { date: '2026-10-20' }] }),
	normaliseCalendar({ working_weekdays: [1, 2, 3, 4, 5, 6, 7] }),
]

/**
 * A small deterministic pseudo-random generator.
 *
 * @param {number} seed - start value
 * @return {function(): number} next value in [0, 1)
 */
function random(seed) {
	let state = seed
	return () => {
		state = (state * 1103515245 + 12345) % 2147483648
		return state / 2147483648
	}
}

/**
 * A date key a number of days after 2026-10-01.
 *
 * @param {number} offset - days
 * @return {string} YYYY-MM-DD
 */
function dateAt(offset) {
	return new Date(Date.UTC(2026, 9, 1 + offset)).toISOString().slice(0, 10)
}

/**
 * One seeded scenario: tasks, edges, the changed task and its new dates.
 *
 * @param {number} seed - scenario seed
 * @return {object} the scenario
 */
function scenario(seed) {
	const next = random(seed)
	const count = 2 + Math.floor(next() * 7)
	const tasks = []
	for (let i = 0; i < count; i++) {
		const start = Math.floor(next() * 30)
		const roll = next()
		tasks.push({
			id: `T${i}`,
			title: `Task ${i}`,
			startDate: roll < 0.08 ? '' : `${dateAt(start)}${roll > 0.9 ? 'T08:00:00Z' : ''}`,
			dueDate: roll > 0.08 && roll < 0.14 ? null : dateAt(start - 2 + Math.floor(next() * 8)),
		})
	}
	const edges = []
	const types = [undefined, 'blocks', 'blocks', 'relates', '']
	for (let i = 0; i < count * 2; i++) {
		const blocker = Math.floor(next() * (count - 1))
		const blocked = next() < 0.85 ? blocker + 1 + Math.floor(next() * (count - 1 - blocker)) : count + 3
		edges.push({ blocker: `T${blocker}`, blocked: `T${blocked}`, type: types[Math.floor(next() * types.length)] })
	}
	const changed = Math.floor(next() * (count + 1))
	const start = Math.floor(next() * 30)
	return {
		tasks,
		edges,
		changedId: `T${changed}`,
		dates: { startDate: dateAt(start), dueDate: dateAt(start + Math.floor(next() * 12)) },
		calendar: Math.floor(next() * CALENDARS.length),
	}
}

/**
 * Run cascade() for one scenario.
 *
 * @param {object} input - the scenario
 * @return {Array<object>} the moves
 */
function run(input) {
	return cascade(
		{ tasks: input.tasks, edges: input.edges, calendar: CALENDARS[input.calendar] },
		{ id: input.changedId, dates: input.dates },
	)
}

/**
 * Every recorded case.
 *
 * @return {Array<object>} scenario and result per case
 */
function recordAll() {
	const cases = []
	for (let seed = 1; seed <= 150; seed++) {
		const input = scenario(seed)
		cases.push({ seed, moves: run(input) })
	}
	const fixed = [
		{ tasks: null, edges: null, changedId: 'A', dates: { startDate: '2026-10-01', dueDate: '2026-10-02' }, calendar: 0 },
		{
			tasks: [{ id: 'A', title: 'A', startDate: '2026-10-05', dueDate: '2026-10-06' }, { id: 'B', title: 'B', startDate: '2026-10-07', dueDate: '2026-10-08' }],
			edges: null,
			changedId: 'A',
			dates: { startDate: '2026-10-05', dueDate: '2026-10-20' },
			calendar: 0,
		},
		{
			tasks: [
				{ id: 'A', title: 'A', startDate: '2026-10-05', dueDate: '2026-10-06' },
				{ id: 'B', title: 'B', startDate: '2026-10-07', dueDate: '2026-10-08' },
				{ id: 'C', title: 'C', startDate: '2026-10-07', dueDate: '2026-10-09' },
				{ id: 'D', title: 'D', startDate: '2026-10-12', dueDate: '2026-10-12' },
			],
			edges: [{ blocker: 'A', blocked: 'B' }, { blocker: 'A', blocked: 'C' }, { blocker: 'B', blocked: 'D' }, { blocker: 'C', blocked: 'D' }],
			changedId: 'A',
			dates: { startDate: '2026-10-05', dueDate: '2026-10-09' },
			calendar: 1,
		},
		{
			tasks: [{ id: 'A', title: 'A', startDate: '2026-10-05', dueDate: '2026-10-06' }, { id: 'B', title: 'B', startDate: '2026-10-07', dueDate: '2026-10-08' }],
			edges: [{ blocker: 'A', blocked: 'B' }, { blocker: 'B', blocked: 'A' }],
			changedId: 'A',
			dates: { startDate: '2026-10-05', dueDate: '2026-10-07' },
			calendar: 2,
		},
	]
	for (const [index, input] of fixed.entries()) {
		cases.push({ fixed: index, moves: run(input) })
	}
	return cases
}

describe('cascade golden', () => {
	it('matches the recording', () => {
		const actual = JSON.stringify(recordAll(), null, 1) + '\n'
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.mkdirSync(path.dirname(FIXTURE), { recursive: true })
			fs.writeFileSync(FIXTURE, actual)
		}
		expect(actual).toBe(fs.readFileSync(FIXTURE, 'utf8'))
	})
})
