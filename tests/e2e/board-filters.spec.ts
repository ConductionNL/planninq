/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the board filter bar and saved filters (boards-filters).
 *
 *   @e2e board-filters::only-urgent-work
 *   @e2e board-filters::my-tasks
 *   @e2e board-filters::everything-not-assigned-to-me
 *   @e2e board-filters::send-the-view-to-a-colleague
 *
 * The suite signs in as the admin only, so the two saved-filter scenarios
 * that need a second member carry an exclude note in the spec; this file
 * saves, applies, renames and deletes a filter as its owner.
 */

import { expect, test } from '@playwright/test'
import { inputByTestId, PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const BRAM = `bram-${RUN}`

test.describe('Board filters', () => {
	test('filter by priority and assignee, is not, a reload and a copied link keep it, and a saved filter round-trips', async ({ page, context }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			// A task is only given to a member who exists: the server refuses anyone else (422).
			const bram = await api.post('/ocs/v2.php/cloud/users', { data: { userid: BRAM, password: `Planninq-e2e-${RUN}!` }, headers: { 'OCS-APIRequest': 'true' } })
			expect(bram.ok(), `user ${BRAM}: ${bram.status()}`).toBe(true)
			const project = await createObject(api, 'project', { title: `Filters ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, BRAM] })
			made.push(['project', project])
			const column = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', column])
			const rows: Array<[string, string, string]> = [
				['U1', 'urgent', ADMIN_USER],
				['U2', 'urgent', BRAM],
				['N1', 'normal', ADMIN_USER],
				['N2', 'normal', ADMIN_USER],
				['N3', 'normal', BRAM],
				['N4', 'normal', ''],
				['N5', 'normal', ''],
			]
			for (const [title, priority, assignedTo] of rows) {
				made.push(['task', await createObject(api, 'task', { title: `${title} ${RUN}`, project, column, columnOrder: 0, status: 'open', priority, assignedTo })])
			}

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const cards = page.getByTestId('task-card')
			await expect(cards).toHaveCount(7)

			// Only urgent work.
			await page.getByTestId('filter-priority').click()
			await page.getByRole('option', { name: 'Urgent' }).click()
			await page.keyboard.press('Escape')
			await expect(cards).toHaveCount(2)
			await expect(page.getByTestId('filter-count')).toContainText('Showing 2 of 7 tasks')
			await page.getByTestId('filter-clear').click()
			await expect(cards).toHaveCount(7)

			// My tasks, then everything not assigned to me (unassigned included).
			await page.getByTestId('filter-assignee').click()
			await page.getByRole('option', { name: 'Me' }).click()
			await page.keyboard.press('Escape')
			await expect(cards).toHaveCount(3)
			await page.getByTestId('filter-assignee-not').click()
			await expect(cards).toHaveCount(4)

			// A reload and a copied link open the same view.
			await page.reload()
			await expect(page.getByTestId('task-card')).toHaveCount(4)
			const colleague = await context.newPage()
			await colleague.goto(page.url())
			await expect(colleague.getByTestId('task-card')).toHaveCount(4)
			await colleague.close()

			// Save it, clear, apply it again, then rename and delete it.
			await page.getByTestId('saved-filters').click()
			await page.getByTestId('save-filter').click()
			await inputByTestId(page, 'saved-filter-name').fill(`Not mine ${RUN}`)
			await page.getByTestId('saved-filter-save').click()
			await page.getByTestId('filter-clear').click()
			await expect(cards).toHaveCount(7)
			await page.getByTestId('saved-filters').click()
			await page.getByRole('menuitem', { name: `Not mine ${RUN}`, exact: true }).click()
			await expect(cards).toHaveCount(4)
			await page.getByTestId('saved-filters').click()
			await page.getByRole('menuitem', { name: `Delete "Not mine ${RUN}"` }).click()
			await page.getByTestId('saved-filters').click()
			await expect(page.getByRole('menuitem', { name: `Not mine ${RUN}`, exact: true })).toHaveCount(0)
		} finally {
			await removeObjects(api, made)
			await api.delete(`/ocs/v2.php/cloud/users/${BRAM}`, { headers: { 'OCS-APIRequest': 'true' }, failOnStatusCode: false })
		}
	})
})
