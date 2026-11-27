/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for moving a project onto a workflow: where its tasks land,
 * what the confirm dialog says and writes, and column editing on a project
 * that follows a workflow.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.5
 */
import { beforeEach, describe, expect, it } from 'vitest'
import { workflowMapping } from '../../src/utils/workflows.js'
import { loadSfcWithImports } from './support/sfc.js'

const VERGUNNING = {
	id: 'w1',
	title: 'Vergunningverlening',
	columns: [{ key: 'intake', title: 'Intake' }, { key: 'beoordeling', title: 'Beoordeling' }, { key: 'besluit', title: 'Besluit', type: 'done' }],
}
const COLUMNS = [{ id: 'c-todo', title: 'To Do' }, { id: 'c-intake', title: ' intake ' }, { id: 'c-done', title: 'Done' }]
const TASKS = [
	...['t1', 't2'].map((id) => ({ id, column: 'c-todo' })),
	...['t3', 't4', 't5', 't6', 't7'].map((id) => ({ id, column: 'c-intake' })),
	...['t8', 't9'].map((id) => ({ id, column: 'c-done' })),
]

describe('workflowMapping', () => {
	it('keeps tasks whose column title is in the workflow, and moves the rest to its first column', () => {
		expect(workflowMapping(COLUMNS, TASKS, VERGUNNING)).toEqual({ moving: 4, target: 'Intake' })
	})

	it('keeps a column made from the workflow by its key, even when renamed', () => {
		const columns = [{ id: 'c-a', title: 'Renamed', workflowKey: 'beoordeling' }]
		expect(workflowMapping(columns, [{ column: 'c-a' }], VERGUNNING)).toEqual({ moving: 0, target: 'Intake' })
	})

	it('moves nothing onto a workflow without usable columns', () => {
		expect(workflowMapping(COLUMNS, TASKS, { columns: [{ title: 'No key' }] })).toEqual({ moving: 0, target: '' })
		expect(workflowMapping(COLUMNS, TASKS, null)).toEqual({ moving: 0, target: '' })
	})
})

const patched = []
let answer = { id: 'p1', workflow: 'w1' }
const store = {
	fetchColumns: async () => COLUMNS,
	fetchTasks: async () => TASKS,
	patchProject: async (id, partial) => {
		patched.push([id, partial])
		if (answer instanceof Error) {
			throw answer
		}
		return answer
	},
}
const dialog = await loadSfcWithImports(new URL('../../src/dialogs/WorkflowMoveDialog.vue', import.meta.url), {
	modules: { '../store/projects.js': { useProjectsStore: () => store } },
	vue: async () => ({}),
})

/**
 * A fake move dialog.
 *
 * @param {object|null} workflow The workflow to follow.
 * @return {object}
 */
function mover(workflow) {
	const vm = { project: { id: 'p1' }, workflow, loading: !!workflow, mapping: { moving: 0, target: '' }, saving: false, submitError: '', emitted: [] }
	vm.t = (app, text) => text
	vm.$emit = (name, value) => vm.emitted.push([name, value])
	vm.confirm = dialog.options.methods.confirm.bind(vm)
	return vm
}

describe('WorkflowMoveDialog', () => {
	beforeEach(() => {
		patched.length = 0
		answer = { id: 'p1', workflow: 'w1' }
	})

	it('counts the tasks that move before it saves', async () => {
		const vm = mover(VERGUNNING)
		await dialog.options.mounted.call(vm)
		expect([vm.loading, vm.mapping, patched]).toEqual([false, { moving: 4, target: 'Intake' }, []])
	})

	it('puts the project on the workflow, or takes it off', async () => {
		const onto = mover(VERGUNNING)
		await onto.confirm()
		const off = mover(null)
		await dialog.options.mounted.call(off)
		await off.confirm()
		expect(patched).toEqual([['p1', { workflow: 'w1' }], ['p1', { workflow: null }]])
		expect(onto.emitted).toEqual([['moved', { id: 'p1', workflow: 'w1' }]])
	})

	it('says so when the change was refused, and emits nothing', async () => {
		answer = null
		const vm = mover(VERGUNNING)
		await vm.confirm()
		answer = new Error('network')
		const thrown = mover(VERGUNNING)
		await thrown.confirm()
		expect([vm.emitted, vm.submitError, thrown.submitError]).toEqual([[], 'The workflow could not be changed. Please try again.', 'The workflow could not be changed. Please try again.'])
	})
})

const sidebar = await loadSfcWithImports(new URL('../../src/components/ProjectSettingsSidebar.vue', import.meta.url), {
	modules: { '@nextcloud/dialogs': { showError: () => {}, showSuccess: () => {} }, '@nextcloud/auth': { getCurrentUser: () => ({ uid: 'anna' }) } },
	vue: async () => ({}),
})

describe('ProjectSettingsSidebar workflow picker', () => {
	it('asks to confirm a different workflow, and nothing for the current one', () => {
		const vm = { project: { workflow: 'w1' }, movingWorkflow: null }
		sidebar.options.methods.pickWorkflow.call(vm, { id: 'w1' })
		expect(vm.movingWorkflow).toBe(null)
		sidebar.options.methods.pickWorkflow.call(vm, { id: null, workflow: null })
		expect(vm.movingWorkflow).toEqual({ id: null, workflow: null })
	})

	it('offers no workflow first, then each workflow', () => {
		const options = sidebar.options.computed.workflowOptions.call({ workflows: [VERGUNNING], t: (app, text) => text })
		expect(options).toEqual([{ id: null, label: 'No workflow', workflow: null }, { id: 'w1', label: 'Vergunningverlening', workflow: VERGUNNING }])
	})
})

const board = await loadSfcWithImports(new URL('../../src/views/ProjectBoard.vue', import.meta.url), {
	modules: { '@nextcloud/auth': { getCurrentUser: () => ({ uid: 'anna' }) } },
	vue: async () => ({}),
})

describe('ProjectBoard column editing', () => {
	it('lets the owner edit columns, but not on a project that follows a workflow', () => {
		const mayEditColumns = board.options.computed.mayEditColumns
		expect(mayEditColumns.call({ isOwner: true, project: { workflow: null } })).toBe(true)
		expect(mayEditColumns.call({ isOwner: true, project: { workflow: 'w1' } })).toBe(false)
		expect(mayEditColumns.call({ isOwner: false, project: {} })).toBe(false)
	})
})
