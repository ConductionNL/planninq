/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the board-list capability (boards-list-toggle).
 *
 *   @e2e board-list::switch-to-the-list
 *   @e2e board-list::the-list-follows-the-label-filter
 */

import { expect, test } from '@playwright/test'
import { openFixtureProjectBoard } from './nav.ts'

test.describe('Board as a list', () => {
	// @e2e board-list::switch-to-the-list
	test('the list shows the board\'s cards and survives a reload', async ({ page }) => {
		await openFixtureProjectBoard(page)
		const cards = await page.getByTestId('task-card').evaluateAll((els) => els.map((el) => el.getAttribute('aria-label')))
		expect(cards.length).toBeGreaterThan(0)

		await page.getByTestId('view-list').click()
		await expect(page).toHaveURL(/view=list/)
		const rows = await page.getByTestId('board-list-row').evaluateAll((els) => els.map((el) => el.getAttribute('data-title')))
		expect(rows).toEqual(cards)

		await page.reload()
		await expect(page.getByTestId('board-list')).toBeVisible()
	})

	// @e2e board-list::the-list-follows-the-label-filter
	test('the list follows the label filter', async ({ page }) => {
		await openFixtureProjectBoard(page)
		const chips = page.getByTestId('label-filter-chip')
		test.skip(await chips.count() < 2, 'the seed carries no label to filter by')
		await chips.nth(1).click()
		const cards = await page.getByTestId('task-card').count()
		await page.getByTestId('view-list').click()
		await expect(page.getByTestId('board-list-row')).toHaveCount(cards)
	})
})
