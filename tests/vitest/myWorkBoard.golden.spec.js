/**
 * Golden test for the my work, board view, label, column rule and timeline
 * helpers and the timeline reads: seeded inputs and scripted server answers,
 * compared with a recorded fixture of every answer.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
 */
import fs from 'node:fs'
import path from 'node:path'
import { afterAll, describe, expect, it, vi } from 'vitest'

const FIXTURE = path.join(__dirname, 'fixtures', 'myWorkBoard.golden.json')
const RECORD = process.env.PLANNINQ_GOLDEN_RECORD === '1'
const server = { answers: [], calls: [] }

vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: async (url, config) => {
			server.calls.push([url, config])
			const answer = server.answers.shift()
			if (answer instanceof Error) {
				throw answer
			}
			return { data: answer }
		},
	},
}))

const { fetchPortfolioTimeline, fetchProjectTimeline } = await import('../../src/api/timeline.js')
const { ruleEffects, ruleProblem } = await import('../../src/utils/columnAutomation.js')
const { resolveTaskLabels } = await import('../../src/utils/labelHelpers.js')
const { filterMyTasks, groupMyTasks, movePinned } = await import('../../src/utils/myWork.js')
const { mergeViewResults } = await import('../../src/utils/projectsView.js')
const { buildLayout } = await import('../../src/utils/timelineHelpers.js')

const TODAY = new Date(2026, 5, 17, 9, 0)

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
	return Array.from({ length: pick([0, 1, 3, 6, 10, 14]) }, make)
}

const IDS = ['a', 'b', 'c', 'p1', 'p2', '', null, undefined, 3]
const DUES = [null, undefined, '', 'garbage', '2026-06-10', '2026-06-16', '2026-06-17', '2026-06-17T23:00:00', '2026-06-18', '2026-06-21', '2026-06-22', '2026-07-01']

/**
 * Every answer for one seed; thrown errors are recorded by name.
 *
 * @param {number} seed - scenario seed
 * @return {Promise<object>} the answers
 */
