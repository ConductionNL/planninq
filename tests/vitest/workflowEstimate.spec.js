/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for the estimate a workflow sets: what a task card shows, what
 * TaskDetail offers and stores, and the project's workflow read once.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.4
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { estimateChoices, estimatePatch, storedScale, workflowEstimate } from '../../src/utils/workflows.js'
import { loadSfcWithImports } from './support/sfc.js'

const reads = []
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: async (url) => {
			reads.push(url)
			if (url.endsWith('/gone')) {
				throw new Error('404')
			}
			return { data: { id: url.split('/').pop(), estimateScale: 'storyPoints' } }
		},
	},
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const { forgetWorkflows, getWorkflow } = await import('../../src/api/workflows.js')

const FIBONACCI = { title: 'Vergunningverlening', estimateScale: 'storyPoints', estimateValues: [1, 2, 3, 5, 8, 13] }
const HOURS = { title: 'Uren', estimateScale: 'hours', estimateValues: [0.5, 1, 4] }
const SIZES = { title: 'Maten', estimateScale: 'tshirt', estimateValues: ['S', 'M', 'L'] }

beforeEach(() => {
	reads.length = 0
	forgetWorkflows()
})

describe('storedScale', () => {
	it('names story points, hours and t-shirt sizes, and nothing for none, an unknown scale or no workflow', () => {
		expect([FIBONACCI, HOURS, SIZES, { estimateScale: 'none' }, { estimateScale: 'days' }, null].map(storedScale)).toEqual(['storyPoints', 'hours', 'tshirt', null, null, null])
	})
})

describe('workflowEstimate', () => {
	it('shows story points with the unit pt and minutes as hours', () => {
		expect(workflowEstimate({ storyPoints: 5 }, FIBONACCI)).toBe('5 pt')
		expect(workflowEstimate({ estimatedDuration: 90 }, HOURS)).toBe('1.5 h')
		expect(workflowEstimate({ estimatedDuration: 90 }, FIBONACCI)).toBe('')
		expect(workflowEstimate({}, null)).toBe('')
	})

	it('shows a t-shirt size as it is stored, and nothing without one', () => {
		expect(workflowEstimate({ estimateSize: 'M', storyPoints: 5 }, SIZES)).toBe('M')
		expect(workflowEstimate({ storyPoints: 5 }, SIZES)).toBe('')
		expect(workflowEstimate({ estimateSize: null }, SIZES)).toBe('')
	})
})

describe('estimateChoices and estimatePatch', () => {
	it('offers exactly the workflow\'s values', () => {
		expect(estimateChoices(FIBONACCI).map((choice) => choice.label)).toEqual(['1 pt', '2 pt', '3 pt', '5 pt', '8 pt', '13 pt'])
		expect(estimateChoices(HOURS)).toEqual([{ id: 0.5, label: '0.5 h' }, { id: 1, label: '1 h' }, { id: 4, label: '4 h' }])
	})

	it('offers the workflow\'s sizes as they are named', () => {
		expect(estimateChoices(SIZES)).toEqual([{ id: 'S', label: 'S' }, { id: 'M', label: 'M' }, { id: 'L', label: 'L' }])
	})

	it('stores a size in estimateSize, and clears it with null', () => {
		expect(estimatePatch(SIZES, 'L')).toEqual({ estimateSize: 'L' })
		expect(estimatePatch(SIZES, null)).toEqual({ estimateSize: null })
	})

	it('stores story points as they are and hours as minutes, and clears with null', () => {
		expect(estimatePatch(FIBONACCI, 8)).toEqual({ storyPoints: 8 })
		expect(estimatePatch(HOURS, 0.5)).toEqual({ estimatedDuration: 30 })
		expect(estimatePatch(HOURS, null)).toEqual({ estimatedDuration: null })
	})
})

describe('getWorkflow', () => {
	it('reads a workflow once for every card, and answers null when it cannot be read', async () => {
		const [first, second] = await Promise.all([getWorkflow('w1'), getWorkflow('w1')])
		expect([first, second, reads]).toEqual([{ id: 'w1', estimateScale: 'storyPoints' }, { id: 'w1', estimateScale: 'storyPoints' }, ['/apps/openregister/api/objects/planninq/workflow/w1']])
		expect(await getWorkflow('gone')).toBe(null)
	})
})

