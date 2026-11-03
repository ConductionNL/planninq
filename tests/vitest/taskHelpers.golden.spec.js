/**
 * Golden test for the task helpers in src/utils/taskHelpers.js: seeded
 * task lists, dependency edges and due dates, compared with a recorded
 * fixture of every helper's answer.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
import fs from 'node:fs'
import path from 'node:path'
import { afterAll, describe, expect, it } from 'vitest'
import {
	deriveBlockedTaskIds,
	dueDateStatus,
	groupTasksByStatus,
	isBlocked,
	linkGroups,
	openBlockerIds,
	statusMapFromTasks,
} from '../../src/utils/taskHelpers.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'taskHelpers.golden.json')
const RECORD = process.env.PLANNINQ_GOLDEN_RECORD === '1'
const NOW = new Date(2026, 5, 15, 13, 30)

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

const IDS = ['a', 'b', 'c', 'd', '', undefined, null, 'toString']
const STATUSES = ['open', 'in_progress', 'blocked', 'done', 'cancelled', undefined, '', 'legacy', 'constructor']
const TYPES = [undefined, '', 'blocks', 'relates', 'duplicates', null]
const DUES = [null, undefined, '', 'garbage', '2026-06-13', '2026-06-14T23:59:00', '2026-06-15', '2026-06-16', '2026-06-17T00:00:00', '2026-06-18', '2027-01-01', new Date(2026, 5, 14, 1), new Date('x'), 0, 1781500000000]

/**
 * One seeded task.
 *
 * @param {function(Array): *} pick - the generator
 * @return {*} the task
 */
function task(pick) {
	const shape = pick([0, 1, 2, 3, 4])
	if (shape === 0) {
		return pick([null, undefined])
	}
	const id = pick(IDS)
	const body = { status: pick(STATUSES), dueDate: pick(DUES), title: `t-${id}` }
	if (shape === 1) {
		return { '@self': { id }, ...body }
	}
	if (shape === 2) {
		return { id: pick(IDS), '@self': { id }, ...body }
	}
	return { id, ...body }
}

/**
 * One seeded dependency edge.
 *
 * @param {function(Array): *} pick - the generator
 * @return {*} the edge
 */
function edge(pick) {
	if (pick([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]) === 0) {
		return pick([null, undefined])
	}
	return { id: `e${pick([1, 2, 3, 4, 5])}`, blocker: pick(IDS), blocked: pick(IDS), type: pick(TYPES) }
}

/**
 * The status map for a seed, sometimes with undefined values.
 *
 * @param {function(Array): *} pick - the generator
 * @return {object} uuid to status
 */
function statusMap(pick) {
	const map = {}
	for (const id of ['a', 'b', 'c', 'd', '', 'toString']) {
		if (pick([true, false, true])) {
			map[id] = pick(STATUSES)
		}
	}
	return map
}

const SEEDS = Array.from({ length: 160 }, (_, i) => i + 1)

/**
 * Every helper's answer for one seed; thrown errors are recorded by name.
 *
 * @param {number} seed - scenario seed
 * @return {object} the answers
 */
function answers(seed) {
	const pick = random(seed)
	const tasks = pick(['list', 'list', 'list', 'list', null, undefined]) === 'list'
		? Array.from({ length: pick([0, 1, 3, 6, 9]) }, () => task(pick))
		: pick([null, undefined])
	const edges = pick([null, undefined, [], 'list', 'list', 'list', 'list']) === 'list'
		? Array.from({ length: pick([1, 3, 6, 10]) }, () => edge(pick))
		: pick([null, undefined, []])
	const statusById = pick([0, 1, 2, 3]) === 0 ? pick([undefined, {}]) : statusMap(pick)
	const taskId = pick(IDS)
	const statuses = pick([undefined, undefined, ['done', 'open'], ['x'], ['open', 'open', 'done']])
	const out = {}
	const record = (name, fn) => {
		try {
			out[name] = fn() ?? null
		} catch (err) {
			out[name] = `throws ${err.name}`
		}
	}
	record('statusMapFromTasks', () => statusMapFromTasks(tasks ?? undefined))
	record('isBlocked', () => IDS.map((id) => isBlocked(id, edges ?? undefined, statusById)))
	record('isBlockedNullEdges', () => isBlocked(taskId, edges))
	record('deriveBlockedTaskIds', () => deriveBlockedTaskIds(edges ?? undefined, statusById))
	record('deriveNullEdges', () => deriveBlockedTaskIds(edges))
	record('openBlockerIds', () => IDS.map((id) => openBlockerIds(id, edges ?? undefined, statusById)))
	record('openNullEdges', () => openBlockerIds(taskId, edges))
	record('linkGroups', () => IDS.map((id) => linkGroups(id, edges ?? undefined)))
	record('linkNullEdges', () => linkGroups(taskId, edges))
	record('groupTasksByStatus', () => {
		const grouped = groupTasksByStatus(tasks ?? undefined, statuses)
		return Object.fromEntries(Object.entries(grouped).map(([key, list]) => [key, list.map((t) => t?.title)]))
	})
	record('groupNullTasks', () => Object.keys(groupTasksByStatus(tasks, statuses)))
	record('dueDateStatus', () => (tasks || []).map((t) => dueDateStatus(t, NOW)))
	record('dueDateStatusNullTask', () => [dueDateStatus(null, NOW), dueDateStatus(undefined, NOW)])
	return JSON.parse(JSON.stringify(out))
}

describe('task helpers (golden)', () => {
	const recorded = RECORD ? {} : JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))

	for (const seed of SEEDS) {
		it(`seed ${seed}`, () => {
			const outcome = answers(seed)
			if (RECORD) {
				recorded[seed] = outcome
			} else {
				expect(outcome).toEqual(recorded[seed])
			}
		})
	}

	it('answers a due date relative to the default clock', () => {
		const tomorrow = new Date()
		tomorrow.setDate(tomorrow.getDate() + 1)
		expect(dueDateStatus({ dueDate: tomorrow })).toBe('approaching')
	})

	afterAll(() => {
		if (RECORD) {
			fs.writeFileSync(FIXTURE, JSON.stringify(recorded, null, '\t') + '\n')
		}
	})
})
