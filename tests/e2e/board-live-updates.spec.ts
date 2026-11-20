/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for live updates on the board (board-live-updates 1.3): a card
 * a colleague moves in another browser moves on an open board without a
 * reload. Without notify_push the board falls back to polling the task
 * collection every 30 seconds, so the wait allows one full poll.
 *
 *   @e2e realtime-updates::a-colleague-moves-a-card
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Board live updates', () => {
	test.describe.configure({ timeout: 180_000 })

	test('a colleague\'s move shows on the board without a reload', async ({ browser, page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Live ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const todo = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', todo])
			const doing = await createObject(api, 'column', { title: 'Doing', project, order: 1, status: 'in_progress' })
			made.push(['column', doing])
			const title = `Live card ${RUN}`
			made.push(['task', await createObject(api, 'task', { title, project, column: todo, columnOrder: 0, status: 'open' })])

			const boardUrl = new URL(`projects/${project}`, PLANNINQ_ROOT).toString()
			const inColumn = (target, column: string) => target.locator(`section.kanban-column[data-column="${column}"]`).locator(`[data-testid="task-card"][aria-label="${title}"]`)
			await page.goto(boardUrl)
			await expect(inColumn(page, 'To do')).toHaveCount(1, { timeout: 30_000 })
			let navigations = 0
			page.on('framenavigated', (frame) => {
				if (frame === page.mainFrame()) {
					navigations++
				}
			})

			const colleague = await browser.newContext({ storageState: 'tests/e2e/.auth/admin.json' })
			const other = await colleague.newPage()
			await other.goto(boardUrl)
			await expect(inColumn(other, 'To do')).toHaveCount(1, { timeout: 30_000 })
			await inColumn(other, 'To do').getByRole('button', { name: 'Move task to another column' }).click()
			await other.getByRole('menuitem', { name: 'Doing' }).click()
			await expect(inColumn(other, 'Doing')).toHaveCount(1)
			await colleague.close()

			await expect(inColumn(page, 'Doing')).toHaveCount(1, { timeout: 75_000 })
			await expect(inColumn(page, 'To do')).toHaveCount(0)
			expect(navigations).toBe(0)
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
