/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for setting task dates inline (tasks-dates 2.1) and for moving
 * a task and copying a column to another project (tasks-move-between-projects
 * 2.2 and 3.1).
 *
 *   @e2e task-dates::set-a-due-date-on-the-task-page
 *   @e2e task-dates::the-board-badge-follows-the-new-date
 *   @e2e task-project-move::move-a-task
 *   @e2e task-project-move::copy-a-column
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { inputByTestId, PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

/**
 * Tomorrow as YYYY-MM-DD.
 */
function tomorrow(): string {
	const day = new Date()
	day.setDate(day.getDate() + 1)
	return day.toISOString().slice(0, 10)
}

/**
 * The tasks of a project, as stored.
 *
 * @param api The admin context.
 * @param project The project id.
 */
async function tasksOf(api: APIRequestContext, project: string): Promise<Array<Record<string, unknown>>> {
	const body = await (await api.get(`${OBJECTS}/task?project=${project}&_limit=100`)).json()
	return body.results ?? []
}

test.describe('Task dates and moving work between projects', () => {
	// Each test seeds a project through the API before it opens a page.
	test.describe.configure({ timeout: 120_000 })

	test('a due date set on the task page shows as a badge on the board card', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Dates ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const column = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', column])
			const task = await createObject(api, 'task', { title: `Dated ${RUN}`, project, column, columnOrder: 0, status: 'open' })
			made.push(['task', task])

			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			const due = inputByTestId(page, 'task-due-date')
			await expect(due).toBeVisible({ timeout: 30_000 })
			await due.fill(tomorrow())
			await due.blur()
			await expect.poll(async () => String((await (await api.get(`${OBJECTS}/task/${task}`)).json()).dueDate ?? '').slice(0, 10), { timeout: 15_000 }).toBe(tomorrow())

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const card = page.locator(`[data-testid="task-card"][aria-label="Dated ${RUN}"]`)
			await expect(card).toBeVisible({ timeout: 30_000 })
			await expect(card).toContainText('Due soon')
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('a task moved to another project lands in its backlog', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const source = await createObject(api, 'project', { title: `Move from ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', source])
			const target = await createObject(api, 'project', { title: `Move to ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', target])
			const task = await createObject(api, 'task', { title: `Mover ${RUN}`, project: source, status: 'open' })
			made.push(['task', task])

			await page.goto(new URL(`projects/${source}/tasks/${task}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-move').first().click()
			await page.getByTestId('task-move-target').click()
			await page.getByRole('option').filter({ hasText: `Move to ${RUN}` }).click()
			await page.getByTestId('task-move-confirm').click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json()).project, { timeout: 15_000 }).toBe(target)

			await page.goto(new URL(`projects/${target}/backlog`, PLANNINQ_ROOT).toString())
			await expect(page.locator(`[data-testid="backlog-row"][data-title="Mover ${RUN}"]`)).toHaveCount(1, { timeout: 30_000 })
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('copying a column puts a copy of it and its tasks on the other project', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		let target = ''
		try {
			const source = await createObject(api, 'project', { title: `Copy from ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', source])
			target = await createObject(api, 'project', { title: `Copy to ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', target])
			const column = await createObject(api, 'column', { title: `Review ${RUN}`, project: source, order: 0, status: 'open' })
			made.push(['column', column])
			made.push(['task', await createObject(api, 'task', { title: `Copied ${RUN}`, project: source, column, columnOrder: 0, status: 'open' })])

			await page.goto(new URL(`projects/${source}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('task-card')).toHaveCount(1, { timeout: 30_000 })
			await page.getByTestId('column-actions').first().click()
			await page.getByTestId('column-copy-to-project').click()
			await page.getByTestId('column-move-target').click()
			await page.getByRole('option').filter({ hasText: `Copy to ${RUN}` }).click()
			await page.getByTestId('column-move-confirm').click()

			await expect.poll(async () => (await tasksOf(api, target)).map((t) => t.title), { timeout: 15_000 }).toContain(`Copied ${RUN}`)
			expect((await tasksOf(api, source)).map((t) => t.title)).toContain(`Copied ${RUN}`)
			await page.goto(new URL(`projects/${target}`, PLANNINQ_ROOT).toString())
			await expect(page.locator(`[data-testid="task-card"][aria-label="Copied ${RUN}"]`)).toHaveCount(1, { timeout: 30_000 })
		} finally {
			if (target) {
				for (const task of await tasksOf(api, target)) {
					made.push(['task', String(task.id)])
				}
				const columns = (await (await api.get(`${OBJECTS}/column?project=${target}&_limit=100`)).json()).results ?? []
				for (const column of columns) {
					made.push(['column', String(column.id)])
				}
			}
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
