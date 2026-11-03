/**
 * Golden test for save() of the edit dialogs that write through the projects
 * store: what each one writes, its saving flag and error while the write
 * runs, and what it shows or emits when the write succeeds or fails, for a
 * new and an existing record. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the
 * fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import fs from 'node:fs'
import path from 'node:path'
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'dialogSave.golden.json')
const events = []
const store = { answer: null, vm: null }

vi.mock('../../src/store/projects.js', () => ({
	useProjectsStore: () => new Proxy({}, {
		get: (_, name) => async (...args) => {
			events.push({ write: String(name), args, saving: store.vm.saving, submitError: store.vm.submitError })
			await new Promise((resolve) => setTimeout(resolve, 0))
			return store.answer
		},
	}),
}))

/**
 * Per dialog: props for a new and an existing record, and data that makes
 * the form valid.
 */
const DIALOGS = {
	BoardFilterSaveDialog: {
		add: { filter: { labels: ['l1'], assignee: 'ann' }, projectId: 'p1' },
		edit: { filter: { labels: [] }, projectId: 'p1', saved: { id: 'sf1', name: 'Mine', shared: true } },
		fill: { name: 'My filter', shared: true },
	},
	ColumnEditDialog: {
		add: { projectId: 'p1', nextOrder: 4 },
		edit: { projectId: 'p1', column: { id: 'c1', title: 'Doing', wipLimit: 3, color: '#112233', done: false } },
		fill: { title: 'Review', wipLimit: '2', color: '#4376FC' },
	},
	FinanceLineDialog: {
		add: { projectId: 'p1' },
		edit: { projectId: 'p1', line: { id: 'f1', kind: 'actual', category: 'travel', amount: 12.5, date: '2026-01-02', description: 'Train' } },
		fill: { amount: '40', category: 'hardware', date: '2026-03-04', description: 'Laptop' },
	},
	LogEntryEditDialog: {
		add: { projectId: 'p1' },
		edit: { projectId: 'p1', entry: { id: 'e1', type: 'decision', title: 'Go', date: '2026-02-01', actions: [{ title: 'a' }] } },
		fill: { title: 'Kick-off', date: '2026-02-03', body: 'Notes' },
	},
	ProjectsViewEditDialog: {
		add: { projects: [{ id: 'p1', title: 'One' }], uid: 'ann' },
		edit: { projects: [{ id: 'p1', title: 'One' }], uid: 'ann', view: { id: 'v1', title: 'Mine', projects: ['p1'], members: ['bob'] } },
		fill: { title: 'Portfolio view', pickedProjects: [{ id: 'p1', label: 'One' }] },
	},
	ReleaseEditDialog: {
		add: { projectId: 'p1' },
		edit: { projectId: 'p1', release: { id: 'r1', title: 'v1', description: 'First', startDate: '2026-01-01', releaseDate: '2026-02-01' } },
		fill: { title: 'v2', releaseDate: '2026-05-01' },
	},
	RiskEditDialog: {
		add: { projectId: 'p1' },
		edit: { projectId: 'p1', risk: { id: 'k1', title: 'Late', likelihood: 2, impact: 3, description: 'd', category: 'plan' } },
		fill: { title: 'Budget overrun', countermeasures: 'Weekly check' },
	},
}

/**
 * A component instance stand-in: data, computed and methods merged the way
 * Vue merges mixins (component last), the props with their defaults, and a
 * recorder for $emit.
 *
 * @param {object} options - the component options
 * @param {object} props - the props
 * @return {object} the instance
 */
function instance(options, props) {
	const parts = [...(options.mixins || []), options]
	const vm = { t: (app, text) => text, $emit: (name, ...payload) => events.push({ emit: name, payload }) }
	for (const [name, prop] of Object.entries(Object.assign({}, ...parts.map((part) => part.props || {})))) {
		if (name in props) {
			vm[name] = props[name]
		} else if ('default' in prop) {
			vm[name] = typeof prop.default === 'function' && prop.type !== Function ? prop.default() : prop.default
		}
	}
	for (const part of parts) {
		for (const [name, method] of Object.entries(part.methods || {})) {
			vm[name] = method.bind(vm)
		}
		for (const [name, getter] of Object.entries(part.computed || {})) {
			Object.defineProperty(vm, name, { get: () => (getter.get || getter).call(vm), configurable: true })
		}
	}
	for (const part of parts) {
		Object.assign(vm, part.data ? part.data.call(vm, vm) : {})
	}
	return vm
}

/**
 * Run save() once.
 *
 * @param {object} options - the component options
 * @param {{props: object, data: object, answer: unknown}} run - props, data overrides and the store's answer
 * @return {Promise<object>} what was written and emitted, and the state after
 */
async function saveOnce(options, run) {
	events.length = 0
	const vm = instance(options, run.props)
	Object.assign(vm, run.data, { submitError: 'previous error' })
	store.vm = vm
	store.answer = run.answer
	await vm.save()
	return { events: JSON.parse(JSON.stringify(events)), saving: vm.saving, submitError: vm.submitError }
}

describe('dialog save golden', () => {
	// The log entry dialog defaults its date to today: pin the clock to noon (UTC) of the recorded day.
	beforeAll(() => {
		vi.useFakeTimers({ toFake: ['Date'] })
		vi.setSystemTime(new Date('2026-11-02T12:00:00Z'))
	})
	afterAll(() => {
		vi.useRealTimers()
	})

	it('matches the recording', async () => {
		const recorded = {}
		for (const [name, { add, edit, fill }] of Object.entries(DIALOGS)) {
			const { options } = await loadSfcWithImports(new URL(`../../src/dialogs/${name}.vue`, import.meta.url))
			recorded[name] = {}
			for (const [kind, props] of Object.entries({ add, edit })) {
				for (const [outcome, answer] of Object.entries({ saved: { id: 'new-1' }, refused: null })) {
					recorded[name][`${kind} ${outcome}`] = await saveOnce(options, { props, data: fill, answer })
				}
				recorded[name][`${kind} unfilled`] = await saveOnce(options, { props, data: {}, answer: { id: 'new-2' } })
			}
		}
		const actual = JSON.stringify(recorded, null, 1) + '\n'
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, actual)
		}
		expect(actual).toBe(fs.readFileSync(FIXTURE, 'utf8'))
	}, 60000)
})
