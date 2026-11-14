/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the portfolio timeline (portfolio-status-overview, section 3).
 *
 *   @e2e gantt-timeline-view::a-portfolio-on-one-axis
 *
 * "Projects the viewer cannot read are left out" carries an `@e2e exclude` in
 * the spec: TimelineControllerTest::testForProjectsListsUnreadableProjectsUnderSkipped.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

test.describe('Portfolio timeline', () => {
	test('a portfolio on one axis', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const portfolio = await createObject(api, 'projectPortfolio', { title: `Tijdlijn ${RUN}`, managers: [ADMIN_USER], order: 0 })
			made.push(['projectPortfolio', portfolio])
			const ids: string[] = []
			for (const [name, start, end] of [['Later', '2026-05-01', '2026-06-30'], ['Eerst', '2026-01-01', '2026-02-28'], ['Midden', '2026-03-01', '2026-04-30']]) {
				const id = await createObject(api, 'project', { title: `${name} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], portfolio, startDate: start, endDate: end })
				made.push(['project', id])
				ids.push(id)
			}
			made.push(['task', await createObject(api, 'task', { title: `Taak ${RUN}`, project: ids[1], status: 'open', startDate: '2026-01-05', dueDate: '2026-01-20' })])

			await page.goto(new URL(`portfolio/timeline?portfolio=${portfolio}`, PLANNINQ_ROOT).toString())

			const projects = page.getByTestId('portfolio-timeline-project')
			await expect(projects).toHaveCount(3, { timeout: 30_000 })
			await expect(projects.nth(0)).toContainText('Eerst')
			await expect(projects.nth(1)).toContainText('Midden')
			await expect(projects.nth(2)).toContainText('Later')
			await expect(page.getByTestId('portfolio-timeline-bar-project')).toHaveCount(3)

			await projects.nth(0).focus()
			await page.keyboard.press('Enter')
			await expect(projects.nth(0)).toHaveAttribute('aria-expanded', 'true')
			await expect(page.getByTestId('portfolio-timeline-bar-task')).toHaveCount(1)
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
