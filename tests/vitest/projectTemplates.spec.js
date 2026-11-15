/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for project templates: the Templates chip of the project list,
 * and the "Use as template" switch of the project settings sidebar.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */
import { describe, expect, it } from 'vitest'
import { isTemplate, projectsForChip, TEMPLATE_CHIP } from '../../src/utils/projectTemplates.js'
import { loadSfcWithImports } from './support/sfc.js'

const WORKING = { id: 'p1', status: 'active' }
const TEMPLATE = { id: 'p2', status: 'active', isTemplate: true }
const ARCHIVED = { id: 'p3', status: 'archived' }
const OLD = { id: 'p4', status: 'active', isTemplate: false }
const ALL = [WORKING, TEMPLATE, ARCHIVED, OLD]

describe('projectsForChip', () => {
	it('lists only the templates under the Templates chip', () => {
		expect(projectsForChip(ALL, TEMPLATE_CHIP)).toEqual([TEMPLATE])
	})

	it('keeps templates out of Active, and a project without the flag in it', () => {
		expect(projectsForChip(ALL, 'active')).toEqual([WORKING, OLD])
	})

	it('filters the other chips by status alone, and no chip lists everything', () => {
		expect(projectsForChip(ALL, 'archived')).toEqual([ARCHIVED])
		expect(projectsForChip(ALL, null)).toEqual(ALL)
	})

	it('reads only a true flag as a template', () => {
		expect([isTemplate(TEMPLATE), isTemplate(OLD), isTemplate({ isTemplate: 'yes' }), isTemplate(null)]).toEqual([true, false, false, false])
	})
})

const { options } = await loadSfcWithImports(new URL('../../src/components/ProjectSettingsSidebar.vue', import.meta.url), {
	modules: { '@nextcloud/dialogs': { showError: () => {}, showSuccess: () => {} }, '@nextcloud/auth': { getCurrentUser: () => ({ uid: 'anna' }) } },
	vue: async () => ({}),
})

/**
 * The Details patch of a fake sidebar.
 *
 * @param {boolean} mayEditCaseLink Whether the person is an owner, manager or admin.
 * @param {*} isTemplate The switch's value.
 * @return {object}
 */
function patchOf(mayEditCaseLink, isTemplate) {
	const vm = {
		project: { id: 'p1', key: 'PL', customFields: {} },
		form: { title: 'P', description: '', color: '', icon: '', portfolio: null, parent: null, autoSchedule: false, key: '', caseReference: null, isTemplate },
		projectFields: [],
		fieldForm: {},
		canEditKey: false,
		mayEditCaseLink,
	}
	for (const [name, method] of Object.entries(options.methods)) {
		vm[name] = method.bind(vm)
	}
	vm.clearedFields = () => ({})
	return vm.detailsPatch()
}

describe('ProjectSettingsSidebar template switch', () => {
	it('sends the flag for an owner, manager or admin', () => {
		expect(patchOf(true, true).isTemplate).toBe(true)
		expect(patchOf(true, 'on').isTemplate).toBe(false)
	})

	it('leaves the flag out for anyone else', () => {
		expect('isTemplate' in patchOf(false, true)).toBe(false)
	})
})
