/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for money across a portfolio and the finance import
 * (portfolio-finance, tasks 3.2 and 3.4).
 *
 *   @e2e portfolio-finance::totals-for-a-portfolio
 *   @e2e finance-import::assigning-an-unmatched-line
 *
 * "A project outside your reach is not totalled" carries an `@e2e exclude`:
 * the suite signs in as the admin only. "An imported line lands on its
 * project" is asserted by the Newman folder "Finance Import".
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

test.describe('Portfolio finance', () => {
	test('totals for a portfolio', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const portfolio = await createObject(api, 'projectPortfolio', { title: `Ruimte ${RUN}`, managers: [ADMIN_USER], order: 0 })
			made.push(['projectPortfolio', portfolio])
			for (const [name, budget, actual] of [['Omgevingsvisie', 50000, 20000], ['Stadspark', 30000, 35000]] as const) {
				const id = await createObject(api, 'project', { title: `${name} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], portfolio, budgetAmount: budget })
				made.push(['project', id])
				made.push(['financeLine', await createObject(api, 'financeLine', { project: id, kind: 'actual', category: 'Other', amount: actual, source: 'manual' })])
			}

			await page.goto(new URL(`portfolio/finance?portfolio=${portfolio}`, PLANNINQ_ROOT).toString())

			await expect(page.getByTestId('portfolio-finance-row')).toHaveCount(2)
			await expect(page.getByTestId('portfolio-finance-total-budget')).toContainText('80')
			await expect(page.getByTestId('portfolio-finance-total-actual')).toContainText('55')
			const second = page.locator(`[data-testid="portfolio-finance-row"][data-project="Stadspark ${RUN}"]`)
			await expect(second.getByTestId('portfolio-finance-remaining')).toContainText(/over budget|boven budget/i)
		} finally {
			await removeObjects(api, made)
		}
	})

	test('assigning an unmatched line', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Omgevingsvisie ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const line = await createObject(api, 'financeLine', { projectKey: `OMGV${RUN}`, kind: 'actual', amount: 700, externalRef: `FIN-U-${RUN}`, source: 'import', category: 'Other' })
			made.push(['financeLine', line])

			await page.goto(new URL('finance/unmatched', PLANNINQ_ROOT).toString())
			await expect(page.getByText(`FIN-U-${RUN}`)).toBeVisible()

			const res = await api.patch(`${OBJECTS}/financeLine/${line}`, { data: { project } })
			expect(res.ok(), `assign: ${res.status()}`).toBe(true)

			await page.reload()
			await expect(page.getByText(`FIN-U-${RUN}`)).toHaveCount(0)
			await page.goto(new URL(`projects/${project}/finance`, PLANNINQ_ROOT).toString())
			await expect(page.locator('[data-testid="finance-line"][data-source="import"]')).toHaveCount(1)
		} finally {
			await removeObjects(api, made)
		}
	})
})
