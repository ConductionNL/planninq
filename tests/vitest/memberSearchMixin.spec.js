/**
 * Tests for the member search input mixin and the two load-trigger mixins,
 * run as plain component options against the Nextcloud autocomplete endpoint
 * (fetch is the only double).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({ generateOcsUrl: (p) => '/ocs/v2.php' + p }))

const { default: memberSearchInput } = await import('../../src/mixins/memberSearchInput.js')

/**
 * A component instance of the search mixin, with the hooks its users supply.
 *
 * @return {object}
 */
function searchInput() {
	const vm = {
		...memberSearchInput.data(),
		errors: [],
		searchFilter() {
			return { members: ['olga'] }
		},
		onSearchError(err) {
			this.errors.push(err.message)
		},
	}
	for (const [name, method] of Object.entries(memberSearchInput.methods)) {
		vm[name] = method.bind(vm)
	}
	return vm
}

function ocsAnswer(data) {
	return new Response(JSON.stringify({ ocs: { data } }), { status: 200 })
}

describe('memberSearchInput', () => {
	let calls

	beforeEach(() => {
		vi.useFakeTimers()
		calls = []
		vi.stubGlobal('fetch', vi.fn(async (url, init) => {
			calls.push([url, init])
			return ocsAnswer([
				{ id: 'olga', label: 'Olga', source: 'users', shareWithDisplayNameUnique: 'olga' },
				{ id: 'bob', label: 'Bob', source: 'users', shareWithDisplayNameUnique: 'bob@example.com' },
			])
		}))
	})

	afterEach(() => {
		vi.useRealTimers()
		vi.unstubAllGlobals()
	})

	it('waits 300 ms after the last keystroke, then searches once and drops existing members', async () => {
		const vm = searchInput()

		vm.onInput('bo')
		vm.onInput('bob')
		await vi.advanceTimersByTimeAsync(299)
		expect(calls).toHaveLength(0)

		await vi.advanceTimersByTimeAsync(1)
		expect(calls).toHaveLength(1)
		expect(calls[0][0]).toContain('/ocs/v2.php/core/autocomplete/get?search=bob')
		expect(calls[0][1].signal).toBeInstanceOf(AbortSignal)
		expect(vm.results.map((option) => option.id)).toEqual(['bob'])
		expect(vm.results[0].subname).toBe('bob@example.com')
		expect(vm.loading).toBe(false)
		expect(vm.searched).toBe(true)
	})

	it('clears the results and does not search below the minimum length', async () => {
		const vm = searchInput()
		vm.results = [{ id: 'old' }]

		vm.onInput(' b ')
		vm.onInput(null)
		await vi.advanceTimersByTimeAsync(1000)

		expect(calls).toHaveLength(0)
		expect(vm.results).toEqual([])
		expect(vm.query).toBe('')
		expect(vm.searched).toBe(false)
	})

	it('aborts the request in flight when the user types on', async () => {
		const vm = searchInput()
		const controller = new AbortController()
		vm.abortController = controller

		vm.onInput('bobby')

		expect(controller.signal.aborted).toBe(true)
	})

	it('reports a failed search and empties the results', async () => {
		vi.stubGlobal('fetch', vi.fn(async () => new Response('', { status: 500, statusText: 'Internal Server Error' })))
		const vm = searchInput()
		vm.results = [{ id: 'old' }]

		await vm.search('bob')

		expect(vm.errors).toEqual(['500 Internal Server Error'])
		expect(vm.results).toEqual([])
		expect(vm.loading).toBe(false)
		expect(vm.searched).toBe(true)
	})

	it('ignores an aborted search without reporting it', async () => {
		const abort = new Error('aborted')
		abort.name = 'AbortError'
		vi.stubGlobal('fetch', vi.fn(async () => {
			throw abort
		}))
		const vm = searchInput()
		vm.results = [{ id: 'kept' }]

		await vm.search('bob')

		expect(vm.errors).toEqual([])
		expect(vm.results).toEqual([{ id: 'kept' }])
		expect(vm.searched).toBe(true)
	})

	it('stops the pending search and the request when unmounted', () => {
		const vm = searchInput()
		const controller = new AbortController()
		vm.abortController = controller
		vm.onInput('bob')
		vm.abortController = controller

		memberSearchInput.beforeUnmount.call(vm)
		vi.advanceTimersByTime(1000)

		expect(controller.signal.aborted).toBe(true)
		expect(calls).toHaveLength(0)
	})
})