async function answers(seed) {
	const pick = random(seed)
	const task = () => pick([null, 't', 't', 't', 't']) && {
		id: pick(IDS),
		'@self': pick([undefined, { id: pick(IDS) }]),
		uuid: pick([undefined, 'u1']),
		title: pick(['B', 'a', 'C', '', undefined]),
		project: pick(['p1', 'p2', undefined]),
		status: pick(['open', 'in_progress', 'done', 'cancelled', 'blocked', undefined]),
		priority: pick(['urgent', 'high', 'normal', 'low', 'weird', undefined]),
		assignedTo: pick(['anna', 'bram', '', undefined]),
		sharedWith: pick([undefined, ['anna'], []]),
		dueDate: pick(DUES),
		completedAt: pick([undefined, '2026-06-17T08:00:00', '2026-06-16', 'x']),
		labels: pick([undefined, null, 'l1', [], ['l1', 'l2'], ['l2', 'l2', 'zz', null, 3], [3]]),
		startDay: pick([1, 4, 9, 20]),
		endDay: pick([2, 4, 15, 30]),
	}
	const tasks = pick([null, undefined, 'l', 'l', 'l']) === 'l' ? listOf(pick, task) : pick([null, undefined])
	const out = {}
	const record = async (name, fn) => {
		try {
			out[name] = (await fn()) ?? null
		} catch (err) {
			out[name] = `throws ${err.name}`
		}
	}
	const ids = (list) => list.map((t) => t?.title ?? null)
	await record('groupMyTasks', () => groupMyTasks(tasks ?? undefined, pick([TODAY, new Date(2026, 5, 21), new Date(2026, 5, 15)])).map((g) => [g.id, ids(g.tasks)]))
	await record('groupNull', () => groupMyTasks(tasks, TODAY).map((g) => g.tasks.length))
	for (const figure of ['', 'open', 'overdue', 'in_progress', 'completed_today', 'other']) {
		await record(`filter ${figure}`, () => ids(filterMyTasks(tasks ?? undefined, pick(['anna', '', undefined]), figure, TODAY)))
	}
	await record('filterNull', () => filterMyTasks(tasks, 'anna').length)
	const order = pick([undefined, null, [], ['a', 'b', 'c'], ['a'], ['b', 'a']])
	await record('movePinned', () => [-2, -1, 1, 2, undefined].map((step) => movePinned(order ?? undefined, pick(['a', 'b', 'c', 'x', undefined]), step)))
	await record('movePinnedNull', () => movePinned(order, 'a', -1))
	const results = pick([null, 'l', 'l', 'l']) === 'l'
		? listOf(pick, () => pick([null, { projectId: pick(['p1', 'p2']), project: pick([null, undefined, { id: 'p' }]), tasks: pick([undefined, 'l', 'l']) === 'l' ? listOf(pick, task) : undefined }]))
		: null
	await record('mergeViewResults', () => {
		const merged = mergeViewResults(results)
		return { ...merged, tasks: ids(merged.tasks) }
	})
	const labels = pick([null, undefined, 'x', [], 'l', 'l']) === 'l'
		? [{ id: 'l1', title: 'Zeta' }, { '@self': { id: 'l2' }, title: 'alpha' }, { uuid: 'zz', title: 'Mid' }, { id: '3', title: 'num' }]
		: pick([null, [], 'x'])
	await record('resolveTaskLabels', () => (tasks || []).map((t) => resolveTaskLabels(t, labels).map((l) => l.title)))
	const rule = pick([null, undefined, { action: pick(['assign', 'addLabel', 'setPriority', undefined]), value: pick(['anna', 'l1', '', undefined, 'zed']) }])
	await record('ruleProblem', () => [
		ruleProblem(rule, pick([undefined, null, ['anna'], ['bram']]), pick([undefined, null, ['l1'], ['l9']])),
		ruleProblem(rule, ['anna', 'zed'], ['l1', 'zed']),
	])
	const sent = pick([null, undefined, { assignedTo: pick(['anna', null, undefined, 3]), priority: pick(['high', '', undefined, 'normal']), labels: pick([undefined, 'l1', ['l1'], ['l1', 'l2']]) }])
	const stored = pick([null, undefined, {}, { assignedTo: pick(['anna', null, undefined, 'bram', '3']), priority: pick(['high', '', undefined, 'normal', 'low']), labels: pick([undefined, 'l1', ['l1'], ['l1', 'l3'], []]) }, { priority: 'normal', labels: ['l2'] }, { assignedTo: undefined }])
	await record('ruleEffects', () => ruleEffects(sent, stored))
	const deps = pick([null, undefined, 'x', 'l', 'l']) === 'l'
		? listOf(pick, () => ({ id: pick([undefined, 'd1', '']), blocker: pick(IDS), blocked: pick(IDS), type: pick([undefined, '', 'blocks', 'relates']) }))
		: pick([null, undefined, 'x'])
	await record('buildLayout', () => buildLayout(pick([undefined, null, 'x', (tasks || []).filter(Boolean)]), deps, pick([undefined, 6, 18])))
	const timelineAnswer = () => pick([
		undefined,
		null,
		new Error('down'),
		{},
		{ projectId: 'p9', window: { from: 'a', to: 'b' }, tasks: [{ id: 't' }], unscheduled: 'x', dependencies: [{ id: 'd' }], projects: [{ id: 'p' }], skipped: ['s'] },
		{ projectId: '', window: null, tasks: null, unscheduled: [{ id: 'u' }], dependencies: {}, projects: 'x', skipped: null },
	])
	server.calls = []
	server.answers = [timelineAnswer()]
	await record('fetchProjectTimeline', () => fetchProjectTimeline('p1', pick([undefined, null, '', '2026-01-01']), pick([undefined, null, '2026-12-31'])))
	await record('fetchProjectTimelineCalls', () => server.calls)
	server.calls = []
	const projectIds = pick([undefined, null, [], ['p1', 'p1', '', null, 4], Array.from({ length: 120 }, (_, i) => `p${i % 101}`)])
	server.answers = [timelineAnswer(), timelineAnswer(), timelineAnswer()]
	await record('fetchPortfolioTimeline', () => fetchPortfolioTimeline(projectIds))
	await record('fetchPortfolioTimelineCalls', () => server.calls.map(([url, config]) => [url, config.params.projects.split(',').length]))
	return JSON.parse(JSON.stringify(out))
}

describe('my work, board and timeline helpers (golden)', () => {
	const recorded = RECORD ? {} : JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))

	for (let seed = 1; seed <= 400; seed++) {
		it(`seed ${seed}`, async () => {
			const outcome = await answers(seed)
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