const card = await loadSfcWithImports(new URL('../../src/components/TaskCard.vue', import.meta.url), { vue: async () => ({}) })

describe('TaskCard estimate badge', () => {
	it('shows the workflow\'s estimate on a workflow, and the logged-time estimate elsewhere', () => {
		const estimateLabel = card.options.computed.estimateLabel
		expect(estimateLabel.call({ task: { storyPoints: 3, estimatedDuration: 120 }, workflow: FIBONACCI })).toBe('3 pt')
		expect(estimateLabel.call({ task: { estimatedDuration: 120 }, workflow: null })).toBe('2h')
		expect(estimateLabel.call({ task: { estimatedDuration: 120 }, workflow: { estimateScale: 'none' } })).toBe('2h')
		expect(estimateLabel.call({ task: { estimateSize: 'L', estimatedDuration: 120 }, workflow: SIZES })).toBe('L')
	})
})

const updates = []
const field = await loadSfcWithImports(new URL('../../src/components/WorkflowEstimateField.vue', import.meta.url), {
	modules: {
		'../store/projects.js': {
			useProjectsStore: () => ({
				updateTask: async (id, patch) => updates.push(['update', id, patch]),
				fetchTask: async (id) => updates.push(['fetch', id]),
			}),
		},
	},
	vue: async () => ({}),
})

/**
 * A fake estimate field.
 *
 * @param {object} task The task.
 * @param {object} workflow The workflow.
 * @return {object}
 */
function estimateField(task, workflow) {
	const vm = { task, workflow, saving: false }
	vm.choices = field.options.computed.choices.call(vm)
	vm.picked = field.options.computed.picked.call(vm)
	vm.save = field.options.methods.save.bind(vm)
	return vm
}

describe('WorkflowEstimateField', () => {
	beforeEach(() => {
		updates.length = 0
	})

	it('picks the option of the stored estimate', () => {
		expect(estimateField({ id: 't1', storyPoints: 5 }, FIBONACCI).picked).toEqual({ id: 5, label: '5 pt' })
		expect(estimateField({ id: 't1', estimatedDuration: 240 }, HOURS).picked).toEqual({ id: 4, label: '4 h' })
		expect(estimateField({ id: 't1', storyPoints: 4 }, FIBONACCI).picked).toBe(null)
		expect(estimateField({ id: 't1', estimateSize: 'M' }, SIZES).picked).toEqual({ id: 'M', label: 'M' })
		expect(estimateField({ id: 't1', estimateSize: 'XL' }, SIZES).picked).toBe(null)
		expect(estimateField({ id: 't1' }, SIZES).picked).toBe(null)
	})

	it('stores the picked value, or clears it, and reads the task again', async () => {
		const vm = estimateField({ id: 't1' }, HOURS)
		await vm.save({ id: 1 })
		await vm.save(null)
		expect(updates).toEqual([['update', 't1', { estimatedDuration: 60 }], ['fetch', 't1'], ['update', 't1', { estimatedDuration: null }], ['fetch', 't1']])
		expect(vm.saving).toBe(false)
	})

	it('stores a picked size', async () => {
		const vm = estimateField({ id: 't2' }, SIZES)
		await vm.save({ id: 'S' })
		expect(updates).toEqual([['update', 't2', { estimateSize: 'S' }], ['fetch', 't2']])
	})
})

const mixin = (await import('../../src/mixins/projectWorkflow.js')).default

describe('projectWorkflow', () => {
	it('reads the project\'s workflow, and none for a project without one', async () => {
		const vm = { workflow: 'stale' }
		await mixin.watch['project.workflow'].handler.call(vm, 'w2')
		expect(vm.workflow).toEqual({ id: 'w2', estimateScale: 'storyPoints' })
		await mixin.watch['project.workflow'].handler.call(vm, null)
		expect(vm.workflow).toBe(null)
	})
})
