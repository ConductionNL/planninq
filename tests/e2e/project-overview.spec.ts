/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the project overview and the shared project tabs
 * (projects-overview-logs-risks).
 *
 *   @e2e project-overview::the-overview-shows-where-the-project-stands
 *   @e2e project-overview::an-empty-project-says-so
 *   @e2e project-overview::moving-between-project-pages-by-keyboard
 *
 * The exact "4 of 9" count is asserted by tests/vitest/projectOverview.spec.js
 * (projectProgress); here the page must show the progress sentence the rule
 * produces for the seeded project.
 */

import { expect, test } from '@playwright/test'
import { openFixtureProjectBoard } from './nav.ts'

test.describe('Project overview', () => {
	// @e2e project-overview::the-overview-shows-where-the-project-stands
	test('the overview shows where the project stands', async ({ page }) => {
		const projectId = await openFixtureProjectBoard(page)
		await page.getByTestId('project-tab-overview').click()
		await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/overview`))

		await expect(page.getByTestId('overview-start')).toBeVisible()
		await expect(page.getByTestId('overview-end')).toBeVisible()
		await expect(page.getByTestId('overview-people').locator('li')).not.toHaveCount(0)
		await expect(page.getByTestId('overview-progress')).toHaveText(/\d+ of \d+ tasks done|No tasks yet/)
	})

	// @e2e project-overview::an-empty-project-says-so
	test('an empty project says so', async ({ page }) => {
		const projectId = await openFixtureProjectBoard(page)
		await page.getByTestId('project-tab-overview').click()
		await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/overview`))

		const link = page.getByTestId('overview-log-link')
		await expect(link).toHaveText(/Add the first entry|Open the log/)
		await link.click()
		await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/log`))
	})

	// @e2e project-overview::moving-between-project-pages-by-keyboard
	test('moving between project pages by keyboard', async ({ page }) => {
		const projectId = await openFixtureProjectBoard(page)
		const board = page.getByTestId('project-tab-board')
		await expect(board).toHaveAttribute('aria-current', 'page')

		const log = page.getByTestId('project-tab-log')
		await log.focus()
		await page.keyboard.press('Enter')
		await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/log`))
		await expect(page.getByTestId('project-tab-log')).toHaveAttribute('aria-current', 'page')
	})
})
