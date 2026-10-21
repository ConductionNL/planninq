/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for My tasks, the task figures on the dashboard and the
 * personal project order (portfolio-my-work-dashboard).
 *
 *   @e2e my-work::tasks-from-two-projects
 *   @e2e my-work::change-a-status-without-leaving
 *   @e2e my-work::figures-for-a-user
 *   @e2e my-work::an-admins-count
 *   @e2e my-work::pin-a-project
 *   @e2e exclude my-work::nothing-assigned the shared instance provisions no second account with no tasks; the empty state is covered by the live check in the PR
 *
 * The suite runs as the admin. Counts on the dashboard include whatever else
 * the instance holds for the admin, so the figures are read before and after
 * the suite's own tasks are made and the difference is asserted.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

/**
 * A date relative to today, as YYYY-MM-DD.
 *
 * @param days Days from today.
 */
function day(days: number): string {
	const date = new Date()
	date.setDate(date.getDate() + days)
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

/**
 * The number a dashboard figure shows.
 *
 * @param page The page.
 * @param label The figure's label.
 */
async function figure(page: Page, label: string): Promise<number> {
	const tile = page.locator('.cn-stat-widget, [data-testid="cn-stat-widget"]').filter({ hasText: label }).first()
	await expect(tile).toBeVisible({ timeout: 30_000 })
	await expect(tile).toContainText(/\d/, { timeout: 30_000 })
	return Number((await tile.innerText()).match(/\d+/)?.[0] ?? NaN)
}

test.describe('My tasks', () => {
	test('tasks from two projects, a status changed in place, the figures and a pinned project', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			await page.goto(PLANNINQ_ROOT)
			const before = {
				open: await figure(page, 'My open tasks'),
				overdue: await figure(page, 'My overdue tasks'),
				progress: await figure(page, 'My tasks in progress'),
			}

			const project = async (title: string) => {
				const id = await createObject(api, 'project', { title: `${title} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], startDate: '2026-01-01', endDate: '2026-12-31' })
				made.push(['project', id])
				return id
			}
			const task = async (projectId: string, title: string, data: object) => {
				const id = await createObject(api, 'task', { title: `${title} ${RUN}`, status: 'open', project: projectId, assignedTo: ADMIN_USER, ...data })
				made.push(['task', id])
				return id
			}
			const vergunningen = await project('Vergunningen')
			const handhaving = await project('Handhaving')
			await task(vergunningen, 'Check the permit', { dueDate: day(-3), priority: 'high' })
			const visit = await task(handhaving, 'Visit the site', { dueDate: day(0) })
			const later = await task(handhaving, 'Write the report', { dueDate: day(30), status: 'in_progress' })

			await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
			const overdue = page.getByTestId('my-work-group-overdue')
			await expect(overdue.getByTestId('my-work-row').filter({ hasText: `Check the permit ${RUN}` })).toContainText(`Vergunningen ${RUN}`, { timeout: 30_000 })
			await expect(page.getByTestId('my-work-group-week').getByTestId('my-work-row').filter({ hasText: `Visit the site ${RUN}` })).toContainText(`Handhaving ${RUN}`)

			// Change a status without leaving.
			const row = page.getByTestId('my-work-row').filter({ hasText: `Visit the site ${RUN}` })
			await row.getByTestId('my-work-status').click()
			await page.getByRole('option', { name: 'In progress' }).click()
			await expect(page).toHaveURL(/\/my-tasks/)
			await expect.poll(async () => {
				const res = await api.get(`${OBJECTS}/task/${visit}`)
				return (await res.json()).status
			}, { timeout: 15_000 }).toBe('in_progress')

			// The title opens the task, and Back returns here.
			await page.getByTestId('my-work-row').filter({ hasText: `Write the report ${RUN}` }).getByTestId('my-work-title').click()
			await expect(page).toHaveURL(new RegExp(`/tasks/${later}`))
			await page.getByRole('button', { name: /Back/ }).first().click()
			await expect(page).toHaveURL(/\/my-tasks/)

			// The figures count the new tasks, and Overdue opens My tasks narrowed to it.
			await page.goto(PLANNINQ_ROOT)
			await expect.poll(() => figure(page, 'My open tasks'), { timeout: 30_000 }).toBe(before.open + 3)
			expect(await figure(page, 'My overdue tasks')).toBe(before.overdue + 1)
			expect(await figure(page, 'My tasks in progress')).toBe(before.progress + 2)
			await page.locator('.cn-stat-widget, [data-testid="cn-stat-widget"]').filter({ hasText: 'My overdue tasks' }).first().click()
			await expect(page).toHaveURL(/\/my-tasks\?group=overdue/)
			await expect(page.getByTestId('my-work-row').filter({ hasText: RUN })).toHaveCount(1)

			// "Projects I am in" counts the projects the admin is a member of.
			const members = await api.get(`${OBJECTS}/project?_limit=1000`)
			const rows = (await members.json()).results ?? []
			const mine = rows.filter((p: { members?: string[] }) => Array.isArray(p.members) && p.members.includes(ADMIN_USER)).length
			await page.goto(PLANNINQ_ROOT)
			expect(await figure(page, 'Projects I am in')).toBe(mine)

			// Pin a project: the last one listed moves to the top, also after a reload.
			const listed = page.getByTestId('dashboard-project')
			await expect(listed.first()).toBeVisible({ timeout: 30_000 })
			const last = listed.last()
			const title = (await last.getByTestId('dashboard-project-title').innerText()).trim()
			await last.getByTestId('dashboard-project-actions').getByRole('button').first().click()
			await page.getByTestId('dashboard-project-pin').click()
			await page.reload()
			await expect(page.getByTestId('dashboard-project-title').first()).toHaveText(title, { timeout: 30_000 })
			await expect(page.getByTestId('dashboard-project-pinned').first()).toBeVisible()

			// Unpin it again, so the admin's dashboard is left as it was.
			await page.getByTestId('dashboard-project').first().getByTestId('dashboard-project-actions').getByRole('button').first().click()
			await page.getByTestId('dashboard-project-pin').click()
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
