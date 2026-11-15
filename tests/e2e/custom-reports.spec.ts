/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for reports a user builds (portfolio-flow-reports, section 3).
 *
 *   @e2e custom-reports::open-tasks-per-assignee-across-two-projects
 *   @e2e custom-reports::a-viewer-on-fewer-projects
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

test.describe('Custom reports', () => {
	test('open tasks per assignee across two projects', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const projects: string[] = []
			for (const name of ['Omgevingsvisie', 'Wegbeheer']) {
				const id = await createObject(api, 'project', { title: `${name} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
				made.push(['project', id])
				projects.push(id)
				made.push(['task', await createObject(api, 'task', { title: `${name} open ${RUN}`, project: id, status: 'open', assignedTo: ADMIN_USER })])
			}

			await page.goto(new URL('reports/custom', PLANNINQ_ROOT).toString())
			await page.getByTestId('report-new').click()
			await page.getByTestId('report-title').locator('input').fill(`Open work per person ${RUN}`)
			const picker = page.getByTestId('report-projects')
			for (const name of ['Omgevingsvisie', 'Wegbeheer']) {
				await picker.locator('input').fill(`${name} ${RUN}`)
				await page.getByRole('option', { name: `${name} ${RUN}` }).click()
			}
			await page.getByTestId('report-filter-status').locator('input').fill('Open')
			await page.getByRole('option', { name: 'Open', exact: true }).click()
			await page.getByTestId('report-display-bar').click()
			await page.getByTestId('report-save').click()

			await expect(page).toHaveURL(/reports\/custom\/[^/]+$/, { timeout: 30_000 })
			const bar = page.getByTestId('report-bar').filter({ hasText: ADMIN_USER })
			await expect(bar).toContainText('2')

			await page.goto(new URL('reports/custom', PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('reports-mine')).toContainText(`Open work per person ${RUN}`)
			const id = page.url().split('/').pop() ?? ''
			if (id) {
				made.push(['taskReport', id])
			}
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('a viewer on fewer projects', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const projects: string[] = []
			for (let i = 0; i < 2; i++) {
				const id = await createObject(api, 'project', { title: `Zichtbaar ${i} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
				made.push(['project', id])
				projects.push(id)
			}
			// Three project ids the admin's own project list does not hold stand in for
			// projects a viewer cannot read; the page counts them as not visible.
			const unseen = ['0000de00-0000-4000-8000-0000ffff0001', '0000de00-0000-4000-8000-0000ffff0002', '0000de00-0000-4000-8000-0000ffff0003']
			const report = await createObject(api, 'taskReport', { title: `Gedeeld ${RUN}`, projects: [...projects, ...unseen], groupBy: 'status', metric: 'count', display: 'table', shared: 'readers' })
			made.push(['taskReport', report])

			await page.goto(new URL(`reports/custom/${report}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('report-hidden')).toContainText('3 of 5 projects in this report are not visible to you', { timeout: 30_000 })
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
