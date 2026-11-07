/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for a project's money (portfolio-finance).
 *
 *   @e2e project-delivery::setting-a-fixed-price-budget
 *   @e2e project-delivery::budget-against-actual-cost-with-booked-time
 *   @e2e project-delivery::lines-from-the-finance-system-cannot-be-edited
 *
 * "Members do not see the money" carries an `@e2e exclude` in the spec: the
 * suite signs in as the admin only. It is asserted by
 * PlanninqRegisterSchemaTest::testFinanceLineSchemaAndItsRules (no member rule
 * on read), FinanceLineListenerTest (members are not stamped as readers) and
 * tests/vitest/finance.spec.js "owner, portfolio managers and admins see the
 * amounts; members do not".
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

test.describe('Project finance', () => {
	test('setting a fixed-price budget', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const id = await createObject(api, 'project', { title: `Stadspark ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', id])
			await page.goto(new URL(`projects/${id}/finance`, PLANNINQ_ROOT).toString())

			await page.getByTestId('finance-billable').click()
			await page.getByTestId('finance-billing-model').click()
			await page.getByRole('option', { name: /Fixed price/i }).click()
			await page.getByTestId('finance-budget-amount').locator('input').fill('56000')
			await page.getByTestId('finance-budget-hours-input').locator('input').fill('400')
			await page.getByTestId('finance-terms-save').click()

			await expect(page.getByTestId('finance-budget')).toContainText('56')
			await expect(page.getByTestId('finance-budget-hours')).toContainText('400')

			await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
			const row = page.locator('li', { hasText: `Stadspark ${RUN}` })
			await expect(row).toContainText('Billable')
			await expect(row).toContainText('56')
		} finally {
			await removeObjects(api, made)
		}
	})

	test('budget against actual cost with booked time', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const id = await createObject(api, 'project', { title: `Omgevingsvisie ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], budgetAmount: 10000, hourlyRate: 100 })
			made.push(['project', id])
			made.push(['plannedTimeEntry', await createObject(api, 'plannedTimeEntry', { project: id, user: ADMIN_USER, duration: 1200, date: '2026-09-28' })])
			made.push(['financeLine', await createObject(api, 'financeLine', { project: id, kind: 'actual', category: 'Materials', amount: 1500, source: 'manual' })])

			await page.goto(new URL(`projects/${id}/finance`, PLANNINQ_ROOT).toString())

			await expect(page.locator('[data-category="labour"] [data-testid="finance-cell-actual"]')).toContainText('2')
			await expect(page.locator('[data-category="Materials"] [data-testid="finance-cell-actual"]')).toContainText('1')
			await expect(page.getByTestId('finance-total-actual')).toContainText('3')
			await expect(page.getByTestId('finance-total-remaining')).toContainText('6')
		} finally {
			await removeObjects(api, made)
		}
	})

	test('lines from the finance system cannot be edited', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const id = await createObject(api, 'project', { title: `Import ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', id])
			made.push(['financeLine', await createObject(api, 'financeLine', { project: id, kind: 'actual', category: 'Other', amount: 1250, source: 'import', externalRef: `FIN-${RUN}` })])

			await page.goto(new URL(`projects/${id}/finance`, PLANNINQ_ROOT).toString())

			const line = page.locator('[data-testid="finance-line"][data-source="import"]')
			await expect(line.getByTestId('finance-line-imported')).toBeVisible()
			await expect(line.getByTestId('finance-line-edit')).toHaveCount(0)
		} finally {
			await removeObjects(api, made)
		}
	})
})
