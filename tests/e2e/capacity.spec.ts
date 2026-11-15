/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the Capacity report per person (portfolio-people-capacity).
 *
 *   @e2e people-capacity::two-people-across-two-projects
 *   @e2e people-capacity::shared-tasks-do-not-double-the-hours
 *   @e2e people-capacity::switching-between-people-and-projects-by-keyboard
 *   @e2e people-capacity::limiting-to-a-portfolio
 *
 * The suite runs as the admin, who is on every project it creates and manages
 * the portfolio. The second person is a user id on the tasks only: the report
 * groups by the id and shows it when no display name resolves, and the shared
 * instance provisions no second account.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const BRAM = `bram-${RUN}`
const PAST = '2026-01-05'

test.describe('Capacity per person', () => {
	test('two people across two projects, a shared task, the keyboard toggle and a portfolio filter', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const portfolio = await createObject(api, 'projectPortfolio', { title: `Ruimte ${RUN}`, managers: [ADMIN_USER], order: 0 })
			made.push(['projectPortfolio', portfolio])
			const project = async (title: string, inPortfolio: boolean) => {
				const id = await createObject(api, 'project', { title: `${title} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], ...(inPortfolio ? { portfolio } : {}), startDate: '2026-01-01', endDate: '2026-12-31' })
				made.push(['project', id])
				return id
			}
			const task = async (projectId: string, data: object) => {
				made.push(['task', await createObject(api, 'task', { title: `Task ${RUN}`, status: 'open', project: projectId, ...data })])
			}
			const visie = await project('Omgevingsvisie', true)
			const wegen = await project('Wegbeheer', false)
			await task(visie, { assignedTo: ADMIN_USER, remainingEstimate: 240 })
			await task(visie, { assignedTo: ADMIN_USER, estimatedDuration: 180 })
			await task(visie, { assignedTo: ADMIN_USER, remainingEstimate: 180, sharedWith: [BRAM] })
			await task(wegen, { assignedTo: ADMIN_USER })
			await task(wegen, { assignedTo: ADMIN_USER })
			await task(wegen, { assignedTo: BRAM, dueDate: PAST, remainingEstimate: 240 })
			await task(wegen, {})
			await task(wegen, {})

			await page.goto(new URL('portfolio', PLANNINQ_ROOT).toString())
			for (const name of [`Omgevingsvisie ${RUN}`, `Wegbeheer ${RUN}`]) {
				await page.getByTestId('capacity-project-filter').locator('input').fill(name)
				await page.getByRole('option', { name }).click()
			}

			const admin = page.getByTestId(`capacity-person-${ADMIN_USER}`)
			await expect(admin.getByTestId('capacity-open')).toHaveText('5', { timeout: 30_000 })
			await expect(admin.getByTestId('capacity-hours')).toHaveText('10 h')
			await expect(admin.getByTestId('capacity-without-estimate')).toHaveText('2')

			const bram = page.getByTestId(`capacity-person-${BRAM}`)
			await expect(bram.getByTestId('capacity-open')).toHaveText('1')
			await expect(bram.getByTestId('capacity-overdue')).toHaveText('1')
			await expect(bram.getByTestId('capacity-hours')).toHaveText('4 h')
			await expect(bram.getByTestId('capacity-shared')).toHaveText('1')

			await expect(page.getByTestId('capacity-person-unassigned').getByTestId('capacity-open')).toHaveText('2')

			await admin.getByTestId('capacity-person-toggle').click()
			await expect(admin.getByTestId('capacity-person-toggle')).toHaveAttribute('aria-expanded', 'true')
			const breakdown = page.getByTestId('capacity-breakdown-row')
			await expect(breakdown).toHaveCount(2)
			await expect(breakdown.nth(0)).toContainText(`Omgevingsvisie ${RUN}`)
			await expect(breakdown.nth(0).locator('td').first()).toHaveText('3')
			await expect(breakdown.nth(1).locator('td').first()).toHaveText('2')

			const byProject = page.getByTestId('capacity-view-project')
			await byProject.focus()
			await page.keyboard.press('Enter')
			await expect(byProject).toHaveAttribute('aria-pressed', 'true')
			await expect(page.getByTestId('capacity-project-row')).toHaveCount(2)
			await page.getByTestId('capacity-view-person').click()

			await page.getByTestId('capacity-portfolio-picker').locator('input').fill(`Ruimte ${RUN}`)
			await page.getByRole('option', { name: `Ruimte ${RUN}` }).click()
			await expect(admin.getByTestId('capacity-open')).toHaveText('3')
			await expect(page.getByTestId(`capacity-person-${BRAM}`).getByTestId('capacity-open')).toHaveText('0')
			await expect(page.getByTestId('capacity-person-unassigned')).toHaveCount(0)
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
