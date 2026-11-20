/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for platform-baseline REQ-PLB-001 (task 4.1).
 *
 *   @e2e platform-baseline::an-automated-accessibility-check-on-the-main-pages
 *
 * axe-core (a direct dependency) runs on the dashboard, a project board and a
 * task page with seeded data, restricted to the WCAG 2.1 A and AA rules, and
 * the test fails on any violation it reports. The check is scoped to the
 * app's own content (`#content`), not the Nextcloud header around it, which
 * planninq does not render.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import * as path from 'path'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const AXE = path.resolve(__dirname, '..', '..', 'node_modules', 'axe-core', 'axe.min.js')
const WCAG_21_A_AA = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']

interface AxeViolation {
	id: string
	impact: string | null
	help: string
	nodes: Array<{ target: string[] }>
}

/**
 * Run axe-core on the page's app content and return its violations.
 *
 * @param page The page, already showing what is checked.
 */
async function wcagViolations(page: Page): Promise<AxeViolation[]> {
	await page.addScriptTag({ path: AXE })

	return page.evaluate(async (tags) => {
		const axe = (window as unknown as { axe: { run: (context: unknown, options: unknown) => Promise<{ violations: AxeViolation[] }> } }).axe
		const context = document.querySelector('#content') ?? document
		const result = await axe.run(context, { runOnly: { type: 'tag', values: tags }, resultTypes: ['violations'] })

		return result.violations.map((violation) => ({
			id: violation.id,
			impact: violation.impact,
			help: violation.help,
			nodes: violation.nodes.map((node) => ({ target: node.target })),
		}))
	}, WCAG_21_A_AA)
}

/**
 * A readable list of the violations, for the failure message.
 *
 * @param violations What axe reported.
 */
function describe(violations: AxeViolation[]): string {
	return violations
		.map((violation) => `${violation.id} (${violation.impact}): ${violation.help} at ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)
		.join('\n')
}

test.describe('Accessibility of the main pages (REQ-PLB-001)', () => {
	test('the dashboard, a project board and a task page have no WCAG 2.1 A or AA violation', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Axe ${RUN}`, status: 'active', key: `AX${RUN.slice(-4).toUpperCase()}`, owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const columns: Record<string, string> = {}
			for (const [order, title] of ['To do', 'In progress', 'Done'].entries()) {
				columns[title] = await createObject(api, 'column', { title, project, order, status: title === 'Done' ? 'done' : 'open' })
				made.push(['column', columns[title]])
			}
			const task = await createObject(api, 'task', { title: `Axe task ${RUN}`, project, status: 'in_progress', column: columns['In progress'], description: 'A task the accessibility check opens.' })
			made.push(['task', task])
			made.push(['task', await createObject(api, 'task', { title: `Axe second task ${RUN}`, project, status: 'open', column: columns['To do'] })])

			await page.goto(PLANNINQ_ROOT)
			await expect(page.locator('#app-navigation-vue')).toBeVisible({ timeout: 30_000 })
			await page.waitForLoadState('domcontentloaded')
			const dashboard = await wcagViolations(page)
			expect(dashboard, `dashboard:\n${describe(dashboard)}`).toEqual([])

			await page.goto(`${PLANNINQ_ROOT}projects/${project}`)
			await expect(page.getByText(`Axe task ${RUN}`).first()).toBeVisible({ timeout: 30_000 })
			await page.waitForLoadState('domcontentloaded')
			const board = await wcagViolations(page)
			expect(board, `project board:\n${describe(board)}`).toEqual([])

			await page.goto(`${PLANNINQ_ROOT}projects/${project}/tasks/${task}`)
			await expect(page.getByText(`Axe task ${RUN}`).first()).toBeVisible({ timeout: 30_000 })
			await page.waitForLoadState('domcontentloaded')
			const taskPage = await wcagViolations(page)
			expect(taskPage, `task page:\n${describe(taskPage)}`).toEqual([])
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
