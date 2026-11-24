/**
 * The Retention admin section (retention-on-planninq-records task 2.1): the
 * picker options from OpenRegister's selection list, reading and saving the
 * pick per schema, and the component's load, pick and save steps with the
 * HTTP client stood in.
 *
 * @spec openspec/changes/archive/2026-11-24-retention-on-planninq-records/specs/record-retention/spec.md#requirement-planninq-records-carry-a-retention-category
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { describe, expect, it } from 'vitest'
import { categoryOptions, loadRetention, normalisePicks, saveRetention } from '../../src/utils/retention.js'
import { loadSfcWithImports } from './support/sfc.js'

const COMPONENT = new URL('../../src/components/RetentionSettings.vue', import.meta.url)
const URL_ = '/apps/planninq/api/settings/retention'
const CATEGORIES = [
	{ category: 'B1', description: 'Projectdossier', period: 'P1Y', action: 'vernietigen' },
	{ category: 'A1', description: '', period: '' },
	{ category: '', description: 'nameless' },
]

/**
 * An HTTP client stand-in that records calls and answers from a queue.
 *
 * @param {Array<object|Error>} answers The answers, in order
 * @return {{calls: Array<Array>, get: Function, put: Function}}
 */
function client(answers) {
	const calls = []
	const answer = async (call) => {
		calls.push(call)
		const next = answers.shift()
		if (next instanceof Error) {
			throw next
		}
		return { data: next }
	}
	return { calls, get: (url) => answer(['get', url]), put: (url, body) => answer(['put', url, body]) }
}

/**
 * The component with axios, the router and the translator stood in, as an instance.
 *
 * @param {object} http The HTTP client stand-in
 * @return {Promise<object>} The instance
 */
async function mounted(http) {
	const { options, template } = await loadSfcWithImports(COMPONENT, {
		modules: {
			'@nextcloud/axios': { default: http },
			'@nextcloud/router': { generateUrl: (path) => path },
			'@nextcloud/l10n': { translate: (app, text) => text },
			'@nextcloud/vue': { NcButton: {}, NcSelect: {} },
			'@conduction/nextcloud-vue': { CnSettingsSection: {} },
		},
	})
	const vm = { ...options.methods }
	for (const name of Object.keys(vm)) {
		vm[name] = vm[name].bind(vm)
	}
	Object.assign(vm, options.data.call(vm))
	for (const [name, getter] of Object.entries(options.computed)) {
		Object.defineProperty(vm, name, { get: getter.bind(vm) })
	}
	await options.created.call(vm)
	vm.template = template
	return vm
}

describe('retention helpers', () => {
	it('labels each named category with its description and period', () => {
		expect(categoryOptions(CATEGORIES)).toEqual([
			{ id: 'B1', label: 'B1 – Projectdossier (P1Y)' },
			{ id: 'A1', label: 'A1' },
		])
		expect(categoryOptions(null)).toEqual([])
	})

	it('reads a pick with every schema present', () => {
		expect(normalisePicks({ project: ' B1 ', task: 5 })).toEqual({ project: 'B1', task: '', plannedTimeEntry: '' })
		expect(normalisePicks(undefined)).toEqual({ project: '', task: '', plannedTimeEntry: '' })
	})

	it('loads and saves through the endpoint', async () => {
		const http = client([
			{ categories: CATEGORIES, picks: { project: 'B1' } },
			{ picks: { project: 'B1', task: 'B1', plannedTimeEntry: '' }, refused: [], applied: true },
		])
		expect(await loadRetention(http, URL_)).toEqual({ categories: CATEGORIES, picks: { project: 'B1', task: '', plannedTimeEntry: '' } })
		expect(await saveRetention(http, URL_, { project: 'B1', task: 'B1' })).toEqual({ project: 'B1', task: 'B1', plannedTimeEntry: '' })
		expect(http.calls).toEqual([
			['get', URL_],
			['put', URL_, { picks: { project: 'B1', task: 'B1', plannedTimeEntry: '' } }],
		])
	})
})

describe('RetentionSettings', () => {
	it('loads the categories and shows the stored pick per schema', async () => {
		const vm = await mounted(client([{ categories: CATEGORIES, picks: { project: 'B1' } }]))

		expect(vm.loaded).toBe(true)
		expect(vm.rows.map((row) => row.schema)).toEqual(['project', 'task', 'plannedTimeEntry'])
		expect(vm.selected('project')).toEqual({ id: 'B1', label: 'B1 – Projectdossier (P1Y)' })
		expect(vm.selected('task')).toBeNull()
		expect(vm.template).toContain(':inputLabel="row.label"')
	})

	it('saves the picked categories and says so', async () => {
		const http = client([
			{ categories: CATEGORIES, picks: {} },
			{ picks: { project: 'B1', task: 'A1', plannedTimeEntry: '' } },
		])
		const vm = await mounted(http)
		vm.pick('project', { id: 'B1' })
		vm.pick('task', { id: 'A1' })
		vm.pick('plannedTimeEntry', null)
		await vm.save()

		expect(http.calls[1]).toEqual(['put', URL_, { picks: { project: 'B1', task: 'A1', plannedTimeEntry: '' } }])
		expect(vm.picks).toEqual({ project: 'B1', task: 'A1', plannedTimeEntry: '' })
		expect(vm.message).toBe('Retention saved.')
		expect(vm.failed).toBe(false)
		expect(vm.saving).toBe(false)
	})

	it('reports a refused save and keeps the form', async () => {
		const vm = await mounted(client([{ categories: CATEGORIES, picks: {} }, new Error('400')]))
		vm.pick('project', { id: 'B1' })
		await vm.save()

		expect(vm.failed).toBe(true)
		expect(vm.message).toBe('Could not save the retention. Please try again.')
		expect(vm.picks.project).toBe('B1')
	})

	it('reports a failed load and offers nothing to pick', async () => {
		const vm = await mounted(client([new Error('403')]))

		expect(vm.loaded).toBe(true)
		expect(vm.options).toEqual([])
		expect(vm.message).toBe('Could not load the retention settings.')
	})
})
