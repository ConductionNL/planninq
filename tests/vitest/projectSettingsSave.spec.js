/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for saving a project's details from the settings sidebar
 * (ProjectSettingsSidebar saveDetails): what is sent, what the user is told,
 * and the state it leaves.
 *
 * @spec openspec/changes/archive/2026-09-30-planning-timeline-editing/tasks.md#task-3.1
 */
import { beforeEach, describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const said = []
const dialogs = {
	showError: (message) => said.push(['error', message]),
	showSuccess: (message) => said.push(['success', message]),
}

const { options } = await loadSfcWithImports(new URL('../../src/components/ProjectSettingsSidebar.vue', import.meta.url), {
	modules: { '@nextcloud/dialogs': dialogs, '@nextcloud/auth': { getCurrentUser: () => ({ uid: 'anna' }) } },
	vue: async () => ({}),
})

/**
 * A fake sidebar with the component's methods.
 *
 * @param {object} given What differs from a plain project.
 * @return {object}
 */
function sidebar(given = {}) {
	const vm = {
		project: { id: 'p1', key: '', customFields: { kept: 'yes', cleared: 'x' } },
		form: { title: ' Plan ', description: '  ', color: '#123456', icon: ' rocket ', portfolio: { id: 'pf1' }, parent: null, autoSchedule: true, key: ' ab ' },
		projectFields: [],
		fieldForm: {},
		canEditKey: true,
		saving: null,
		missingFields: null,
		patched: [],
		answer: { id: 'p1' },
		parentChoices: [{ id: null, label: 'none' }, { id: 'pp', label: 'Parent' }],
		...given,
		t: (app, text) => text,
	}
	vm.projectsStore = {
		error: 'refused',
		patchProject: async (id, details) => {
			vm.patched.push([id, details, vm.saving])
			if (vm.answer instanceof Error) {
				throw vm.answer
			}
			return vm.answer
		},
	}
	for (const [name, method] of Object.entries(options.methods)) {
		vm[name] = method.bind(vm)
	}
	vm.clearedFields = () => ({ cleared: null })
	vm.saveFailure = (error) => `failed: ${error}`
	return vm
}

describe('ProjectSettingsSidebar saveDetails', () => {
	beforeEach(() => {
		said.length = 0
	})

	it('sends the details, the first key and the fields, and says the tasks get numbers', async () => {
		const vm = sidebar()
		await vm.saveDetails()
		expect(vm.patched).toEqual([['p1', {
			title: 'Plan',
			description: undefined,
			color: '#123456',
			icon: 'rocket',
			portfolio: 'pf1',
			parent: null,
			autoSchedule: true,
			customFields: { kept: 'yes', cleared: null },
			key: 'AB',
		}, true]])
		expect(Object.keys(vm.patched[0][1])).toEqual(['title', 'description', 'color', 'icon', 'portfolio', 'parent', 'autoSchedule', 'customFields', 'key'])
		expect([vm.saving, vm.missingFields, said]).toEqual([false, [], [['success', 'Project saved. The existing tasks get their numbers in the background.']]])
	})

	it('keeps the key out when it may not be edited, and a project with a key gets the plain message', async () => {
		const vm = sidebar({ canEditKey: false, project: { id: 'p1', key: 'PL' }, form: { title: 'P', description: 'd', color: '', icon: '', portfolio: null, parent: { id: 'pp' }, autoSchedule: 'yes', key: '' } })
		await vm.saveDetails()
		expect(vm.patched[0][1]).toEqual({ title: 'P', description: 'd', color: '', icon: undefined, portfolio: null, parent: 'pp', autoSchedule: false, customFields: { cleared: null } })
		expect(said).toEqual([['success', 'Project saved']])
	})

	it('sends no key when the form key is empty for a project without one', async () => {
		const vm = sidebar({ form: { title: 'P', description: '', color: '', icon: '', portfolio: null, parent: null, autoSchedule: false, key: '   ' } })
		await vm.saveDetails()
		expect(vm.patched[0][1].key).toBe(null)
		expect(said).toEqual([['success', 'Project saved']])
	})

	it('a refused save says why and puts the parent back', async () => {
		const vm = sidebar({ answer: null, project: { id: 'p1', key: '', parent: 'pp' } })
		await vm.saveDetails()
		expect([said, vm.form.parent, vm.saving]).toEqual([[['error', 'failed: refused']], { id: 'pp', label: 'Parent' }, false])
	})

	it('a failing save says it could not', async () => {
		const vm = sidebar({ answer: new Error('offline') })
		await vm.saveDetails()
		expect([said, vm.saving]).toEqual([[['error', 'Could not save project']], false])
	})

	it('saves nothing while a required field is missing', async () => {
		const vm = sidebar({ projectFields: [{ key: 'budget', label: 'Budget', required: true }], fieldForm: { budget: '' } })
		await vm.saveDetails()
		expect([vm.patched, vm.saving, vm.missingFields.length > 0, said]).toEqual([[], null, true, []])
	})
})
