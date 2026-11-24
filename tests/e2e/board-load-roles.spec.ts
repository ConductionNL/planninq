/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Board load with 200 tasks, with and without the project-scoped read rules
 * (projects-members-and-roles, task 2.4).
 *
 *   @spec openspec/changes/archive/2026-11-24-projects-members-and-roles/tasks.md#task-2.4
 *
 * "Before 2.3" can no longer be checked out on a live stack: the read rules are
 * in the register. The admin is the stand-in for it, because OpenRegister lets
 * the admin read without evaluating the rules; a plain member walks every rule
 * (`members $contains $userId` on task, column and projectPhase). Each side is
 * the median of three warm loads, both timings are written to the test's
 * annotations (and the log) for the PR body, and the member's must be within
 * 20 percent of the admin's.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const TASKS = 200
const LOADS = 3

/**
 * Median milliseconds from navigation until the board shows its column with
 * all tasks counted, over `LOADS` warm loads (one unmeasured load first).
 *
 * @param page A signed-in page.
 * @param url The board's URL.
 * @return The median load time in milliseconds.
 */
async function medianBoardLoad(page: Page, url: string): Promise<number> {
	const load = async (): Promise<number> => {
		const start = Date.now()
		await page.goto(url)
		await expect(page.getByTestId('column-count').first()).toContainText(String(TASKS), { timeout: 60_000 })
		await expect(page.getByTestId('task-card').first()).toBeVisible()
		return Date.now() - start
	}
	await load()
	const times: number[] = []
	for (let i = 0; i < LOADS; i++) {
		times.push(await load())
	}
	times.sort((a, b) => a - b)
	return times[Math.floor(times.length / 2)]
}

test.describe('Board load with the read rules', () => {
	test('a member\'s board with 200 tasks loads within 20 percent of the unrestricted load', async ({ browser, page }) => {
		test.setTimeout(10 * 60_000)
		const api = await adminApi()
		const member = `meet-${RUN}`
		const made: Array<[string, string]> = []
		try {
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: member, password: PASSWORD } })).ok()).toBe(true)
			const project = await createObject(api, 'project', {
				title: `Meting ${RUN}`,
				status: 'active',
				owner: ADMIN_USER,
				members: [ADMIN_USER, member],
			})
			made.push(['project', project])
			const column = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', column])
			for (let i = 0; i < TASKS; i++) {
				made.push(['task', await createObject(api, 'task', { title: `Taak ${i} ${RUN}`, project, column, columnOrder: i, status: 'open' })])
			}

			const url = new URL(`projects/${project}`, PLANNINQ_ROOT).toString()
			const unrestricted = await medianBoardLoad(page, url)

			const m = await signedInPage(browser, member, PASSWORD)
			const withRules = await medianBoardLoad(m.page, url)
			await m.context.close()

			const ratio = withRules / unrestricted
			test.info().annotations.push(
				{ type: 'board-load-unrestricted-ms', description: String(unrestricted) },
				{ type: 'board-load-with-rules-ms', description: String(withRules) },
				{ type: 'board-load-ratio', description: ratio.toFixed(2) },
			)
			console.log(`board load, ${TASKS} tasks: admin ${unrestricted} ms, member ${withRules} ms, ratio ${ratio.toFixed(2)}`)
			expect(withRules, `member ${withRules} ms vs admin ${unrestricted} ms`).toBeLessThanOrEqual(unrestricted * 1.2)
		} finally {
			await removeObjects(api, made)
			await api.delete(`/ocs/v2.php/cloud/users/${member}`, { failOnStatusCode: false })
			await api.dispose()
		}
	})
})
