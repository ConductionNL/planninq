/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the portfolio overview (portfolio-status-overview, section 2).
 *
 *   @e2e portfolio-overview::a-portfolio-manager-opens-the-overview
 *   @e2e portfolio-overview::the-roll-up-of-a-portfolio
 *
 * The suite runs as the admin, who manages the portfolio it creates. A
 * manager who is on none of the projects is covered by the read rule
 * (PlanninqRegisterSchemaTest) and by the live check in the PR, because the
 * shared instance provisions no second, non-admin account.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const TODAY = new Date().toISOString().slice(0, 10)

test.describe('Portfolio overview', () => {
	test('a portfolio manager opens the overview, and the roll-up of a portfolio', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const title = `Ruimte ${RUN}`
			const portfolio = await createObject(api, 'projectPortfolio', { title, managers: [ADMIN_USER], order: 0 })
			made.push(['projectPortfolio', portfolio])
			const project = async (name: string, status = 'active') => {
				const id = await createObject(api, 'project', { title: `${name} ${RUN}`, status, owner: ADMIN_USER, members: [ADMIN_USER], portfolio, startDate: '2026-01-01', endDate: '2026-12-31' })
				made.push(['project', id])
				return id
			}
			const report = async (projectId: string, money: string) => {
				const aspects = { statusMoney: money, statusOrganisation: 'onTrack', statusTime: 'onTrack', statusInformation: 'onTrack', statusQuality: 'onTrack', statusRisk: 'onTrack' }
				made.push(['projectStatusReport', await createObject(api, 'projectStatusReport', { project: projectId, reportDate: TODAY, ...aspects })])
			}
			await report(await project('Omgevingsvisie'), 'onTrack')
			await report(await project('Bestemmingsplan', 'completed'), 'onTrack')
			await report(await project('Woningbouw'), 'offTrack')
			await project('Zonder rapportage')

			await page.goto(new URL(`portfolio/status?portfolio=${portfolio}`, PLANNINQ_ROOT).toString())

			const rows = page.getByTestId('portfolio-project-row')
			await expect(rows).toHaveCount(4, { timeout: 30_000 })
			await expect(page.getByTestId('portfolio-projects')).toContainText('Completed')
			await expect(rows.first().getByTestId('health-status').first()).toHaveAttribute('data-status', /onTrack|offTrack|none/)
			await expect(rows.first().locator('.health-status svg').first()).toBeVisible()

			const money = page.getByTestId('portfolio-rollup-money')
			await expect(money.getByTestId('portfolio-rollup-counts')).toHaveText('2 on track, 0 at risk, 1 off track, 1 no report')
			await expect(money.getByTestId('portfolio-rollup-state')).toHaveText(/Off track/)
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
