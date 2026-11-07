/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for project status reports (portfolio-status-overview).
 *
 *   @e2e project-status-report::writing-a-status-report
 *   @e2e project-status-report::a-time-suggestion-from-late-tasks
 *
 * "The server decides the overall status" carries an `@e2e exclude` in the
 * spec: OpenRegister's calculation overwrites the overall on save, asserted by
 * PlanninqRegisterSchemaTest::testProjectStatusReportSchemaCalculatesTheOverallStatus.
 *
 * The suite runs as the admin, who may write reports on any project.
 */

import { expect, test } from '@playwright/test'
import { openFixtureProjectBoard } from './nav.ts'

async function openStatus(page) {
	const projectId = await openFixtureProjectBoard(page)
	await page.getByTestId('project-tab-status').click()
	await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/status`))
	return projectId
}

const ASPECTS = ['money', 'organisation', 'time', 'information', 'quality', 'risk']

test.describe('Project status reports', () => {
	test('writing a status report', async ({ page }) => {
		await openStatus(page)
		const before = await page.getByTestId('status-history-row').count()

		await page.getByTestId('status-report-new').click()
		for (const aspect of ASPECTS) {
			await page.getByTestId(`status-${aspect}-${aspect === 'time' ? 'atRisk' : 'onTrack'}`).click()
		}
		await page.getByTestId('status-note-time').locator('input').fill('Permit delayed by 3 weeks')
		await page.getByTestId('status-report-save').click()

		const latest = page.getByTestId('status-latest')
		await expect(latest.getByTestId('status-latest-overall')).toHaveAttribute('data-status', 'atRisk')
		await expect(page.getByTestId('status-latest-time')).toContainText('Permit delayed by 3 weeks')
		await expect(page.getByTestId('status-history-row')).toHaveCount(before + 1)
	})

	test('a time suggestion from late tasks', async ({ page }) => {
		await openStatus(page)
		await page.getByTestId('status-report-new').click()

		const suggestion = page.getByTestId('status-suggestion-time')
		await expect(suggestion).toBeVisible()
		for (const status of ['onTrack', 'atRisk', 'offTrack']) {
			await expect(page.getByTestId(`status-time-${status}`).locator('input')).not.toBeChecked()
		}
	})
})
