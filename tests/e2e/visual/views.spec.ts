/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Visual-regression baselines for every planix page view (gate-26).
 *
 * One spec per component under src/views/, driven through the app's own
 * navigation — never a hand-built deep link. nav.ts records why: the router is
 * `createWebHistory` mounted at `generateUrl('/apps/planix')`, so a `#/…`
 * fragment or an `/index.php/apps/planix/<subroute>` URL matches no route and
 * the catch-all silently renders the Dashboard. A visual baseline captured that
 * way would be a screenshot of the wrong screen that passes forever.
 *
 * Each test therefore ASSERTS IT IS ON THE RIGHT SCREEN before it screenshots.
 * Without that assertion a redirect to the Dashboard would produce nine
 * identical baselines and nine green tests — the failure mode this file exists
 * to prevent.
 *
 * Masking: `.live-region`-style timestamps and any element carrying
 * `data-visual-mask` are masked so a baseline does not churn on wall-clock text.
 */

import { expect, test, type Page } from '@playwright/test'
import { FIXTURE } from '../fixtures/seed'
import { PLANIX_ROOT, openFixtureProjectBoard } from '../nav'

/**
 * Elements whose content is time-dependent and would churn every run.
 *
 * @param page the Playwright page
 * @return locators to mask out of the screenshot
 */
function masks(page: Page) {
	return [page.locator('[data-visual-mask]'), page.locator('time')]
}

/**
 * Screenshot the app content area once it has settled.
 *
 * @param page the Playwright page
 * @param name baseline file name
 * @return void
 */
async function shoot(page: Page, name: string): Promise<void> {
	// The SPA renders into #content-vue; screenshotting the whole page would
	// bake Nextcloud's header/clock into every baseline.
	const content = page.locator('#content-vue, #app-content-vue, #content').first()
	await expect(content).toBeVisible()
	// NOT waitForLoadState('networkidle'): Nextcloud long-polls for
	// notifications, so the network is never idle and every capture timed out
	// at 30 s. toHaveScreenshot() already retries until two consecutive frames
	// match, which is the stabilisation this needs; the per-view assertions
	// above are what prove the right screen is loaded.
	await expect(content).toHaveScreenshot(name, {
		mask: masks(page),
		animations: 'disabled',
		maxDiffPixelRatio: 0.02,
	})
}

/**
 * Enter the app and click a top-level navigation entry.
 *
 * @param page  the Playwright page
 * @param title the nav entry's title attribute
 * @return void
 */
async function navigateTo(page: Page, title: string): Promise<void> {
	await page.goto(PLANIX_ROOT)
	await page.locator(`#app-navigation-vue a[title="${title}"]`).click()
}

test.describe('visual baselines — planix views', () => {
	test('Dashboard renders its landing view @visual', async ({ page }) => {
		await page.goto(PLANIX_ROOT)
		await expect(page).toHaveURL(/\/apps\/planix\/?$/)
		await shoot(page, 'dashboard.png')
	})

	test('ProjectList renders the projects index @visual', async ({ page }) => {
		await navigateTo(page, 'Projects')
		await expect(page).toHaveURL(/\/projects$/)
		await expect(page.locator('.project-list-item').first()).toBeVisible()
		await shoot(page, 'project-list.png')
	})

	test('ProjectBoard renders the kanban board @visual', async ({ page }) => {
		await openFixtureProjectBoard(page)
		await expect(page).toHaveURL(/\/projects\/[^/?#]+$/)
		await shoot(page, 'project-board.png')
	})

	// Backlog and Timeline are reached from NcButtons that call $router.push —
	// NOT anchors. getByRole('link') matches nothing here and the click times
	// out, which reads as "the view is broken" rather than "the selector is".
	// Same trap nav.ts records for project rows.
	test('ProjectBacklog renders the backlog @visual', async ({ page }) => {
		const id = await openFixtureProjectBoard(page)
		await page.getByRole('button', { name: /backlog/i }).first().click()
		await expect(page).toHaveURL(new RegExp(`/projects/${id}/backlog$`))
		await shoot(page, 'project-backlog.png')
	})

	test('ProjectTimeline renders the gantt @visual', async ({ page }) => {
		const id = await openFixtureProjectBoard(page)
		await page.getByRole('button', { name: /timeline/i }).first().click()
		await expect(page).toHaveURL(new RegExp(`/projects/${id}/timeline$`))
		await shoot(page, 'project-timeline.png')
	})

	test('TaskDetail renders a task @visual', async ({ page }) => {
		const id = await openFixtureProjectBoard(page)
		await page.locator('.task-card, [data-testid="task-card"]', { hasText: FIXTURE.tasks.normal })
			.first().click()
		await expect(page).toHaveURL(new RegExp(`/projects/${id}/tasks/[^/?#]+$`))
		await shoot(page, 'task-detail.png')
	})

	test('Boards renders the boards overview @visual', async ({ page }) => {
		await navigateTo(page, 'Boards')
		await expect(page).toHaveURL(/\/boards$/)
		await shoot(page, 'boards.png')
	})

	test('Portfolio renders capacity @visual', async ({ page }) => {
		await navigateTo(page, 'Portfolio')
		await expect(page).toHaveURL(/\/portfolio$/)
		await shoot(page, 'portfolio.png')
	})

	test('Timesheet renders time entries @visual', async ({ page }) => {
		await navigateTo(page, 'Timesheet')
		await expect(page).toHaveURL(/\/timesheet$/)
		await shoot(page, 'timesheet.png')
	})
})
