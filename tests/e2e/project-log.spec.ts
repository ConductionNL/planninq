/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the project log (projects-overview-logs-risks).
 *
 *   @e2e project-logs::recording-a-meeting
 *   @e2e project-logs::turning-a-meeting-outcome-into-an-action
 *
 * "An outsider cannot read the log" carries an `@e2e exclude` in the spec:
 * the refusal is OpenRegister's read rule on the members list, asserted by
 * PlanninqRegisterSchemaTest and ProjectMemberAccessListenerTest.
 *
 * Titles carry a run id so repeated nightly runs do not collide.
 */

import { expect, test } from '@playwright/test'
import { openFixtureProjectBoard } from './nav.ts'

async function openLog(page) {
	const projectId = await openFixtureProjectBoard(page)
	await page.getByTestId('project-tab-log').click()
	await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/log`))
	return projectId
}

async function addMeeting(page, title: string) {
	await page.getByTestId('log-add').click()
	await page.getByTestId('log-entry-type').click()
	await page.getByRole('option', { name: 'Meeting' }).click()
	await page.getByTestId('log-entry-title').locator('input').fill(title)
	await page.getByTestId('log-entry-save').click()
	await expect(page.locator(`[data-testid="log-entry"][data-title="${title}"]`)).toHaveCount(1)
}

test.describe('Project log', () => {
	// @e2e project-logs::recording-a-meeting
	test('recording a meeting', async ({ page }) => {
		const title = `Kick-off ${Date.now()}`
		await openLog(page)
		await addMeeting(page, title)

		const first = page.getByTestId('log-entry').first()
		await expect(first).toHaveAttribute('data-title', title)
		await expect(first.getByTestId('log-entry-type-label')).toHaveText('Meeting')
		await expect(first.getByTestId('log-entry-meta')).toContainText('by ')

		await page.getByTestId('log-filter-meeting').click()
		const types = await page.getByTestId('log-entry-type-label').allTextContents()
		expect(types.every((type) => type.trim() === 'Meeting')).toBe(true)
	})

	// @e2e project-logs::turning-a-meeting-outcome-into-an-action
	test('turning a meeting outcome into an action', async ({ page }) => {
		const title = `Kick-off ${Date.now()}`
		const action = `Send the planning to the steering group ${Date.now()}`
		const projectId = await openLog(page)
		await addMeeting(page, title)

		const entry = page.locator(`[data-testid="log-entry"][data-title="${title}"]`)
		await entry.getByRole('button', { name: 'Entry actions' }).click()
		await page.getByTestId('log-entry-add-action').click()
		await page.getByTestId('log-action-title').locator('input').fill(action)
		await page.getByTestId('log-action-save').click()
		await expect(entry.getByTestId('log-entry-actions')).toContainText(action)

		await page.getByTestId('log-filter-actions').click()
		await expect(page.getByTestId('log-actions')).toContainText(action)

		await page.getByTestId('project-tab-board').click()
		await expect(page).toHaveURL(new RegExp(`/projects/${projectId}$`))
		await expect(page.locator('[data-cy="kanban-board"]')).toContainText(action)
	})
})
