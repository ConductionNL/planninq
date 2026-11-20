/**
 * Golden test for the search input of MemberSearch and OwnerGroupPicker:
 * typing, the 300 ms pause, aborting a search that is overtaken, a failed or
 * aborted search, picking a result and closing the component. Each step
 * records the component's state, the searches asked for, the store calls,
 * emits, logged errors and error toasts, plus fingerprints of the compiled
 * template and the styles. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import fs from 'node:fs'
import path from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'memberSearchInput.golden.json')
const events = []
const answers = []

/**
 * An error with a name, as fetch raises them.
 *
 * @param {string} name - the error name
 * @return {Error} the error
 */
function named(name) {
	const error = new Error(`${name} raised`)
	error.name = name
	return error
}

vi.mock('../../src/utils/memberSearch.js', async (original) => ({
	...(await original()),
	searchMembers: (term, options) => {
		const { signal, ...rest } = options
		events.push(`searchMembers(${JSON.stringify(term)}, ${JSON.stringify(rest)}, signal ${signal ? 'given' : 'none'})`)
		const answer = answers.shift() ?? { results: [] }
		return new Promise((resolve, reject) => {
			signal?.addEventListener('abort', () => {
				events.push(`aborted ${JSON.stringify(term)}`)
				reject(named('AbortError'))
			})
			setTimeout(() => (answer.error ? reject(answer.error) : resolve(answer.results)), 50)
		})
	},
}))

vi.mock('../../src/store/projects.js', () => ({
	useProjectsStore: () => new Proxy({}, {
		get: (_, name) => async (...args) => {
			events.push(`store.${String(name)}(${JSON.stringify(args)})`)
			if (args.includes('fail')) {
				throw new Error('store failed')
			}
		},
	}),
}))

const MODULES = { '@nextcloud/dialogs': { showError: (text) => events.push(`showError(${JSON.stringify(text)})`) } }

/**
 * A component instance stand-in: data, methods and unmount hooks merged the
 * way Vue merges mixins (component last), the given props, and recorders for
 * $emit and t().
 *
 * @param {object} options - the component options
 * @param {object} props - the props
 * @return {object} the instance
 */
function instance(options, props) {
	const parts = [...(options.mixins || []), options]
	const vm = { ...props, t: (app, text) => text, $emit: (name, payload) => events.push(`emit ${name} ${JSON.stringify(payload)}`) }
	for (const part of parts) {
		Object.assign(vm, part.methods || {})
	}
	for (const name of Object.keys(vm)) {
		if (typeof vm[name] === 'function' && name !== '$emit' && name !== 't') {
			vm[name] = vm[name].bind(vm)
		}
	}
	for (const part of parts) {
		Object.assign(vm, part.data ? part.data.call(vm, vm) : {})
	}
	vm.unmount = () => parts.forEach((part) => part.beforeUnmount?.call(vm))
	return vm
}

/**
 * The state worth comparing after a step.
 *
 * @param {object} vm - the instance
 * @return {object} the state
 */
function state(vm) {
	return {
		query: vm.query,
		results: vm.results,
		loading: vm.loading,
		searched: vm.searched,
		minLength: vm.minLength,
		timers: vi.getTimerCount(),
		hasController: vm.abortController !== null,
	}
}

const USER = { key: 'user-ann', type: 'user', id: 'ann', displayName: 'Ann' }
const GROUP = { key: 'group-staff', type: 'group', id: 'staff', displayName: 'Staff' }

/**
 * Steps a scenario takes: [action, argument]. 'answer' queues the next search
 * outcome, 'type' calls onInput, 'wait' advances the clock in ms, 'flush'
 * lets pending promises settle, 'call' runs a method with arguments.
 */
