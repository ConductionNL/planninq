/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the example data the setup wizard loads (platform-demo-data).
 *
 *   @e2e demo-data::the-admin-loads-example-data-and-sees-the-projects
 *   @e2e demo-data::my-tasks-shows-example-tasks-due-around-today
 *   @e2e demo-data::the-example-board-shows-cards-in-columns
 *   @e2e demo-data::the-example-timeline-draws-dependency-arrows
 *   @e2e demo-data::dates-follow-the-load-day
 *   @e2e register-schemas::a-fresh-install-has-labels-and-no-projects
 *
 * The wizard's own endpoints load the data (pick "Example data", then run
 * "Load the example data"); the pages are then read as the admin. The example
 * objects have fixed ids, so loading twice updates them instead of adding more.
 * The shared instance holds projects other specs made, so "no projects" on a
 * fresh install is asserted by PHPUnit PlanninqRegisterSchemaTest
 * testInstallSeedsOnlyDefaultLabels; here the install labels are checked.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const PORTAL = '0000de00-0000-4000-8000-000100000001'
const NAMESPACES_TASK = '0000de00-0000-4000-8000-000300000006'

/**
 * A date relative to today, as YYYY-MM-DD.
 *
 * @param days Days from today.
 */
function day(days: number): string {
	const date = new Date()
	date.setDate(date.getDate() + days)
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

test.describe('Example data', () => {
	test.beforeAll(async () => {
		const api = await adminApi()
		const pick = await api.post('/index.php/apps/planninq/api/setup/config', { data: { demo_dataset: 'demo' } })
		expect(pick.ok()).toBe(true)
		const load = await api.post('/index.php/apps/planninq/api/setup/action/load-demo-data', { data: {} })
		expect(load.ok(), await load.text()).toBe(true)
	})

	test('the admin sees the example projects, is their owner, and the dates sit around today', async ({ page }) => {
		const api = await adminApi()
		const project = await (await api.get(`${OBJECTS}/project/${PORTAL}`)).json()
		expect(project.owner).not.toBe('@operator')
		expect(project.members).toContain(project.owner)

		const task = await (await api.get(`${OBJECTS}/task/${NAMESPACES_TASK}`)).json()
		expect([day(3), day(2), day(4)]).toContain(task.dueDate)

		await page.goto(PLANNINQ_ROOT)
		await page.locator('#app-navigation-vue a[title="Projects"]').click()
		for (const title of ['Client Portal v2', 'Infrastructure Migration', 'Onboarding Automation']) {
			await expect(page.getByText(title).first()).toBeVisible({ timeout: 30_000 })
		}
	})

	test('my tasks lists the example tasks assigned to the admin', async ({ page }) => {
		await page.goto(`${PLANNINQ_ROOT}my-tasks`)
		await expect(page.getByText('Fix the redirect after login').first()).toBeVisible({ timeout: 30_000 })
		await expect(page.getByText('Set up the production namespaces').first()).toBeVisible()
	})

	test('the example board shows cards in columns', async ({ page }) => {
		await page.goto(`${PLANNINQ_ROOT}projects/${PORTAL}`)
		const lane = page.locator('section[data-column-id="0000de00-0000-4000-8000-000200000002"]')
		await expect(lane.getByText('Fix the redirect after login')).toBeVisible({ timeout: 30_000 })
	})

	test('the example timeline draws arrows between different tasks', async ({ page }) => {
		await page.goto(`${PLANNINQ_ROOT}projects/${PORTAL}/timeline`)
		await expect(page.getByTestId('timeline-edge').first()).toBeVisible({ timeout: 30_000 })
		expect(await page.getByTestId('timeline-edge').count()).toBeGreaterThanOrEqual(3)
	})

	test('a fresh install carries the five default labels', async () => {
		const api = await adminApi()
		const body = await (await api.get(`${OBJECTS}/label?_limit=200`)).json()
		const titles = (body.results ?? []).map((label: { title: string }) => label.title)
		for (const title of ['Bug', 'Feature', 'Docs', 'Design', 'Infrastructure']) {
			expect(titles).toContain(title)
		}
	})
})
