/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the Flow tab and the portfolio flow (portfolio-flow-reports, section 2).
 *
 *   @e2e flow-metrics::a-queue-growing-before-review
 *   @e2e flow-metrics::cycle-time-of-finished-tasks
 *   @e2e flow-metrics::cycle-time-across-a-portfolio
 *
 * The audit trail cannot be back-dated, so the history these tests seed is
 * today's: tasks are created and moved now, and the assertions read the last
 * day of the period. The day-by-day replay over weeks (2 in Review on the
 * first day, 9 on the last; lead 7 and cycle 5 days) is asserted on fixed
 * histories by tests/unit/Service/FlowHistoryServiceTest.php.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

test.describe('Flow', () => {
	test('a queue growing before review, and cycle time of finished tasks', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Flow ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const columns: Record<string, string> = {}
			for (const [order, title] of ['To do', 'Doing', 'Review', 'Done'].entries()) {
				columns[title] = await createObject(api, 'column', { title, project, order, status: title === 'Done' ? 'done' : 'open' })
				made.push(['column', columns[title]])
			}
			for (let i = 0; i < 3; i++) {
				made.push(['task', await createObject(api, 'task', { title: `Review ${i} ${RUN}`, project, status: 'in_progress', column: columns.Review })])
			}
			const finished = await createObject(api, 'task', { title: `Finished ${RUN}`, project, status: 'open', column: columns['To do'] })
			made.push(['task', finished])
			await api.patch(`${OBJECTS}/task/${finished}`, { data: { column: columns.Done, status: 'done', completedAt: new Date().toISOString() } })

			await page.goto(new URL(`projects/${project}/flow`, PLANNINQ_ROOT).toString())

			await page.getByTestId('flow-table-toggle').click()
			const table = page.getByTestId('flow-table')
			await expect(table).toBeVisible({ timeout: 30_000 })
			await expect(table.locator('thead th')).toHaveText(['Date', 'To do', 'Doing', 'Review', 'Done'])
			await expect(table.locator('tbody tr').last().locator('td[data-column="Review"]')).toHaveText('3')

			await expect(page.getByTestId('flow-finished')).toHaveText('1')
			await expect(page.getByTestId('flow-slowest').getByRole('link')).toContainText(`Finished ${RUN}`)
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('cycle time across a portfolio', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const portfolio = await createObject(api, 'projectPortfolio', { title: `Ruimte ${RUN}`, managers: [ADMIN_USER], order: 0 })
			made.push(['projectPortfolio', portfolio])
			for (const name of ['Omgevingsvisie', 'Wegbeheer', 'Groen']) {
				const project = await createObject(api, 'project', { title: `${name} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], portfolio })
				made.push(['project', project])
				const task = await createObject(api, 'task', { title: `${name} taak ${RUN}`, project, status: 'done', completedAt: new Date().toISOString() })
				made.push(['task', task])
			}

			await page.goto(new URL(`portfolio/flow?portfolio=${portfolio}`, PLANNINQ_ROOT).toString())

			await expect(page.getByTestId('flow-finished')).toHaveText('3', { timeout: 30_000 })
			await page.getByTestId('portfolio-flow-projects').getByText(`Groen ${RUN}`).click()
			await expect(page.getByTestId('flow-finished')).toHaveText('2')
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
