/**
 * Golden test for the write actions of the labels, time entries and
 * dependencies stores and the collection read of the object module: every
 * action runs against a scripted server, and the fixture records its result,
 * the requests it sent, what it logged and the store state afterwards.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 * @spec openspec/specs/time-tracking/spec.md
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
import fs from 'node:fs'
import path from 'node:path'
import { createPinia, setActivePinia } from 'pinia'
import { afterAll, describe, expect, it, vi } from 'vitest'

const FIXTURE = path.join(__dirname, 'fixtures', 'storeWrites.golden.json')
const RECORD = process.env.PLANNINQ_GOLDEN_RECORD === '1'

const world = { answers: [], log: [] }

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ h: 1 }) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {}, fetchCollection: async () => [] }),
}))

const { useLabelsStore } = await import('../../src/store/labels.js')
const { useTimeEntriesStore } = await import('../../src/store/timeEntries.js')
const { useDependenciesStore } = await import('../../src/store/dependencies.js')
const { useObjectStore } = await import('../../src/store/modules/object.js')

/**
 * Whether a scripted answer is a thrown error.
 *
 * @param {object} step - scripted answer
 * @return {boolean} true for a failure step
 */
function isFailure(step) {
	return step.throws !== undefined
}

/**
 * A fetch that answers from the scripted queue and records each request.
 *
 * @param {string} url - request url
 * @param {object} init - request options
 * @return {Promise<object>} the scripted response
 */
async function fakeFetch(url, init = {}) {
	world.log.push(['fetch', url, init.method ?? 'GET', init.body ?? null, init.headers ?? null])
	const step = world.answers.shift() ?? { ok: true, body: { labels: [{ id: 'l1' }] } }
	if (isFailure(step)) {
		const err = new Error(step.throws ?? '')
		if (step.throws === null) {
			err.message = ''
		}
		throw err
	}
	let reads = 0
	return {
		ok: step.ok,
		status: step.status ?? (step.ok ? 200 : 500),
		json: async () => {
			reads++
			if (step.badJson === true || (step.badJson === 'second' && reads > 1)) {
				throw new SyntaxError('bad json')
			}
			return step.body
		},
	}
}

const OK = [
	{ ok: true, body: { id: 'x1', '@self': { id: 'x2' }, blocker: 'b1', blocked: 'b2', type: 'relates', tasksUpdated: 3, results: [{ id: 'r1' }] } },
	{ ok: true, body: { '@self': { id: 'x3' }, blocker: null, tasksUpdated: 0 } },
	{ ok: true, body: { title: 'plain' } },
	{ ok: true, body: [{ id: 'arr' }] },
	{ ok: true, body: null },
	{ ok: true, badJson: true },
	{ ok: true, badJson: 'second', body: { id: 'once' } },
]
const NOT_OK = [
	{ ok: false, status: 400, body: { error: 'bad', detail: 'detail', message: 'msg' } },
	{ ok: false, status: 422, body: { detail: 'only detail', message: 'm' } },
	{ ok: false, status: 409, body: { message: 'only message' } },
	{ ok: false, status: 500, body: {} },
	{ ok: false, status: 500, body: null },
	{ ok: false, status: 500, badJson: true },
]
const THROWN = [{ throws: 'boom' }, { throws: null }]
const RESPONSES = [...OK, ...NOT_OK, ...THROWN]
const LABELS_AFTER = [{ ok: true, body: { labels: [{ id: 'l1' }, { id: 'l2' }] } }, { ok: false }]

