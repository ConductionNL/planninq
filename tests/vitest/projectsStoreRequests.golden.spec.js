/**
 * Golden test for the request actions of the projects store: every action
 * runs against a scripted server and object store, and the fixture records
 * its result, the requests it sent, the types it registered, the messages it
 * showed and the store state afterwards.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/projects/spec.md
 */
import fs from 'node:fs'
import path from 'node:path'
import { createPinia, setActivePinia } from 'pinia'
import { afterAll, beforeEach, describe, expect, it, vi } from 'vitest'

const FIXTURE = path.join(__dirname, 'fixtures', 'projectsStoreRequests.golden.json')
const RECORD = process.env.PLANNINQ_GOLDEN_RECORD === '1'

const world = { uid: 'anna', answers: [], store: {}, log: [], logRegister: false }

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ h: 1 }) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => (world.uid ? { uid: world.uid } : null) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: (text) => world.log.push(['showError', text]) }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: () => ['devs'] }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => world.objectStore }))

const { useProjectsStore } = await import('../../src/store/projects.js')

/**
 * The thrown value a scripted step describes.
 *
 * @param {object} step - `{throws: message|null, status, response}`
 * @return {*} the error
 */
function failure(step) {
	if (step.plain) {
		return { status: step.status, response: step.response }
	}
	const err = new Error(step.throws ?? '')
	if (step.throws === null) {
		err.message = ''
	}
	Object.assign(err, step.status ? { status: step.status } : {}, step.response ? { response: step.response } : {})
	return err
}

/**
 * Whether a scripted answer is a thrown error.
 *
 * @param {*} value - scripted answer
 * @return {boolean} true for a failure step
 */
function isFailure(value) {
	return Boolean(value) && (value.throws !== undefined || value.plain === true)
}

/**
 * A fetch that answers from the scripted queue and records each request.
 *
 * @param {string} url - request url
 * @param {object} init - request options
 * @return {Promise<object>} the scripted response
 */
async function fakeFetch(url, init = {}) {
	world.log.push(['fetch', url, init.method ?? 'GET', init.body ?? null])
	const step = world.answers.shift() ?? { ok: true, body: {} }
	if (isFailure(step)) {
		throw failure(step)
	}
	return {
		ok: step.ok,
		status: step.status ?? (step.ok ? 200 : 500),
		json: async () => {
			if (step.badJson) {
				throw new SyntaxError('bad json')
			}
			return step.body
		},
	}
}

/**
 * An object store whose answers come from the case.
 *
 * @param {object} spec - `{fetchObject, getError, saveObject, collection, registry}`
 * @return {object} the object store double
 */
function objectStore(spec) {
	const registry = { ...(spec.registry ?? {}) }
	const answer = async (key, value) => {
		if (isFailure(value)) {
			throw failure(value)
		}
		world.log.push([key])
		return value ?? null
	}
	return {
		objectTypeRegistry: registry,
		registerObjectType: (...args) => {
			if (world.logRegister) {
				world.log.push(['register', ...args])
			}
			registry[args[0]] = true
		},
		fetchObject: (schema, id) => answer(`fetchObject ${schema} ${id}`, spec.fetchObject),
		saveObject: (schema, data) => answer(`saveObject ${schema} ${JSON.stringify(data)}`, spec.saveObject),
		getError: (schema) => {
			world.log.push(['getError', schema])
			return spec.getError ?? null
		},
		fetchCollection: async (schema, filters) => {
			world.log.push(['fetchCollection', schema, filters])
			if (isFailure(spec.collection)) {
				throw failure(spec.collection)
			}
			return filters._page === 1 ? (spec.collection ?? []) : []
		},
	}
}

const PROJECTS = [{ id: 'p1', title: 'One', owner: 'anna', members: ['anna'] }, { id: 'p2', title: 'Two', owner: 'bram', members: ['bram'] }]
const ACTIVE = [null, { id: 'p1', title: 'One', extra: 1 }, { id: 'p9', title: 'Other' }]

const CASES = []

/**
 * Add one case for every active project in ACTIVE.
 *
 * @param {string} name - case name
 * @param {object} c - `{action, args, answers, store, uid}`
 */
function add(name, c) {
	ACTIVE.forEach((active, i) => CASES.push({ name: `${name} / active ${i}`, active, ...c }))
}

const THROWN = [
	{ throws: 'boom' },
	{ throws: null },
	{ plain: true, status: 502, response: { status: 503, data: { message: 'down' } } },
	{ plain: true, status: 404 },
	{ throws: 'with response', response: { status: 500, data: {} } },
	{ throws: 'with status', status: 418 },
]
const NOT_OK = [
	{ ok: false, status: 400, body: { error: 'bad', message: 'msg', code: 'code-1' } },
	{ ok: false, status: 403, body: { errors: { code: 'lifecycle-guard-denied' } } },
	{ ok: false, status: 409, body: { message: 'changed' } },
	{ ok: false, status: 422, body: { code: 'lifecycle-invalid-transition' } },
	{ ok: false, status: 500, body: {} },
	{ ok: false, status: 500, body: null },
	{ ok: false, status: 500, badJson: true },
]
const OK = [
	{ ok: true, body: { id: 'p1', title: 'New', status: 'archived' } },
	{ ok: true, body: { '@self': { id: 'p1' }, title: 'Self' } },
	{ ok: true, body: { title: 'No id' } },
	{ ok: true, body: { id: 'p2', title: 'Other' } },
	{ ok: true, badJson: true },
	{ ok: true, body: null },
]
const RESPONSES = [...OK, ...NOT_OK, ...THROWN]

