/**
 * Golden test for the board filter in src/utils/boardFilter.js: seeded
 * filters, page queries and tasks, compared with a recorded fixture of the
 * normalised, encoded and decoded filters, the due values and every match.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
import fs from 'node:fs'
import path from 'node:path'
import { afterAll, describe, expect, it } from 'vitest'
import { applySavedFilter, decodeFilter, dueValue, encodeFilter, matchesFilter, normaliseFilter, withFilterQuery } from '../../src/utils/boardFilter.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'boardFilter.golden.json')
const RECORD = process.env.PLANNINQ_GOLDEN_RECORD === '1'
const TODAY = new Date(2026, 5, 17, 10, 0)

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

const VALUES = {
	assignee: ['me', 'unassigned', 'anna', 'bram', '', null, 3],
	label: ['l1', 'l2', '3', '', null],
	priority: ['low', 'normal', 'high', 'urgent', ''],
	due: ['overdue', 'thisWeek', 'none', ''],
}
const DUES = [null, undefined, '', 'garbage', '2026-06-10', '2026-06-16', '2026-06-17', '2026-06-17T23:30:00', '2026-06-21', '2026-06-22', '2026-07-01', new Date(2026, 5, 19, 22)]

/**
 * Every answer for one seed; thrown errors are recorded by name.
 *
 * @param {number} seed - scenario seed
 * @return {object} the answers
 */
function answers(seed) {
	const pick = random(seed)
	const entry = (dimension) => pick([
		undefined,
		null,
		'x',
		{ op: 'is' },
		{ op: pick(['is', 'isNot', 'other', undefined]), values: Array.from({ length: pick([0, 1, 2, 3]) }, () => pick(VALUES[dimension])) },
		{ op: pick(['is', 'isNot']), values: Array.from({ length: pick([1, 2]) }, () => pick(VALUES[dimension])) },
	])
	const raw = pick([null, undefined, 'x', 'f', 'f', 'f']) === 'f'
		? { assignee: entry('assignee'), label: entry('label'), priority: entry('priority'), due: entry('due'), other: { values: ['x'] } }
		: pick([null, undefined, 'x'])
	const query = pick([null, undefined, 'q', 'q', 'q']) === 'q'
		? Object.fromEntries(['assignee', 'assignee!', 'label', 'label!', 'priority', 'priority!', 'due', 'due!', 'page']
				.filter(() => pick([true, false, false]))
				.map((key) => [key, pick(['me,anna', 'l1', '', 'low,,high', 'overdue', 3, undefined, null, 'none,thisWeek'])]))
		: pick([null, undefined])
	const tasks = Array.from({ length: 8 }, () => pick([null, undefined, 't', 't', 't', 't']) && {
		assignedTo: pick(['anna', 'bram', '', null, undefined, 3]),
		labels: pick([undefined, null, 'l1', [], ['l1'], ['l2', null, 3], ['3']]),
		priority: pick(['low', 'normal', 'high', 'urgent', '', undefined]),
		status: pick(['open', 'done', 'cancelled', undefined]),
		dueDate: pick(DUES),
	})
	const out = {}
	const record = (name, fn) => {
		try {
			out[name] = fn() ?? null
		} catch (err) {
			out[name] = `throws ${err.name}`
		}
	}
	const filter = normaliseFilter(raw)
	record('normaliseFilter', () => filter)
	record('encodeFilter', () => encodeFilter(pick([filter, raw, null])))
	record('decodeFilter', () => decodeFilter(query))
	record('roundTrip', () => decodeFilter(encodeFilter(filter)))
	record('withFilterQuery', () => withFilterQuery(query, filter))
	record('dueValue', () => tasks.map((task) => dueValue(task, TODAY)))
	record('dueValueSunday', () => tasks.map((task) => dueValue(task, new Date(2026, 5, 21, 23))))
	record('matchesFilter', () => tasks.map((task) => matchesFilter(task, pick([filter, raw, decodeFilter(query)]), pick(['anna', 'bram', null, undefined, 3]), TODAY)))
	record('applySavedFilter', () => applySavedFilter(pick([undefined, null, { criteria: raw }, { criteria: filter }]), pick([undefined, null, ['l1'], ['l1', 'l2', '3']])))
	return JSON.parse(JSON.stringify(out))
}

describe('board filter (golden)', () => {
	const recorded = RECORD ? {} : JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))

	for (let seed = 1; seed <= 400; seed++) {
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