const CASES = []
RESPONSES.forEach((answer, i) => {
	LABELS_AFTER.forEach((after, j) => {
		CASES.push({ name: `createLabel ${i} ${j}`, store: 'labels', action: 'createLabel', args: [{ title: 'L', color: '#fff' }], answers: [answer, after] })
		CASES.push({ name: `updateLabel ${i} ${j}`, store: 'labels', action: 'updateLabel', args: ['l1', { title: 'L2' }], answers: [answer, after] })
	})
	CASES.push({ name: `deleteLabel ${i}`, store: 'labels', action: 'deleteLabel', args: ['l1'], answers: [answer] })
	CASES.push({ name: `deleteLabel other ${i}`, store: 'labels', action: 'deleteLabel', args: ['l9'], answers: [answer] })
	CASES.push({ name: `timeEntry update ${i}`, store: 'timeEntries', action: 'update', args: ['e1', { duration: 30 }], answers: [answer] })
	CASES.push({ name: `timeEntry update other ${i}`, store: 'timeEntries', action: 'update', args: ['e9', { date: '2026-01-01' }], answers: [answer] })
	CASES.push({ name: `createEdge ${i}`, store: 'dependencies', action: 'createEdge', args: ['t1', 't2'], answers: [answer] })
	CASES.push({ name: `createEdge typed ${i}`, store: 'dependencies', action: 'createEdge', args: ['t1', 't2', 'duplicates'], answers: [answer] })
	CASES.push({ name: `createEdge undefined type ${i}`, store: 'dependencies', action: 'createEdge', args: ['t1', 't2', undefined], answers: [answer] })
	CASES.push({ name: `fetchObjects ${i}`, store: 'object', action: 'fetchObjects', args: ['task', { project: 'p1', _limit: 5 }], answers: [answer] })
	CASES.push({ name: `fetchObjects no params ${i}`, store: 'object', action: 'fetchObjects', args: ['task'], answers: [answer] })
})
CASES.push({ name: 'fetchObjects unregistered', store: 'object', action: 'fetchObjects', args: ['nope'], answers: [] })
CASES.push({ name: 'fetchObjects null params', store: 'object', action: 'fetchObjects', args: ['task', null], answers: [] })

const STORES = {
	labels: () => {
		const store = useLabelsStore()
		store.labels = [{ id: 'l1', title: 'One' }, { id: 'l2', title: 'Two' }]
		return store
	},
	timeEntries: () => {
		const store = useTimeEntriesStore()
		store.entries = [{ id: 'e1', duration: 10, project: 'p1' }, { id: 'e2', duration: 20 }]
		return store
	},
	dependencies: () => {
		const store = useDependenciesStore()
		store.edges = [{ id: 'd0', blocker: 'a', blocked: 'b', type: 'blocks' }]
		return store
	},
	object: () => {
		const store = useObjectStore()
		store.configure({ baseUrl: '/apps/openregister/api/objects', schemaBaseUrl: '/schemas' })
		store.registerObjectType('task', 'task-schema', 'planninq-register')
		return store
	},
}

/**
 * Run one case and describe everything it did.
 *
 * @param {object} c - the case
 * @return {object} the recorded outcome
 */
async function run(c) {
	setActivePinia(createPinia())
	world.answers = [...c.answers]
	world.log = []
	vi.stubGlobal('fetch', fakeFetch)
	vi.stubGlobal('OC', { requestToken: 'token' })
	vi.stubGlobal('window', { location: { origin: 'https://cloud.example' } })
	const store = STORES[c.store]()
	const spies = ['error', 'warn'].map((level) => vi.spyOn(console, level).mockImplementation((...args) => world.log.push([`console.${level}`, ...args.map((a) => (a instanceof Error ? `${a.name}: ${a.message}` : a))])))
	let result
	try {
		result = { returned: await store[c.action](...c.args) }
	} catch (err) {
		result = { thrown: err?.message ?? String(err) }
	} finally {
		spies.forEach((spy) => spy.mockRestore())
		vi.unstubAllGlobals()
	}
	return JSON.parse(JSON.stringify({ result: result ?? null, state: { ...store.$state }, log: world.log }))
}

describe('store write actions (golden)', () => {
	const recorded = RECORD ? {} : JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))

	it('has a unique name per case', () => {
		expect(new Set(CASES.map((c) => c.name)).size).toBe(CASES.length)
	})

	for (const c of CASES) {
		it(c.name, async () => {
			const outcome = await run(c)
			if (RECORD) {
				recorded[c.name] = outcome
			} else {
				expect(outcome).toEqual(recorded[c.name])
			}
		})
	}

	afterAll(() => {
		if (RECORD) {
			fs.writeFileSync(FIXTURE, JSON.stringify(recorded, null, '\t') + '\n')
		}
	})
})
