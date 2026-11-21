/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the Per project chart of the Time spent report
 * (time-timer-and-work-type 2.5).
 *
 *   @e2e built-in-reports::minutes-per-project
 *
 * The hours live once, on humaniq's TimeEntry (plannedtimeentry-reads-humaniqs-hours),
 * so the chart sums humaniq's `hours` per project and hides without humaniq;
 * the suite skips there. A bar of 90 minutes therefore reads 1.5.
 *
 * Every page runs as a regular user the spec creates, in a context of its own
 * with an empty storage state; the admin only creates and removes that user.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'
import { humaniqEnabled, idOf, JSON_HEADERS, removeUserWork, TIME_ENTRIES, userApi, userWithTasks } from './time-fixtures.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const MARKT = `Herinrichting Marktplein ${RUN}`
const BALIE = `Digitale balie ${RUN}`

/**
 * Book hours on a task in humaniq, as planninq's Log time does.
 *
 * @param api The user's context.
 * @param booking The booking.
 * @param booking.user The user id.
 * @param booking.project The project id.
 * @param booking.task The task id.
 * @param booking.hours The hours.
 * @return the TimeEntry id
 */
async function bookHours(api: APIRequestContext, booking: { user: string, project: string, task: string, hours: number }): Promise<string> {
	const res = await api.post(TIME_ENTRIES, {
		data: { date: '2026-10-07', hours: booking.hours, userId: booking.user, projectId: booking.project, domainObjectType: 'planninq:task', domainObjectRef: booking.task },
		headers: JSON_HEADERS,
	})
	expect(res.ok(), `TimeEntry: ${res.status()}`).toBe(true)
	return idOf(res)
}

test.describe('Time spent report', () => {
	test('the Per project chart splits the hours per project and opens the Timesheet', async ({ browser }) => {
		test.setTimeout(120_000)
		const admin = await adminApi()
		const user = `rapport-${RUN}`
		const markt: { project?: string, tasks?: string[] } = {}
		let balie = ''
		let balieTask = ''
		const entries: string[] = []
		let api: APIRequestContext | null = null
		try {
			test.skip(!(await humaniqEnabled(admin)), 'the Per project chart reads humaniq, which is not enabled here')
			Object.assign(markt, await userWithTasks(admin, user, PASSWORD, MARKT, [`Bestek ${RUN}`]))
			api = await userApi(user, PASSWORD)
			const created = await api.post('/index.php/apps/planninq/api/projects', { data: { title: BALIE }, headers: JSON_HEADERS })
			expect(created.status()).toBe(201)
			balie = String((await created.json()).id)
			const t = await api.post(`${OBJECTS}/task`, { data: { title: `Formulieren ${RUN}`, status: 'open', project: balie, assignedTo: user }, headers: JSON_HEADERS })
			expect(t.ok()).toBe(true)
			balieTask = await idOf(t)

			// GIVEN 90 minutes on one project and 30 on the other.
			entries.push(await bookHours(api, { user, project: markt.project as string, task: (markt.tasks as string[])[0], hours: 1.5 }))
			entries.push(await bookHours(api, { user, project: balie, task: balieTask, hours: 0.5 }))

			// WHEN the user opens the report, THEN one bar per project.
			const { context, page } = await signedInPage(browser, user, PASSWORD)
			await page.goto(new URL('reports/time', PLANNINQ_ROOT).toString())
			const chart = page.locator('.cn-widget-wrapper').filter({ hasText: 'Per project' })
			await expect(chart).toHaveCount(1)
			await expect(chart).toContainText(MARKT)
			await expect(chart).toContainText(BALIE)
			const marktBar = chart.locator('.apexcharts-bar-area[val="1.5"]')
			await expect(marktBar).toHaveCount(1)
			await expect(chart.locator('.apexcharts-bar-area[val="0.5"]')).toHaveCount(1)

			// AND choosing a bar opens the Timesheet filtered to that project.
			await marktBar.click()
			await expect(page).toHaveURL(new RegExp(`/timesheet\\?(.*&)?project=${markt.project}(&|$)`))
			await context.close()
		} finally {
			for (const id of entries) {
				await admin.delete(`${TIME_ENTRIES}/${id}`, { failOnStatusCode: false })
			}
			await api?.dispose()
			if (balieTask) {
				await admin.delete(`${OBJECTS}/task/${balieTask}`, { failOnStatusCode: false })
			}
			if (balie) {
				await admin.delete(`${OBJECTS}/project/${balie}`, { failOnStatusCode: false })
			}
			await removeUserWork(admin, user, markt)
			await admin.dispose()
		}
	})
})
