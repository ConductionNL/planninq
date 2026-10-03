/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for card colours and swimlanes on the board (boards-card-display).
 *
 *   @e2e board-view-options::colour-by-label
 *   @e2e board-view-options::swimlanes-by-assignee
 *   @e2e board-view-options::hand-a-task-to-a-colleague
 *   @e2e board-view-options::come-back-to-a-grouped-board
 *
 * The suite signs in as the admin only, so "Bram's view is unchanged" is proven
 * by BoardViewPreferenceServiceTest::testViewIsKeptPerPersonAndProject.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Card colours and swimlanes', () => {
	test('member colours cards by label, groups by assignee, hands a task over and comes back to the same view', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Vergunningen ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const column = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', column])
			const label = await createObject(api, 'label', { title: `Juridisch ${RUN}`, color: '#c00000' })
			made.push(['label', label])
			for (const [title, assignedTo, labels] of [['A', ADMIN_USER, [label]], ['B', ADMIN_USER, []], ['C', '', []]] as Array<[string, string, string[]]>) {
				made.push(['task', await createObject(api, 'task', { title: `${title} ${RUN}`, project, column, columnOrder: 0, status: 'open', assignedTo, labels })])
			}

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const card = page.getByTestId('task-card').filter({ hasText: `A ${RUN}` })
			await expect(card).toBeVisible()

			// Colour by label: a coloured edge, and the label chip keeps its name.
			await page.getByTestId('board-view-menu').click()
			await page.getByTestId('board-colour-label').click()
			await expect(card.locator('.task-card')).toHaveAttribute('data-edge', '#c00000')
			await expect(card).toContainText(`Juridisch ${RUN}`)

			// Swimlanes by assignee: a row per person and a no-assignee row, each with its count.
			await page.getByTestId('board-view-menu').click()
			await page.getByTestId('board-group-assignee').click()
			const rows = page.getByTestId('swimlane')
			await expect(rows).toHaveCount(2)
			await expect(rows.nth(0).getByTestId('swimlane-toggle')).toContainText('(2)')
			await expect(rows.nth(1).getByTestId('swimlane-toggle')).toContainText('No assignee (1)')

			// Collapse a row: its cards are gone, its header stays.
			await rows.nth(1).getByTestId('swimlane-toggle').click()
			await expect(rows.nth(1).getByTestId('task-card')).toHaveCount(0)
			await rows.nth(1).getByTestId('swimlane-toggle').click()

			// Hand a task over: drag C from the no-assignee row into the admin's row, same lane.
			const target = rows.nth(0).locator(`section[data-column-id="${column}"]`)
			await rows.nth(1).getByTestId('task-card').filter({ hasText: `C ${RUN}` }).dragTo(target)
			await expect(rows.nth(0).getByTestId('task-card').filter({ hasText: `C ${RUN}` })).toBeVisible()
			await expect(page.getByTestId('swimlane')).toHaveCount(1)

			// Come back: the grouping and colouring are remembered.
			await page.reload()
			await expect(page.getByTestId('swimlane')).toHaveCount(1)
			await expect(card.locator('.task-card')).toHaveAttribute('data-edge', '#c00000')

			// Put the admin's view back for the other suites.
			await page.getByTestId('board-view-menu').click()
			await page.getByTestId('board-group-none').click()
			await page.getByTestId('board-view-menu').click()
			await page.getByTestId('board-colour-none').click()
		} finally {
			await removeObjects(api, made)
		}
	})
})
