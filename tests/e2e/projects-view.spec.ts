/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for cross-project views (boards-cross-project-board).
 *
 *   @e2e cross-project-view::the-view-shows-tasks-of-two-projects-with-project-chips
 *   @e2e cross-project-view::moving-a-card-places-it-in-its-projects-column
 *   @e2e cross-project-view::the-keyboard-move-works-on-the-view
 *   @e2e cross-project-view::a-project-without-a-matching-column-refuses-the-move
 *   @e2e cross-project-view::a-viewer-outside-a-project-sees-the-hidden-projects-notice
 *
 * The suite signs in as the admin only, and an admin reads every project, so
 * the hidden-projects notice is reached here through a project id that no
 * longer exists (OpenRegister answers both with the same 404).
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Cross-project views', () => {
	test('a view shows two projects with chips, moves through each project\'s columns and refuses a lane a project lacks', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const servers = await createObject(api, 'project', { title: `Servers ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', servers])
			const workplace = await createObject(api, 'project', { title: `Workplace ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', workplace])

			const todo = await createObject(api, 'column', { title: 'To do', project: servers, order: 0, status: 'open' })
			const doing = await createObject(api, 'column', { title: 'Doing', project: servers, order: 1, status: 'in_progress' })
			const done = await createObject(api, 'column', { title: 'Done', project: servers, order: 2, status: 'done', type: 'done' })
			const wOpen = await createObject(api, 'column', { title: 'Open', project: workplace, order: 0, status: 'open' })
			made.push(['column', todo], ['column', doing], ['column', done], ['column', wOpen])

			const patch = await createObject(api, 'task', { title: `Patch mail server ${RUN}`, project: servers, column: todo, columnOrder: 1000, status: 'open' })
			const laptops = await createObject(api, 'task', { title: `Order laptops ${RUN}`, project: workplace, column: wOpen, columnOrder: 1000, status: 'open' })
			made.push(['task', patch], ['task', laptops])

			const view = await createObject(api, 'boardView', { title: `IT operations ${RUN}`, members: [], projects: [servers, workplace, '00000000-0000-4000-8000-00000000dead'] })
			made.push(['boardView', view])

			await page.goto(new URL(`boards/views/${view}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('view-title')).toHaveText(`IT operations ${RUN}`)
			await expect(page.getByTestId('hidden-projects')).toContainText('1 project in this view is hidden from you.')

			// Both projects' open tasks in the Open lane, each naming its project.
			const openLane = page.locator('[data-status="open"]')
			await expect(openLane.getByTestId('task-card')).toHaveCount(2)
			await expect(openLane.getByTestId('task-card').filter({ hasText: `Patch mail server ${RUN}` }).getByTestId('task-card-project')).toContainText(`Servers ${RUN}`)
			await expect(openLane.getByTestId('task-card').filter({ hasText: `Order laptops ${RUN}` }).getByTestId('task-card-project')).toContainText(`Workplace ${RUN}`)

			// Keyboard move: In progress lands in Servers' Doing column.
			const card = openLane.getByTestId('task-card').filter({ hasText: `Patch mail server ${RUN}` })
			await card.getByRole('button', { name: 'Move task to another column' }).click()
			await page.getByTestId('move-to-in_progress').click()
			await expect(page.locator('[data-status="in_progress"]').getByTestId('task-card')).toHaveCount(1)
			const moved = await (await api.get(`${OBJECTS}/task/${patch}`)).json()
			expect(moved.column).toBe(doing)
			expect(moved.status).toBe('in_progress')

			// Workplace has no Blocked column: the card stays and the view says so.
			const other = openLane.getByTestId('task-card').filter({ hasText: `Order laptops ${RUN}` })
			await other.getByRole('button', { name: 'Move task to another column' }).click()
			await page.getByTestId('move-to-blocked').click()
			await expect(page.getByTestId('view-announcement')).toHaveText(`Workplace ${RUN} has no column for Blocked.`)
			await expect(openLane.getByTestId('task-card')).toHaveCount(1)
			const unchanged = await (await api.get(`${OBJECTS}/task/${laptops}`)).json()
			expect(unchanged.status).toBe('open')
			expect(unchanged.column).toBe(wOpen)
		} finally {
			await removeObjects(api, made)
		}
	})
})
