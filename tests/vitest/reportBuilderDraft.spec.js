/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Vitest tests for the draft the report builder opens with
 * (ReportBuilderDialog data): an empty report gets the defaults, an existing
 * one keeps its values, and the draft never shares arrays with the report.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const { options } = await loadSfcWithImports(new URL('../../src/dialogs/ReportBuilderDialog.vue', import.meta.url), { vue: async () => ({}) })

describe('ReportBuilderDialog data', () => {
	it('opens a new report with the defaults', () => {
		expect(options.data.call({ report: {} })).toEqual({
			open: true,
			saving: false,
			error: '',
			draft: { title: '', projects: [], filters: { status: null, priority: null }, groupBy: 'assignedTo', metric: 'count', display: 'bar', shared: 'private' },
		})
	})

	it('opens an existing report with its values and its own copy of the projects', () => {
		const report = { title: 'Load', projects: ['p1', 'p2'], filters: { status: 'open', label: 'x' }, groupBy: 'project', metric: 'hours', display: 'table', shared: 'everyone' }
		const state = options.data.call({ report })

		expect(state.draft).toEqual({ title: 'Load', projects: ['p1', 'p2'], filters: { status: 'open', priority: null, label: 'x' }, groupBy: 'project', metric: 'hours', display: 'table', shared: 'everyone' })
		expect(state.draft.projects).not.toBe(report.projects)
		expect(state.draft.filters).not.toBe(report.filters)
	})

	it('treats empty values as missing', () => {
		const state = options.data.call({ report: { title: '', projects: null, filters: null, groupBy: '', metric: '', display: '', shared: '' } })

		expect(state.draft).toEqual({ title: '', projects: [], filters: { status: null, priority: null }, groupBy: 'assignedTo', metric: 'count', display: 'bar', shared: 'private' })
	})
})
