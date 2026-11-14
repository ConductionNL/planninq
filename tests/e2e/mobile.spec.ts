/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Phone smoke suite (platform-mobile-web). Runs only in the `phone-android`
 * and `phone-ios` projects of playwright.config.ts, at a phone's width with
 * touch, and taps through the daily flows: My tasks, a task page, logging
 * time, the timesheet and the one-column board.
 *
 *   @e2e mobile-web::my-tasks-on-a-phone-opens-a-task
 *   @e2e mobile-web::log-time-on-a-phone
 *   @e2e mobile-web::the-timesheet-shows-a-day-list-on-a-phone
 *   @e2e mobile-web::the-phone-board-shows-one-column-and-switches
 *   @e2e mobile-web::a-card-moves-by-its-menu-on-a-phone
 *   @e2e mobile-web::a-sideways-scroll-fails-the-suite
 *
 * Two checks run on every page of the flows: the page does not scroll
 * sideways (the board's own column scroller aside), and the flow's buttons
 * and links are at least 44 by 44 CSS pixels.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const MIN_TARGET = 44

/**
 * How many CSS pixels the page is wider than the screen; 0 when it fits.
 *
 * @param page The page
 * @return The overflow in pixels
 */
async function sidewaysOverflow(page: Page): Promise<number> {
	return page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - window.innerWidth))
}

/**
 * The visible targets matching `selector` that are smaller than 44 by 44.
 *
 * @param page The page
 * @param selector Which buttons and links to measure
 * @return A description of each target that is too small
 */
async function smallTargets(page: Page, selector: string): Promise<string[]> {
	return page.locator(selector).evaluateAll((nodes, min) => nodes
		.map((node) => ({ node, box: node.getBoundingClientRect() }))
		.filter(({ box }) => box.width > 0 && box.height > 0)
		.filter(({ box }) => box.width < min || box.height < min)
		.map(({ node, box }) => `${(node.getAttribute('aria-label') || node.textContent || node.tagName).trim().slice(0, 40)} ${Math.round(box.width)}x${Math.round(box.height)}`), MIN_TARGET)
}

/**
 * Assert the page fits the screen and the given targets are big enough.
 *
 * @param page The page
 * @param selector Which buttons and links to measure
 */
async function expectPhoneFit(page: Page, selector: string): Promise<void> {
	expect(await sidewaysOverflow(page), 'the page scrolls sideways').toBe(0)
	expect(await smallTargets(page, selector), 'targets under 44 by 44 pixels').toEqual([])
}

test.describe('Phone', () => {
	// @e2e mobile-web::a-sideways-scroll-fails-the-suite
	// Negative control: skipped by default, run once when the helper was written
	// (PHONE_CONTROL=1 npx playwright test --project=phone-android mobile.spec.ts).
	test('the sideways check fails on a page with an 800 pixel wide element', async ({ page }) => {
		test.skip(!process.env.PHONE_CONTROL, 'negative control, run on demand')
		await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
		await page.evaluate(() => {
			const wide = document.createElement('div')
			wide.style.width = '800px'
			wide.style.height = '10px'
			document.body.appendChild(wide)
		})
		expect(await sidewaysOverflow(page)).toBeGreaterThan(0)
	})

	test('my tasks, a task page, logging time and the timesheet by tapping', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Phone ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const task = await createObject(api, 'task', { title: `Call the supplier ${RUN}`, status: 'open', project, assignedTo: ADMIN_USER })
			made.push(['task', task])

			// @e2e mobile-web::my-tasks-on-a-phone-opens-a-task
			await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
			const title = page.getByTestId('my-work-title').filter({ hasText: `Call the supplier ${RUN}` })
			await expect(title).toBeVisible({ timeout: 30_000 })
			await expectPhoneFit(page, '[data-testid="my-work-title"]')
			await title.tap()
			await expect(page).toHaveURL(new RegExp(`/tasks/${task}`))
			await expect(page.getByTestId('log-time')).toBeVisible()
			await expectPhoneFit(page, '.task-detail__main button, .task-detail__main a')
			await expect(page.getByTestId('task-edit')).toBeVisible()

			// @e2e mobile-web::log-time-on-a-phone
			await page.getByTestId('log-time').tap()
			await page.getByTestId('time-entry-duration').locator('input').fill('30m')
			await expectPhoneFit(page, '[data-testid="time-entry-save"], [data-testid="time-entry-cancel"]')
			await page.getByTestId('time-entry-save').tap()
			await expect(page.getByTestId('time-entry-duration')).toHaveCount(0)

			// @e2e mobile-web::the-timesheet-shows-a-day-list-on-a-phone
			await page.goto(new URL('timesheet', PLANNINQ_ROOT).toString())
			const row = page.getByTestId('timesheet-row').filter({ hasText: `Call the supplier ${RUN}` })
			await expect(row).toBeVisible({ timeout: 30_000 })
			await expect(row).toContainText('30')
			await expect(page.getByTestId('timesheet-total')).toBeVisible()
			await expectPhoneFit(page, '[data-testid="timesheet-row"] a')
		} finally {
			await removeObjects(api, made)
		}
	})

	test('the board shows one column, switches, and moves a card by its menu', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Phone board ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const todo = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', todo])
			const doing = await createObject(api, 'column', { title: 'In progress', project, order: 1, status: 'in_progress' })
			made.push(['column', doing])
			const moved = await createObject(api, 'task', { title: `Order paper ${RUN}`, project, column: todo, columnOrder: 0, status: 'open' })
			made.push(['task', moved])
			for (let i = 0; i < 3; i++) {
				made.push(['task', await createObject(api, 'task', { title: `Busy ${i} ${RUN}`, project, column: doing, columnOrder: i, status: 'in_progress' })])
			}

			// @e2e mobile-web::the-phone-board-shows-one-column-and-switches
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const switcher = page.getByTestId('phone-column-switcher')
			await expect(switcher).toBeVisible({ timeout: 30_000 })
			await expect(page.locator('section.kanban-column[data-column="To do"]')).toBeVisible()
			await expect(page.locator('section.kanban-column[data-column="In progress"]')).toBeHidden()
			await expectPhoneFit(page, '[data-testid="phone-column-button"]')
			const inProgress = switcher.getByRole('button', { name: 'In progress (3)' })
			await inProgress.tap()
			await expect(inProgress).toHaveAttribute('aria-pressed', 'true')
			await expect(page.locator('section.kanban-column[data-column="In progress"]').getByTestId('task-card')).toHaveCount(3)
			await expect(page.locator('section.kanban-column[data-column="To do"]')).toBeHidden()

			// @e2e mobile-web::a-card-moves-by-its-menu-on-a-phone
			await switcher.getByRole('button', { name: 'To do (1)' }).tap()
			const card = page.getByTestId('task-card').filter({ hasText: `Order paper ${RUN}` })
			await card.getByRole('button', { name: 'Move task to another column' }).tap()
			await page.getByRole('menuitem', { name: 'In progress' }).tap()
			await expect(switcher.getByRole('button', { name: 'In progress (4)' })).toBeVisible()
			await expect.poll(async () => {
				const response = await api.get(`${OBJECTS}/task/${moved}`)
				return (await response.json()).status
			}).toBe('in_progress')
		} finally {
			await removeObjects(api, made)
		}
	})
})