const SCENARIOS = {
	tooShort: [['type', 'a'], ['wait', 400], ['type', ''], ['type', null], ['type', '  a  ']],
	pauseThenSearch: [['answer', { results: [USER, GROUP] }], ['type', 'an'], ['wait', 299], ['wait', 1], ['flush']],
	typingResetsPause: [['answer', { results: [GROUP] }], ['type', 'an'], ['wait', 200], ['type', 'ann'], ['wait', 200], ['wait', 100], ['flush']],
	overtakenSearch: [['answer', { results: [USER] }], ['answer', { results: [GROUP] }], ['type', 'an'], ['wait', 300], ['type', 'ann'], ['wait', 10], ['wait', 300], ['wait', 49], ['wait', 1], ['flush']],
	settledThenTyped: [['answer', { results: [USER] }], ['type', 'an'], ['wait', 349], ['wait', 1], ['type', 'ann'], ['flush']],
	shortAfterResults: [['answer', { results: [USER] }], ['type', 'an'], ['wait', 300], ['flush'], ['type', 'a'], ['flush']],
	abortedSearch: [['answer', { error: named('AbortError') }], ['type', 'an'], ['wait', 300], ['flush']],
	failedSearch: [['answer', { results: [USER] }], ['type', 'an'], ['wait', 300], ['flush'], ['answer', { error: named('TypeError') }], ['type', 'ann'], ['wait', 300], ['flush']],
	unmountDuringPause: [['type', 'an'], ['wait', 100], ['unmount'], ['wait', 500], ['flush']],
	unmountDuringSearch: [['answer', { results: [USER] }], ['type', 'an'], ['wait', 300], ['unmount'], ['flush']],
	unmountIdle: [['unmount']],
	pickNothing: [['call', 'select', null], ['flush']],
	pickUser: [['answer', { results: [USER] }], ['type', 'an'], ['wait', 300], ['flush'], ['call', 'select', USER], ['flush']],
	pickGroup: [['call', 'select', GROUP], ['flush']],
	pickFails: [['call', 'select', { ...GROUP, id: 'fail' }], ['flush']],
}

const COMPONENTS = {
	MemberSearch: [
		{ projectId: 'p1', existingMembers: ['bob'], existingGroups: ['admins'] },
		{ projectId: 'fail' },
	],
	OwnerGroupPicker: [
		{ projectId: 'p1', ownerGroup: 'staff', groupName: 'Staff', canChange: true },
		{ projectId: 'p1', ownerGroup: null, canChange: true },
		{ projectId: 'fail', ownerGroup: null, canChange: true },
	],
}
const EXTRA = { OwnerGroupPicker: { clear: [['call', 'change', null], ['flush']], rename: [['call', 'change', 'g2', 'Group two'], ['flush']] } }

/**
 * Run one scenario on a fresh instance.
 *
 * @param {object} vm - the instance
 * @param {Array} steps - the scenario's steps
 * @return {Promise<Array>} per step: the step, what happened and the state after it
 */
async function play(vm, steps) {
	const trace = []
	for (const [action, ...args] of steps) {
		events.length = 0
		if (action === 'answer') {
			answers.push(args[0])
		} else if (action === 'type') {
			vm.onInput(args[0])
		} else if (action === 'wait') {
			await vi.advanceTimersByTimeAsync(args[0])
		} else if (action === 'flush') {
			await vi.runAllTimersAsync()
			await new Promise((resolve) => setImmediate(resolve))
		} else if (action === 'unmount') {
			vm.unmount()
		} else {
			await vm[args[0]](...args.slice(1))
		}
		trace.push({ step: [action, ...args], events: [...events], state: state(vm) })
	}
	return trace
}

describe('member search input golden', () => {
	beforeEach(() => {
		vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
		vi.spyOn(console, 'error').mockImplementation((...args) => events.push(`console.error(${args.map((arg) => (arg instanceof Error ? arg.message : arg)).join(', ')})`))
	})
	afterEach(() => {
		vi.useRealTimers()
		vi.restoreAllMocks()
		answers.length = 0
	})

	it('matches the recording', async () => {
		const recorded = {}
		for (const [name, propSets] of Object.entries(COMPONENTS)) {
			const { options, templateHash, styleHash } = await loadSfcWithImports(new URL(`../../src/components/${name}.vue`, import.meta.url), { modules: MODULES })
			const scenarios = { ...SCENARIOS, ...(EXTRA[name] || {}) }
			recorded[name] = { templateHash, styleHash, runs: [] }
			for (const props of propSets) {
				for (const [scenario, steps] of Object.entries(scenarios)) {
					answers.length = 0
					recorded[name].runs.push({ props, scenario, trace: await play(instance(options, props), steps) })
					vi.clearAllTimers()
				}
			}
		}
		const actual = JSON.stringify(recorded, null, 1) + '\n'
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, actual)
		}
		expect(actual).toBe(fs.readFileSync(FIXTURE, 'utf8'))
	}, 60000)
})