const REGISTRIES = [{}, { project: true, task: true, label: true }, { projectLogEntry: true, risk: true, boardView: true, boardFilter: true, projectRelease: true, financeLine: true, plannedTimeEntry: true, column: true }]
REGISTRIES.forEach((registry, i) => add(`_objectStore registry ${i}`, { action: '_objectStore', args: [], store: { registry }, twice: true }))

const COLLECTIONS = [[], [...PROJECTS, { id: 'p3', members: [], ownerGroup: 'devs' }], { throws: 'boom' }, { throws: null }, { plain: true, status: 500, response: { status: 503, data: { message: 'down' } } }, { plain: true, status: 404 }, { throws: 'resp', response: { status: 500, data: {} } }]
COLLECTIONS.forEach((collection, i) => {
	[null, 'anna', 'bram'].forEach((uid) => add(`fetchProjects ${i} ${uid}`, { action: 'fetchProjects', args: [{ status: 'active' }], store: { collection }, uid }))
})

const FOUND = [{ id: 'p1', title: 'Found' }, null]
const STORE_ERRORS = [null, { status: 403 }, { statusCode: 403 }, { status: 404 }, { statusCode: 500 }, {}, { message: 'saved wrong' }]
for (const action of ['fetchProject', 'fetchTask']) {
	FOUND.forEach((found, i) => STORE_ERRORS.forEach((storeError, j) => add(`${action} ${i} ${j}`, { action, args: ['p1'], store: { fetchObject: found, getError: storeError } })))
	THROWN.forEach((thrown, i) => add(`${action} thrown ${i}`, { action, args: ['p1'], store: { fetchObject: thrown } }))
}

const SAVED = [{ id: 'p1', title: 'Saved' }, null, ...THROWN]
SAVED.forEach((saved, i) => STORE_ERRORS.forEach((storeError, j) => add(`updateProject ${i} ${j}`, { action: 'updateProject', args: ['p1', { title: 'Saved' }], store: { saveObject: saved, getError: storeError } })))

RESPONSES.forEach((answer, i) => {
	add(`createProject ${i}`, { action: 'createProject', args: [{ title: 'New' }], answers: [answer] })
	add(`createProject status ${i}`, { action: 'createProject', args: [{ title: 'New', status: 'draft' }], answers: [answer] })
	add(`patchProject ${i}`, { action: 'patchProject', args: ['p1', { members: ['x'] }], answers: [answer] })
	add(`patchProject p9 ${i}`, { action: 'patchProject', args: ['p9', { title: 'T' }], answers: [answer] })
	add(`runProjectTransition ${i}`, { action: 'runProjectTransition', args: ['p1', 'archive'], answers: [answer] })
	add(`runProjectTransition data ${i}`, { action: 'runProjectTransition', args: ['p9', 'approve', { note: 'ok' }], answers: [answer] })
	add(`deleteProject ${i}`, { action: 'deleteProject', args: ['p1'], answers: [answer] })
	add(`deleteProject p2 ${i}`, { action: 'deleteProject', args: ['p2'], answers: [answer] })
	add(`savePhase new ${i}`, { action: 'savePhase', args: [{ title: 'Phase', project: 'p1' }], answers: [answer] })
	add(`savePhase ${i}`, { action: 'savePhase', args: [{ id: 'ph1', status: 'done' }], answers: [answer] })
	add(`updateTaskStatus ${i}`, { action: 'updateTaskStatus', args: ['t1', 'done'], answers: [answer] })
	add(`updateTask ${i}`, { action: 'updateTask', args: ['t1', { estimatedDuration: 3 }], answers: [answer] })
})

/**
 * Run one case and describe everything it did.
 *
 * @param {object} c - the case
 * @return {object} the recorded outcome
 */
async function run(c) {
	setActivePinia(createPinia())
	world.uid = c.uid === undefined ? 'anna' : c.uid
	world.answers = [...(c.answers ?? [])]
	world.log = []
	world.logRegister = c.action === '_objectStore'
	world.objectStore = objectStore(c.store ?? {})
	vi.stubGlobal('fetch', fakeFetch)
	const store = useProjectsStore()
	store.projects = PROJECTS.map((p) => ({ ...p }))
	store.activeProject = c.active ? { ...c.active } : null
	store.activeTask = c.active ? { id: 't0' } : null
	store.error = 'stale'
	const errors = vi.spyOn(console, 'error').mockImplementation((...args) => world.log.push(['console.error', ...args]))
	let result
	try {
		result = { returned: await store[c.action](...c.args) }
		if (c.twice) {
			result.second = await store[c.action](...c.args)
		}
		if (result.returned === world.objectStore || result.second === world.objectStore) {
			result = { returned: result.returned === world.objectStore ? 'objectStore' : result.returned, second: 'objectStore' }
		}
	} catch (err) {
		result = { thrown: err?.message ?? String(err) }
	} finally {
		errors.mockRestore()
	}
	const same = (value, before) => (JSON.stringify(value) === JSON.stringify(before) ? 'unchanged' : value)
	const state = { projects: same(store.projects, PROJECTS), activeProject: same(store.activeProject, c.active ?? null), activeTask: store.activeTask, error: store.error, loading: store.loading }
	return JSON.parse(JSON.stringify({ result, state, log: world.log }))
}

describe('projects store request actions (golden)', () => {
	const recorded = RECORD ? {} : JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))

	beforeEach(() => vi.restoreAllMocks())

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
