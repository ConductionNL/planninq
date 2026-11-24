/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Live check for platform-baseline task 4.2 (REQ-PLB-002, REQ-PLB-003).
 *
 * Theme: when thematiq is enabled, the primary buttons on the dashboard and a
 * project board must paint with the theme's `--color-primary-element`, the
 * variable thematiq writes the configured design tokens into. The test
 * compares each button's computed background with a probe element styled with
 * that variable, so it holds for any colour the instance is configured with.
 * It skips on an instance without thematiq.
 *
 * Right to left: a fresh user whose Nextcloud language is Arabic opens a
 * project timeline. The document runs right to left, and a task that starts
 * three days from now is drawn entirely to the LEFT of the today marker,
 * because time runs from right to left.
 *
 * Written for the nightly run; not yet run against a live instance.
 *
 *   @e2e platform-baseline::a-municipality-applies-its-house-style
 *   @e2e platform-baseline::a-user-with-arabic-as-their-language-opens-the-timeline
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { signedInPage } from './fresh-user.ts'
import { openFixtureProjectBoard, PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'
import { userWithTasks } from './time-fixtures.ts'

const RUN = Date.now().toString(36)

/**
 * Whether an app is enabled on the instance.
 *
 * @param admin The admin context.
 * @param app The app id.
 */
async function appEnabled(admin: APIRequestContext, app: string): Promise<boolean> {
	const res = await admin.get('/ocs/v2.php/cloud/apps?filter=enabled&format=json', { failOnStatusCode: false })
	const apps = (await res.json())?.ocs?.data?.apps ?? []
	return Array.isArray(apps) && apps.includes(app)
}

/**
 * The computed backgrounds of the visible primary buttons in planninq's content, next to the
 * computed colour of `var(--color-primary-element)` on the same page.
 *
 * @param page The page.
 */
async function primaryColours(page: Page): Promise<{ theme: string, buttons: string[] }> {
	return page.evaluate(() => {
		const probe = document.createElement('div')
		probe.style.backgroundColor = 'var(--color-primary-element)'
		document.body.appendChild(probe)
		const theme = getComputedStyle(probe).backgroundColor
		probe.remove()
		const root = document.querySelector('#content-vue, #app-content-vue, #content') ?? document.body
		const buttons = [...root.querySelectorAll<HTMLElement>('.button-vue--vue-primary, .button-vue--primary')]
			.filter((el) => el.offsetParent !== null)
			.map((el) => getComputedStyle(el).backgroundColor)
		return { theme, buttons }
	})
}

/**
 * A date `days` from today, as YYYY-MM-DD.
 *
 * @param days The offset in days.
 */
function isoDay(days: number): string {
	const date = new Date()
	date.setUTCDate(date.getUTCDate() + days)
	return date.toISOString().slice(0, 10)
}

test.describe('Platform baseline: theme and right to left', () => {
	test.describe.configure({ timeout: 120_000 })

	test('with thematiq on, the dashboard and a board take the theme\'s colours', async ({ page }) => {
		const admin = await adminApi()
		try {
			test.skip(!(await appEnabled(admin, 'thematiq')), 'thematiq is not enabled on this instance')
		} finally {
			await admin.dispose()
		}

		await page.goto(PLANNINQ_ROOT)
		await expect(page.locator('#content-vue, #app-content-vue, #content').first()).toBeVisible({ timeout: 30_000 })
		const dashboard = await primaryColours(page)
		expect(dashboard.theme).not.toBe('')
		for (const colour of dashboard.buttons) {
			expect(colour).toBe(dashboard.theme)
		}

		await openFixtureProjectBoard(page)
		const board = await primaryColours(page)
		expect(board.buttons.length, 'a board shows at least one primary action').toBeGreaterThan(0)
		for (const colour of board.buttons) {
			expect(colour).toBe(board.theme)
		}
	})

	test('a user with Arabic as their language sees the timeline run right to left', async ({ browser }) => {
		const admin = await adminApi()
		const user = `rtl-${RUN}`
		const password = `Planninq-e2e-${RUN}!`
		let project = ''
		try {
			const made = await userWithTasks(admin, user, password, `RTL ${RUN}`, [`Later task ${RUN}`])
			project = made.project
			const language = await admin.put(`/ocs/v2.php/cloud/users/${user}`, { data: { key: 'language', value: 'ar' } })
			expect(language.ok(), `language: ${language.status()}`).toBe(true)
			const dated = await admin.patch(`${OBJECTS}/task/${made.tasks[0]}`, { data: { startDate: isoDay(3), dueDate: isoDay(6) } })
			expect(dated.ok(), `dates: ${dated.status()}`).toBe(true)

			const { context, page } = await signedInPage(browser, user, password)
			try {
				await page.goto(new URL(`projects/${project}/timeline`, PLANNINQ_ROOT).toString())
				const bar = page.getByTestId('timeline-bar').first()
				await expect(bar).toBeVisible({ timeout: 30_000 })
				const today = page.locator('.project-timeline__today')
				await expect(today).toBeVisible()

				const direction = await page.evaluate(() => getComputedStyle(document.querySelector('.project-timeline__bars') ?? document.body).direction)
				expect(direction).toBe('rtl')

				const barBox = await bar.boundingBox()
				const todayBox = await today.boundingBox()
				expect(barBox).not.toBeNull()
				expect(todayBox).not.toBeNull()
				// Later dates sit further left: the whole bar ends before the today marker starts.
				expect((barBox as { x: number, width: number }).x + (barBox as { x: number, width: number }).width)
					.toBeLessThanOrEqual((todayBox as { x: number }).x + 1)
			} finally {
				await context.close()
			}
		} finally {
			if (project !== '') {
				await admin.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
			}
			await admin.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
			await admin.dispose()
		}
	})
})
