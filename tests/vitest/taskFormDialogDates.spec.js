/**
 * The task dialog refuses a start date after the due date (tasks-dates 3.1).
 *
 * Renders TaskFormDialog on the server with its package components stubbed
 * (planninq's vitest runs in node without a DOM, so this is its mount) and
 * checks the alert, the disabled Save button, and that save() writes nothing.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/tasks-dates/tasks.md#task-3.1
 */

import { describe, expect, it, vi } from 'vitest'
import { loadComponent, renderScene } from './support/ssr.js'

const DIALOG = new URL('../../src/dialogs/TaskFormDialog.vue', import.meta.url)
const MESSAGE = 'The start date is after the due date.'

/**
 * Render the dialog editing a task with these dates.
 *
 * @param {string|null} startDate The task's start date.
 * @param {string|null} dueDate The task's due date.
 * @return {Promise<{html: string, clicks: Array<string>}>}
 */
function renderWithDates(startDate, dueDate) {
	return renderScene(DIALOG, {
		props: { projectId: 'p1', task: { id: 't1', title: 'Check the drawings', startDate, dueDate } },
		spy: ['save'],
	})
}

/**
 * The Save button's printed attributes.
 *
 * @param {string} html The rendered dialog.
 * @return {string}
 */
function saveButton(html) {
	const match = html.match(/<nc-button [^>]*:data-testid="task-form-save"[^>]*>/)
	expect(match).not.toBeNull()
	return match[0]
}

describe('TaskFormDialog dates', () => {
	it('shows the alert and disables Save for a start after the due date', async () => {
		const { html } = await renderWithDates('2026-10-12', '2026-10-10')

		expect(html).toContain(`<p class="task-form-dialog__error" role="alert" data-testid="task-form-date-error">${MESSAGE}</p>`)
		expect(saveButton(html)).toContain(':disabled="true"')
	})

	it('shows no alert and enables Save for a start before the due date', async () => {
		const { html } = await renderWithDates('2026-10-01', '2026-10-10')

		expect(html).not.toContain('task-form-date-error')
		expect(html).not.toContain(MESSAGE)
		expect(saveButton(html)).toContain(':disabled="false"')
	})

	it('accepts the same day as start and due date', async () => {
		const { html } = await renderWithDates('2026-10-10', '2026-10-10')

		expect(html).not.toContain('task-form-date-error')
		expect(saveButton(html)).toContain(':disabled="false"')
	})

	it('accepts a start date without a due date', async () => {
		const { html } = await renderWithDates('2026-10-12', null)

		expect(html).not.toContain('task-form-date-error')
		expect(saveButton(html)).toContain(':disabled="false"')
	})

	it('saves nothing when save() runs with the dates refused', async () => {
		const { methods } = await loadComponent(DIALOG)
		const emit = vi.fn()
		const dialog = { dateError: MESSAGE, saving: false, submitError: '', $emit: emit, task: { id: 't1' }, draft: { title: 'Check the drawings' } }

		await methods.save.call(dialog)

		expect(dialog.saving).toBe(false)
		expect(dialog.submitError).toBe('')
		expect(emit).not.toHaveBeenCalled()
	})
})
