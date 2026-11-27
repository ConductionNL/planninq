/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for the workflows section of the admin page: the stored
 * workflow, the workflow API, the edit dialog and deleting a workflow.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { columnKey, parseEstimateValues, workflowBody } from '../../src/utils/workflows.js'
import { loadSfcWithImports } from './support/sfc.js'

const calls = []
let reply = {}
/**
 * A fake axios verb that records the call and answers the reply.
 *
 * @param {string} verb The verb.
 * @return {Function}
 */
function verb(verb) {
	return async (url, body) => {
		calls.push([verb, url, body])
		if (reply instanceof Error) {
			throw reply
		}
		return reply
	}
}
vi.mock('@nextcloud/axios', () => ({ default: { get: verb('get'), post: verb('post'), put: verb('put'), delete: verb('delete') } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const api = await import('../../src/api/workflows.js')
const URL_BASE = '/apps/openregister/api/objects/planninq/workflow'

beforeEach(() => {
	calls.length = 0
	reply = { data: { id: 'w1' } }
})

describe('columnKey', () => {
	it('makes a key from the title, unique among the taken keys', () => {
		expect(columnKey('Beoordeling', [])).toBe('beoordeling')
		expect(columnKey('Bezwaar & beroep', [])).toBe('bezwaar-beroep')
		expect(columnKey('Intake', ['intake', 'intake-2'])).toBe('intake-3')
		expect(columnKey('!!', [])).toBe('column')
	})
})

describe('parseEstimateValues', () => {
	it('reads positive numbers once each, sizes as words, and nothing without a scale', () => {
		expect(parseEstimateValues('1, 2, 3, 5, 8, 13, 8, -1, x', 'storyPoints')).toEqual([1, 2, 3, 5, 8, 13])
		expect(parseEstimateValues(' S, M ,L,,M', 'tshirt')).toEqual(['S', 'M', 'L'])
		expect(parseEstimateValues('1, 2', 'none')).toEqual([])
	})
})

describe('workflowBody', () => {
	it('keeps the stored keys, makes keys for new columns, drops unnamed ones and keeps the order', () => {
		expect(workflowBody({
			title: ' Vergunningverlening ',
			description: ' ',
			columns: [{ key: 'intake', title: 'Intake' }, { title: ' ' }, { title: 'Intake' }, { key: 'besluit', title: 'Besluit', type: 'done' }],
			estimateScale: 'storyPoints',
			estimateValues: '1, 2, 3',
		})).toEqual({
			title: 'Vergunningverlening',
			columns: [{ key: 'intake', title: 'Intake', type: 'active' }, { key: 'intake-2', title: 'Intake', type: 'active' }, { key: 'besluit', title: 'Besluit', type: 'done' }],
			estimateScale: 'storyPoints',
			estimateValues: [1, 2, 3],
		})
	})

	it('keeps a description and reads an unknown scale as none', () => {
		expect(workflowBody({ title: 'W', description: ' Vergunningen ', columns: [], estimateScale: 'weird', estimateValues: '1' }))
			.toEqual({ title: 'W', columns: [], estimateScale: 'none', estimateValues: [], description: 'Vergunningen' })
	})
})

describe('workflow API', () => {
	it('lists the workflows by title, from results or a plain list', async () => {
		reply = { data: { results: [{ title: 'Z' }, { title: 'A' }] } }
		expect(await api.listWorkflows()).toEqual([{ title: 'A' }, { title: 'Z' }])
		reply = { data: [{ title: 'B' }] }
		expect(await api.listWorkflows()).toEqual([{ title: 'B' }])
		expect(calls[0]).toEqual(['get', URL_BASE, { params: { _limit: 200 } }])
	})

	it('creates a workflow without an id and replaces one with an id', async () => {
		await api.saveWorkflow({ title: 'New' })
		await api.saveWorkflow({ id: 'w1', title: 'Old' })
		expect(calls).toEqual([['post', URL_BASE, { title: 'New' }], ['put', `${URL_BASE}/w1`, { title: 'Old' }]])
	})

	it('throws the server\'s reason for a refused delete', async () => {
		reply = Object.assign(new Error('409'), { response: { data: { message: '2 project(s) follow this workflow. Move them to another workflow or off it before deleting it.' } } })
		await expect(api.deleteWorkflow('w1')).rejects.toThrow('2 project(s) follow this workflow.')
		reply = new Error('network')
		await expect(api.deleteWorkflow('w1')).rejects.toThrow('workflow-delete-error')
		await expect(api.saveWorkflow({})).rejects.toThrow('workflow-save-error')
	})
})

/**
 * A fake component with an SFC's methods.
 *
 * @param {object} options The SFC options.
 * @param {object} vm The state.
 * @return {object}
 */
function bind(options, vm) {
	for (const [name, method] of Object.entries(options.methods)) {
		vm[name] = method.bind(vm)
	}
	vm.t = (app, text) => text
	vm.emitted = []
	vm.$emit = (name, value) => vm.emitted.push([name, value])
	return vm
}

const dialog = await loadSfcWithImports(new URL('../../src/dialogs/WorkflowEditDialog.vue', import.meta.url), { vue: async () => ({}) })

describe('WorkflowEditDialog', () => {
	it('moves a column up and down', () => {
		const vm = bind(dialog.options, { form: { columns: [{ title: 'A' }, { title: 'B' }, { title: 'C' }] } })
		vm.moveColumn(2, -1)
		expect(vm.form.columns.map((c) => c.title)).toEqual(['A', 'C', 'B'])
		vm.moveColumn(0, 1)
		expect(vm.form.columns.map((c) => c.title)).toEqual(['C', 'A', 'B'])
	})

	it('saves the edited workflow under its id and emits what was stored', async () => {
		const vm = bind(dialog.options, { workflow: { id: 'w1' }, form: { title: 'W', description: '', columns: [{ key: 'a', title: 'A' }], estimateScale: 'none', estimateValues: '' }, saving: false, submitError: '' })
		await vm.save()
		expect(calls).toEqual([['put', `${URL_BASE}/w1`, { title: 'W', columns: [{ key: 'a', title: 'A', type: 'active' }], estimateScale: 'none', estimateValues: [] }]])
		expect([vm.emitted, vm.saving, vm.submitError]).toEqual([[['saved', { id: 'w1' }]], false, ''])
	})

	it('shows a retry message when the save failed without a reason', async () => {
		reply = new Error('network')
		const vm = bind(dialog.options, { workflow: null, form: { title: 'W', columns: [] }, saving: false, submitError: '' })
		await vm.save()
		expect([vm.emitted, vm.submitError]).toEqual([[], 'The workflow could not be saved. Please try again.'])
	})
})

const settings = await loadSfcWithImports(new URL('../../src/components/WorkflowSettings.vue', import.meta.url), { vue: async () => ({}) })

describe('WorkflowSettings remove', () => {
	it('drops a deleted workflow from the list', async () => {
		const vm = bind(settings.options, { workflows: [{ id: 'w1' }, { id: 'w2' }], error: '' })
		await vm.remove({ id: 'w1' })
		expect([calls, vm.workflows, vm.error]).toEqual([[['delete', `${URL_BASE}/w1`, undefined]], [{ id: 'w2' }], ''])
	})

	it('keeps a workflow that projects follow and says why', async () => {
		reply = Object.assign(new Error('409'), { response: { data: { message: '1 project(s) follow this workflow.' } } })
		const vm = bind(settings.options, { workflows: [{ id: 'w1' }], error: '' })
		await vm.remove({ id: 'w1' })
		expect([vm.workflows, vm.error]).toEqual([[{ id: 'w1' }], '1 project(s) follow this workflow.'])
	})
})
