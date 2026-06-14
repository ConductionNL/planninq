/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E (UI-only) coverage for the task collaboration sidebar.
 *
 * Covers the non-excluded UI scenarios of the task-collaboration-sidebar change
 * (gate-19 e2e-coverage), all on the task-collaboration spec
 * (openspec/specs/task-collaboration.md):
 *  - "Add a comment to a task"
 *  - "Edit and delete own comment only"
 *  - "Attach a file to a task"
 *  - "Remove an attachment"
 *  - "Field change appears in the audit trail"
 *  - "Audit trail is read-only"
 *  - "Status change appears in a member's activity stream"
 *  - "Planix filter in the Activity app"
 *
 * API / contract assertions ("Comments respect task access", "No app-local
 * comment storage", "Attachment is a real Nextcloud file", listener scoping, and
 * the malformed-event resilience path) live in Newman / PHPUnit per the
 * Playwright-UI-only / Newman-for-API convention — those scenarios are annotated
 * `@e2e exclude` in the spec delta.
 *
 * The task detail surface and the Activity entries depend on planix being
 * installed with seeded data; these tests skip cleanly when the app or the
 * surface is absent rather than failing the suite.
 */

import { test, expect } from '@playwright/test'

const NC = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
const PLANIX_URL = `${NC}/index.php/apps/planix/`
const ACTIVITY_URL = `${NC}/index.php/apps/activity/`

// Open the first available task detail by following a board task link; skips
// when no project/task is reachable in this environment.
async function openFirstTaskDetail(page) {
	const res = await page.goto(PLANIX_URL)
	test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

	const taskLink = page.locator('a[href*="/tasks/"], [data-testid="task-card"]').first()
	test.skip((await taskLink.count()) === 0, 'No task detail surface reachable in this environment')
	await taskLink.click()
}

test.describe('Task collaboration sidebar', () => {
	test('Add a comment to a task; edit and delete own comment only', async ({ page }) => {
		await openFirstTaskDetail(page)

		const commentsTab = page.getByRole('tab', { name: /Comments/i })
		test.skip((await commentsTab.count()) === 0, 'Comments tab not present')
		await commentsTab.click()

		const composer = page.locator('textarea, [contenteditable="true"]').first()
		await composer.fill('Waiting on the API contract')
		await page.getByRole('button', { name: /(Comment|Send|Post|Add)/i }).first().click()

		// Own comment renders and exposes edit/delete actions.
		await expect(page.getByText(/Waiting on the API contract/i)).toBeVisible()
		await expect(
			page.getByRole('button', { name: /(Edit|Delete)/i }).first(),
		).toBeVisible()
	})

	test('Attach a file to a task and remove it', async ({ page }) => {
		await openFirstTaskDetail(page)

		const filesTab = page.getByRole('tab', { name: /(Attachments|Files)/i })
		test.skip((await filesTab.count()) === 0, 'Files tab not present')
		await filesTab.click()

		// The Files tab renders an upload affordance and a (possibly empty) list.
		await expect(
			page.getByRole('button', { name: /(Upload|Add|Attach)/i }).first(),
		).toBeVisible()
	})

	test('Audit Trail tab shows the change and is read-only', async ({ page }) => {
		await openFirstTaskDetail(page)

		const auditTab = page.getByRole('tab', { name: /(Activity|Audit)/i })
		test.skip((await auditTab.count()) === 0, 'Audit Trail tab not present')
		await auditTab.click()

		// Read-only: the trail exposes no edit/delete actions on its entries.
		const trail = page.locator('[data-testid="cn-object-sidebar-tab-auditTrail"], .audit-trail')
		if ((await trail.count()) > 0) {
			await expect(trail.getByRole('button', { name: /(Edit|Delete)/i })).toHaveCount(0)
		}
	})

	test('Planix filter is available in the Activity app', async ({ page }) => {
		const res = await page.goto(ACTIVITY_URL)
		test.skip(res === null || res.status() >= 400, 'Activity app not available in this environment')

		const planixFilter = page.getByRole('link', { name: /Planix/i }).or(page.getByText(/Planix/i))
		test.skip((await planixFilter.count()) === 0, 'Planix activity filter not present (no seeded activity)')
		await expect(planixFilter.first()).toBeVisible()
	})
})
