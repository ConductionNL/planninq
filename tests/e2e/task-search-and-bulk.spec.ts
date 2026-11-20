/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for searching a board and the backlog, and for bulk changes on
 * the backlog (tasks-search-and-bulk).
 *
 *   @e2e task-search-and-bulk::search-the-board
 *   @e2e task-search-and-bulk::clear-the-search
 *   @e2e task-search-and-bulk::bulk-status-change
 *   @e2e task-search-and-bulk::one-task-fails
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { inputByTestId, PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

/**
 * A task's stored field.
 *
 * @param api The admin context.
 * @param id The task id.
 * @param field The property to read.
 */
async function field(api: APIRequestContext, id: string, field: string): Promise<unknown> {
	return (await (await api.get(`${OBJECTS}/task/${id}`)).json())[field]
}

test.describe('Task search and bulk changes', () => {
	// Each test seeds a project through the API before it opens a page.
	test.describe.configure({ timeout: 120_000 })

	test('searching the board narrows the cards and the counts, and Escape clears it', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Search ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const todo = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', todo])
			const doing = await createObject(api, 'column', { title: 'Doing', project, order: 1, status: 'in_progress' })
			made.push(['column', doing])
			for (const [title, column] of [['Draft the permit letter', todo], ['Call the applicant', todo], ['Check the permit fee', doing], ['Book the room', doing]]) {
				made.push(['task', await createObject(api, 'task', { title: `${title} ${RUN}`, project, column, columnOrder: 0, status: column === todo ? 'open' : 'in_progress' })])
			}

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const cards = page.getByTestId('task-card')
			await expect(cards).toHaveCount(4, { timeout: 30_000 })

			const search = inputByTestId(page, 'board-search')
			await search.fill('PERMIT')
			await expect(cards).toHaveCount(2)
			await expect(page.locator(`[data-testid="task-card"][aria-label="Draft the permit letter ${RUN}"]`)).toHaveCount(1)
			await expect(page.locator(`[data-testid="task-card"][aria-label="Call the applicant ${RUN}"]`)).toHaveCount(0)
			const counts = page.getByTestId('column-count')
			await expect(counts.nth(0)).toContainText('1')
			await expect(counts.nth(1)).toContainText('1')

			await search.press('Escape')
			await expect(search).toHaveValue('')
			await expect(cards).toHaveCount(4)
			await expect(counts.nth(0)).toContainText('2')
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('the backlog search narrows the rows and a bulk status change updates three tasks', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Bulk ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const ids: string[] = []
			for (const title of ['Permit A', 'Permit B', 'Permit C', 'Agenda D']) {
				const id = await createObject(api, 'task', { title: `${title} ${RUN}`, project, status: 'open' })
				made.push(['task', id])
				ids.push(id)
			}

			await page.goto(new URL(`projects/${project}/backlog`, PLANNINQ_ROOT).toString())
			const rows = page.getByTestId('backlog-row')
			await expect(rows).toHaveCount(4, { timeout: 30_000 })

			const search = inputByTestId(page, 'backlog-search')
			await search.fill('permit')
			await expect(rows).toHaveCount(3)
			await expect(page.locator(`[data-testid="backlog-row"][data-title="Agenda D ${RUN}"]`)).toHaveCount(0)

			await page.getByText('Select all', { exact: true }).click()
			await expect(page.getByTestId('backlog-bulk-bar')).toContainText('Selected: 3')
			await page.getByTestId('bulk-action').click()
			await page.getByRole('option').filter({ hasText: /^\s*Status\s*$/ }).click()
			await page.getByTestId('bulk-value').click()
			await page.getByRole('option').filter({ hasText: /^\s*In progress\s*$/ }).click()
			await page.getByTestId('bulk-apply').click()
			await expect(page.getByText('Tasks updated: 3', { exact: true })).toBeVisible()

			for (const id of ids.slice(0, 3)) {
				await expect.poll(() => field(api, id, 'status'), { timeout: 15_000 }).toBe('in_progress')
			}
			expect(await field(api, ids[3], 'status')).toBe('open')

			await search.press('Escape')
			await expect(rows).toHaveCount(4)
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('a task deleted meanwhile fails the bulk change and stays selected', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Bulk fail ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const ids: string[] = []
			for (const title of ['One', 'Two', 'Three']) {
				const id = await createObject(api, 'task', { title: `${title} ${RUN}`, project, status: 'open' })
				made.push(['task', id])
				ids.push(id)
			}

			await page.goto(new URL(`projects/${project}/backlog`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('backlog-row')).toHaveCount(3, { timeout: 30_000 })
			await page.getByText('Select all', { exact: true }).click()
			expect((await api.delete(`${OBJECTS}/task/${ids[2]}`)).ok()).toBe(true)

			await page.getByTestId('bulk-action').click()
			await page.getByRole('option').filter({ hasText: /^\s*Priority\s*$/ }).click()
			await page.getByTestId('bulk-value').click()
			await page.getByRole('option').filter({ hasText: /^\s*High\s*$/ }).click()
			await page.getByTestId('bulk-apply').click()
			await expect(page.getByText('Tasks updated: 2', { exact: true })).toBeVisible()
			await expect(page.getByText('Tasks not changed: 1', { exact: true })).toBeVisible()
			await expect(page.getByTestId('backlog-bulk-bar')).toContainText('Selected: 1')
			await expect(page.locator(`[data-testid="backlog-row"][data-title="Three ${RUN}"] input[type="checkbox"]`)).toBeChecked()
			expect(await field(api, ids[0], 'priority')).toBe('high')
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
