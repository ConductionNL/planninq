/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the risk register (projects-overview-logs-risks).
 *
 *   @e2e risk-register::adding-a-risk
 *   @e2e risk-register::a-project-leader-scans-risks-across-projects
 *   @e2e risk-register::switching-to-a-three-level-scale
 *   @e2e risk-register::a-smaller-scale-is-refused-while-risks-use-a-higher-level
 *
 * "A client cannot write its own score" carries an `@e2e exclude` in the
 * spec: OpenRegister's calculation overwrites the score on save, asserted by
 * PlanninqRegisterSchemaTest::testRiskSchemaCalculatesItsScore.
 *
 * The scale tests run as the admin and put the five-level default back at
 * the end, so the other risk tests keep their five by five map.
 *
 * Titles carry a run id so repeated nightly runs do not collide.
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as NC } from './base-url.ts'
import { openFixtureProjectBoard } from './nav.ts'

async function openRisks(page) {
	const projectId = await openFixtureProjectBoard(page)
	await page.getByTestId('project-tab-risks').click()
	await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/risks`))
	return projectId
}

async function pick(page, testId: string, option: RegExp | string) {
	await page.getByTestId(testId).click()
	await page.getByRole('option', { name: option }).first().click()
}

async function addRisk(page, title: string, likelihood: number, impact: number) {
	await page.getByTestId('risk-add').click()
	await page.getByTestId('risk-title').locator('input').fill(title)
	await pick(page, 'risk-likelihood', new RegExp(`^${likelihood}\\.`))
	await pick(page, 'risk-impact', new RegExp(`^${impact}\\.`))
	await pick(page, 'risk-response', 'Reduce')
	await page.getByTestId('risk-countermeasures').locator('textarea').fill('Order the parts a month early.')
	await page.getByTestId('risk-save').click()
	await expect(page.locator(`[data-testid="risk-row"][data-title="${title}"]`)).toHaveCount(1)
}

async function setLevels(page, levels: number) {
	await page.goto(`${NC}/index.php/settings/admin/planninq`)
	await page.getByTestId('risk-scale-levels').selectOption(String(levels))
	await page.getByTestId('risk-scale-save').click()
}

test.describe('Risk register', () => {
	// @e2e risk-register::adding-a-risk
	test('adding a risk', async ({ page }) => {
		const title = `Supplier delivers late ${Date.now()}`
		await openRisks(page)
		await addRisk(page, title, 4, 3)

		const row = page.locator(`[data-testid="risk-row"][data-title="${title}"]`)
		await expect(row.getByTestId('risk-score')).toContainText('12')
		const cell = page.getByTestId('risk-cell-4-3')
		await expect(cell).toContainText('High')
		await expect(cell.locator('.risk-heat-map__count')).not.toHaveText('0')
	})

	// @e2e risk-register::a-project-leader-scans-risks-across-projects
	test('a project leader scans risks across projects', async ({ page }) => {
		const title = `Permit refused ${Date.now()}`
		await openRisks(page)
		await addRisk(page, title, 5, 5)

		await page.locator('#app-navigation-vue a[title="Risks"]').click()
		await expect(page).toHaveURL(/\/projects\/risks$/)
		await expect(page.getByText(title)).toBeVisible()
	})

	// @e2e risk-register::a-smaller-scale-is-refused-while-risks-use-a-higher-level
	test('a smaller scale is refused while risks use a higher level', async ({ page }) => {
		await openRisks(page)
		await addRisk(page, `Flood ${Date.now()}`, 5, 1)

		await setLevels(page, 4)
		await expect(page.getByTestId('risk-scale-error')).toHaveText(/risks? uses? level 5\. Change (them|it) first\./)
	})

	// @e2e risk-register::switching-to-a-three-level-scale
	test('switching to a three-level scale', async ({ page }) => {
		// Only runs on an instance with no risk above level 3, so it is
		// skipped when an earlier test in this run left a level 5 risk.
		await setLevels(page, 3)
		const refused = await page.getByTestId('risk-scale-error').isVisible().catch(() => false)
		test.skip(refused, 'risks above level 3 exist on this instance')

		await openRisks(page)
		await expect(page.getByTestId('risk-heat-map').locator('tbody tr')).toHaveCount(3)
		await setLevels(page, 5)
	})
})
