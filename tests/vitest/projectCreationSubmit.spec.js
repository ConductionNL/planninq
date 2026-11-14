/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for creating a project from the creation dialog
 * (ProjectCreationDialog submit): what is sent, what the user is told, and
 * what is emitted.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-12
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const said = []
const { options } = await loadSfcWithImports(new URL('../../src/dialogs/ProjectCreationDialog.vue', import.meta.url), {
	modules: {
		'@nextcloud/dialogs': {
			showError: (message) => said.push(['error', message]),
			showSuccess: (message) => said.push(['success', message]),
		},
	},
	vue: async () => ({}),
})

/**
 * A fake dialog with the component's methods.
 *
 * @param {object} given What differs from a valid, idle dialog.
 * @return {object}
 */
function dialog(given = {}) {
	const vm = {
		form: { title: ' Plan ', key: ' ab ', description: '  ', color: '', icon: ' rocket ' },
		prefill: null,
		isValid: true,
		loading: false,
		titleTouched: false,
		keyTaken: '',
		sent: [],
		emitted: [],
		answer: { id: 'p1' },
		...given,
		t: (app, text) => text,
		$emit: (name, value) => vm.emitted.push([name, value]),
	}
	vm.projectsStore = {
		createProject: async (fields) => {
			vm.sent.push(fields)
			if (vm.answer instanceof Error) {
				throw vm.answer
			}
			return vm.answer
		},
	}
	for (const [name, method] of Object.entries(options.methods)) {
		vm[name] = method.bind(vm)
	}
	return vm
}

/**
 * Submit a dialog and return what happened.
 *
 * @param {object} given What differs from a valid, idle dialog.
 * @return {Promise<object>}
 */
async function submit(given) {
	said.length = 0
	const vm = dialog(given)
	await vm.submit()
	return { sent: vm.sent, emitted: vm.emitted, said: [...said], titleTouched: vm.titleTouched, keyTaken: vm.keyTaken }
}

describe('ProjectCreationDialog submit', () => {
	it('sends the trimmed fields, says so and emits the project', async () => {
		const result = await submit()

		expect(result.sent).toEqual([{ title: 'Plan', key: 'AB', description: undefined, color: undefined, icon: 'rocket', caseReference: undefined, client: undefined }])
		expect(result.said).toEqual([['success', 'Project created']])
		expect(result.emitted).toEqual([['created', { id: 'p1' }]])
		expect(result.titleTouched).toBe(true)
	})

	it('sends the colour, the description and the case prefill when given', async () => {
		const result = await submit({
			form: { title: 'Plan', key: 'AB', description: ' Why ', color: '#123456', icon: '' },
			prefill: { caseReference: 'case-1', client: 'Gemeente' },
		})

		expect(result.sent).toEqual([{ title: 'Plan', key: 'AB', description: 'Why', color: '#123456', icon: undefined, caseReference: 'case-1', client: 'Gemeente' }])
	})

	it('sends nothing while the form is invalid or a save runs, but marks the title touched', async () => {
		const invalid = await submit({ isValid: false })
		const busy = await submit({ loading: true })

		expect([invalid.sent, invalid.said, invalid.titleTouched]).toEqual([[], [], true])
		expect([busy.sent, busy.said, busy.titleTouched]).toEqual([[], [], true])
	})

	it('marks a used key without a toast', async () => {
		const result = await submit({ answer: new Error('planninq-project-key-used') })

		expect([result.keyTaken, result.said, result.emitted]).toEqual(['used', [], []])
	})

	it('tells an admin-only refusal apart from any other failure', async () => {
		const restricted = await submit({ answer: new Error('Creation is restricted to administrators') })
		const other = await submit({ answer: new Error('boom') })
		const bare = await submit({ answer: Object.assign(new Error(''), { message: undefined }) })

		expect(restricted.said).toEqual([['error', 'Project creation is restricted to administrators.']])
		expect(other.said).toEqual([['error', 'Could not create project. Please try again.']])
		expect(bare.said).toEqual([['error', 'Could not create project. Please try again.']])
		expect([restricted.emitted, other.emitted, other.keyTaken]).toEqual([[], [], ''])
	})
})
