/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Board load with 200 tasks under the role read rules (projects-members-and-roles
 * task 2.4).
 *
 * Task 2.4 asked for the board load before and after the read rules of 2.3.
 * Those rules shipped on 2 Oct, so a "before" can no longer be taken on the same
 * code. This spec measures the same cost another way. The admin opens the board,
 * and OpenRegister skips the role rules for an admin. A project member opens the
 * same board with the role rules applied. Each is measured warm, as the median of
 * three loads from navigation start until all 200 cards are on screen. The
 * member's median must stay within 20 percent of the admin's, plus a 50 ms floor
 * for timer noise on small numbers. Both timings are written to the test's
 * annotations, so the nightly report records them.
 *
 * Written for the nightly run; not yet run against a live instance. It checks a
 * task, not a spec scenario, so it carries no scenario tag.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const TASKS = 200
const LOADS = 3

/**
 * Create `count` tasks in one column, 20 at a time.
 *
 * @param api The admin context.
 * @param project The project id.
 * @param column The column id.
 * @param count How many.
 * @return The task ids.
 */
async function seedTasks(api: APIRequestContext, project: string, column: string, count: number): Promise<string[]> {
	const ids: string[] = []
	for (let start = 0; start < count; start += 20) {
		const batch = Array.from({ length: Math.min(20, count - start) }, (_, i) => start + i)
		ids.push(...await Promise.all(batch.map((n) => createObject(api, 'task', { title: `Load ${RUN} ${n}`, project, status: 'open', column }))))
	}
	return ids
}

/**
 * The median time, in ms, from navigation start until every card is on the board, over LOADS warm loads.
 *
 * @param page The signed-in page.
 * @param url The board URL.
 */
async function boardLoad(page: Page, url: string): Promise<number> {
	const cards = page.getByTestId('task-card')
	await page.goto(url)
	await expect(cards).toHaveCount(TASKS, { timeout: 60_000 })
	const times: number[] = []
	for (let i = 0; i < LOADS; i++) {
		await page.goto(url)
		await expect(cards).toHaveCount(TASKS, { timeout: 60_000 })
		times.push(await page.evaluate(() => performance.now()))
	}
	times.sort((a, b) => a - b)
	return times[Math.floor(times.length / 2)]
}

test.describe('Board load under the role rules', () => {
	test.describe.configure({ timeout: 600_000 })

	test('a member\'s board of 200 tasks loads within 20 percent of the admin\'s', async ({ browser, page }) => {
		const api = await adminApi()
		const member = `load-${RUN}`
		const password = `Planninq-e2e-${RUN}!`
		let project = ''
		const made: Array<[string, string]> = []
		try {
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: member, password } })).ok()).toBe(true)
			project = await createObject(api, 'project', { title: `Board load ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, member] })
			const column = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', column])
			made.push(['column', await createObject(api, 'column', { title: 'Done', project, order: 1, type: 'done', status: 'done' })])
			for (const id of await seedTasks(api, project, column, TASKS)) {
				made.push(['task', id])
			}

			const url = new URL(`projects/${project}`, PLANNINQ_ROOT).toString()
			const adminMs = await boardLoad(page, url)
			const { context, page: memberPage } = await signedInPage(browser, member, password)
			let memberMs = 0
			try {
				memberMs = await boardLoad(memberPage, url)
			} finally {
				await context.close()
			}

			test.info().annotations.push(
				{ type: 'board-load-admin-ms', description: adminMs.toFixed(0) },
				{ type: 'board-load-member-ms', description: memberMs.toFixed(0) },
			)
			expect(memberMs).toBeLessThanOrEqual(adminMs * 1.2 + 50)
		} finally {
			await removeObjects(api, made)
			if (project !== '') {
				await api.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
			}
			await api.delete(`/ocs/v2.php/cloud/users/${member}`, { failOnStatusCode: false })
			await api.dispose()
		}
	})
})
