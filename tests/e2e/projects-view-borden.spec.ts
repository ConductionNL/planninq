/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for saving a cross-project view on the Borden page
 * (boards-cross-project-board).
 *
 *   @e2e cross-project-view::a-user-saves-a-view-of-two-projects
 *   @e2e cross-project-view::the-project-picker-offers-only-member-projects
 *
 * The suite signs in as the admin only. Sharing with a second person and a
 * viewer outside a project need a second account and are covered by the
 * Newman folder "Cross-project Views" and PHPUnit (see the spec's notes).
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Cross-project views on the Borden page', () => {
	test('save a view of two member projects; a project the user is not in is not offered', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const servers = await createObject(api, 'project', { title: `Servers ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			const workplace = await createObject(api, 'project', { title: `Workplace ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			const finance = await createObject(api, 'project', { title: `Finance ${RUN}`, status: 'active', owner: `dora-${RUN}`, members: [`dora-${RUN}`] })
			made.push(['project', servers], ['project', workplace], ['project', finance])

			await page.goto(new URL('boards', PLANNINQ_ROOT).toString())
			await page.getByTestId('new-view').click()
			await page.getByTestId('view-name').locator('input').fill(`IT operations ${RUN}`)

			await page.getByTestId('view-projects').click()
			await expect(page.getByRole('option', { name: `Servers ${RUN}` })).toBeVisible()
			await expect(page.getByRole('option', { name: `Finance ${RUN}` })).toHaveCount(0)
			await page.getByRole('option', { name: `Servers ${RUN}` }).click()
			await page.getByTestId('view-projects').click()
			await page.getByRole('option', { name: `Workplace ${RUN}` }).click()
			await page.keyboard.press('Escape')
			await page.getByTestId('view-save').click()

			await expect(page).toHaveURL(/\/boards\/views\/[^/?#]+$/)
			const id = page.url().split('/').pop() as string
			made.push(['boardView', id])
			await expect(page.getByTestId('view-title')).toHaveText(`IT operations ${RUN}`)

			const saved = await (await api.get(`${OBJECTS}/boardView/${id}`)).json()
			expect(saved.owner).toBe(ADMIN_USER)
			expect([...saved.projects].sort()).toEqual([servers, workplace].sort())

			await page.goto(new URL('boards', PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('cross-project-views').getByTestId('view-card').filter({ hasText: `IT operations ${RUN}` })).toBeVisible()
		} finally {
			await removeObjects(api, made)
		}
	})
})
