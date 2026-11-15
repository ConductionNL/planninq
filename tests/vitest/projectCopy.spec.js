/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for copying a project and starting one from a template: the
 * copy request, the copy API, the creation dialog's template path and the
 * copy dialog's save.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { copyBody, defaultCopyParts, templateOptions } from '../../src/utils/projectTemplates.js'
import { loadSfcWithImports } from './support/sfc.js'

const posted = []
let reply = { data: { id: 'new' } }
vi.mock('@nextcloud/axios', () => ({
	default: {
		post: async (url, body) => {
			posted.push([url, body])
			if (reply instanceof Error) {
				throw reply
			}
			return reply
		},
	},
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

const { copyProject } = await import('../../src/api/projectCopy.js')

beforeEach(() => {
	posted.length = 0
	reply = { data: { id: 'new' } }
})

describe('copyBody', () => {
	it('trims the title, keeps a key and start date, and takes everything but the people by default', () => {
		expect(copyBody({ title: '  Aanbesteding wegbeheer ', key: 'AWB', startDate: '2026-06-01' })).toEqual({
			title: 'Aanbesteding wegbeheer',
			key: 'AWB',
			startDate: '2026-06-01',
			parts: { columns: true, tasks: true, phases: true, dependencies: true, people: false },
		})
	})

	it('drops the dependencies without the tasks, and leaves out an empty key and date', () => {
		expect(copyBody({ title: 'P', key: '', startDate: '', parts: { tasks: false, people: true } })).toEqual({
			title: 'P',
			parts: { columns: true, tasks: false, phases: true, dependencies: false, people: true },
		})
	})

	it('gives a fresh default each time', () => {
		const first = defaultCopyParts()
		first.people = true
		expect(defaultCopyParts().people).toBe(false)
	})
})

describe('templateOptions', () => {
	it('offers only the templates, by title', () => {
		const projects = [{ id: 'b', title: 'Bouw', isTemplate: true }, { id: 'w', title: 'Werk' }, { id: 'a', title: 'Aanbesteding', isTemplate: true }, { id: 'n', isTemplate: true }]
		expect(templateOptions(projects)).toEqual([{ id: 'a', label: 'Aanbesteding' }, { id: 'b', label: 'Bouw' }, { id: 'n', label: 'n' }])
		expect(templateOptions(null)).toEqual([])
	})
})

describe('copyProject', () => {
	it('posts the request to the copy endpoint and answers the new project', async () => {
		expect(await copyProject('p 1', { title: 'T', parts: {} })).toEqual({ id: 'new' })
		expect(posted).toEqual([['/index.php/apps/planninq/api/projects/p%201/copy', { title: 'T', parts: {} }]])
	})

	it('throws the server\'s reason, or copy-error when it gave none', async () => {
		reply = Object.assign(new Error('403'), { response: { data: { error: 'Project creation is restricted to administrators.' } } })
		await expect(copyProject('p1', {})).rejects.toThrow('Project creation is restricted to administrators.')
		reply = new Error('network')
		await expect(copyProject('p1', {})).rejects.toThrow('copy-error')
	})
})

/**
 * A fake component with the methods of an SFC.
 *
 * @param {object} options The SFC's options.
 * @param {object} vm The state.
 * @return {object}
 */
function bind(options, vm) {
	for (const [name, method] of Object.entries(options.methods)) {
		vm[name] = method.bind(vm)
	}
	return vm
}

const said = []
const creation = await loadSfcWithImports(new URL('../../src/dialogs/ProjectCreationDialog.vue', import.meta.url), {
	modules: {
		'@nextcloud/dialogs': { showError: (message) => said.push(['error', message]), showSuccess: (message) => said.push(['success', message]) },
		'../api/projectCopy.js': { copyProject: async (id, body) => copyProject(id, body) },
	},
	vue: async () => ({}),
})

describe('ProjectCreationDialog from a template', () => {
	it('copies the template without its people, adds the project to the list and emits it', async () => {
		said.length = 0
		const emitted = []
		const vm = bind(creation.options, {
			form: { title: ' Aanbesteding wegbeheer ', key: 'awb', template: { id: 'tpl' }, startDate: '2026-06-01', description: '', color: '', icon: '' },
			isValid: true,
			loading: false,
			copying: false,
			projectsStore: { projects: [{ id: 'old' }], createProject: async () => { throw new Error('not called') } },
			t: (app, text) => text,
			$emit: (name, value) => emitted.push([name, value]),
		})
		await vm.submit()
		expect(posted).toEqual([['/index.php/apps/planninq/api/projects/tpl/copy', {
			title: 'Aanbesteding wegbeheer',
			key: 'AWB',
			startDate: '2026-06-01',
			parts: { columns: true, tasks: true, phases: true, dependencies: true, people: false },
		}]])
		expect(vm.projectsStore.projects).toEqual([{ id: 'old' }, { id: 'new' }])
		expect([emitted, said, vm.copying]).toEqual([[['created', { id: 'new' }]], [['success', 'Project created']], false])
	})
})

const copyDialog = await loadSfcWithImports(new URL('../../src/dialogs/ProjectCopyDialog.vue', import.meta.url), {
	modules: { '../api/projectCopy.js': { copyProject: async (id, body) => copyProject(id, body) } },
	vue: async () => ({}),
})

/**
 * A fake copy dialog.
 *
 * @return {object}
 */
function copier() {
	const vm = bind(copyDialog.options, {
		project: { id: 'p1', title: 'Wegbeheer' },
		title: 'Copy of Wegbeheer',
		startDate: '',
		parts: { ...defaultCopyParts(), people: false },
		saving: false,
		submitError: '',
		emitted: [],
		t: (app, text) => text,
	})
	vm.$emit = (name, value) => vm.emitted.push([name, value])
	return vm
}

describe('ProjectCopyDialog save', () => {
	it('copies the project with the chosen parts and emits the copy', async () => {
		const vm = copier()
		await vm.save()
		expect(posted).toEqual([['/index.php/apps/planninq/api/projects/p1/copy', { title: 'Copy of Wegbeheer', parts: defaultCopyParts() }]])
		expect([vm.emitted, vm.saving, vm.submitError]).toEqual([[['copied', { id: 'new' }]], false, ''])
	})

	it('shows the server\'s reason, or a retry message, and emits nothing', async () => {
		const vm = copier()
		reply = Object.assign(new Error('500'), { response: { data: { error: 'The project could not be copied. Nothing was kept.' } } })
		await vm.save()
		expect([vm.emitted, vm.submitError]).toEqual([[], 'The project could not be copied. Nothing was kept.'])
		reply = new Error('network')
		await vm.save()
		expect(vm.submitError).toBe('The project could not be copied. Please try again.')
	})
})
