/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for dependencies on the task page and the board
 * (planning-dependencies-on-task-page).
 *
 *   @e2e task-dependencies::add-a-blocker-from-the-task-page
 *   @e2e task-dependencies::a-cycle-is-refused-on-the-page
 *   @e2e task-dependencies::the-badge-appears-and-clears
 *   @e2e task-dependencies::link-two-related-tasks
 *   @e2e task-dependencies::arrow-on-the-timeline
 *   @e2e task-dependencies::add-a-blocked-by-dependency
 *   @e2e task-dependencies::remove-a-dependency
 *   @e2e task-dependencies::blocker-completion-clears-the-indicator
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

/**
 * A project with two dated tasks.
 *
 * @param api The admin context.
 * @param made The objects to remove afterwards.
 */
async function twoTasks(api, made: Array<[string, string]>) {
	const project = await createObject(api, 'project', { title: `Links ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
	made.push(['project', project])
	const first = await createObject(api, 'task', { title: `Fundering ${RUN}`, status: 'open', project, startDate: '2027-03-01', dueDate: '2027-03-12' })
	const second = await createObject(api, 'task', { title: `Metselwerk ${RUN}`, status: 'open', project, startDate: '2027-03-15', dueDate: '2027-03-26' })
	made.push(['task', first], ['task', second])
	return { project, first, second }
}

/**
 * Add a link from the task page of `taskId` to the task titled `title`.
 *
 * @param page The page.
 * @param project The project id.
 * @param taskId The task whose page is open.
 * @param title The task to link.
 * @param type The link type label.
 */
async function addLink(page, project: string, taskId: string, title: string, type = 'Blocked by') {
	await page.goto(new URL(`projects/${project}/tasks/${taskId}`, PLANNINQ_ROOT).toString())
	await page.getByTestId('task-link-type').click()
	await page.getByRole('option', { name: type }).click()
	await page.getByTestId('task-link-picker').click()
	await page.getByRole('option', { name: title }).click()
	await page.getByTestId('task-link-add').click()
}

test.describe('Task dependencies', () => {
	test('add a blocked by link, see it on both tasks, and remove it', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const { project, first, second } = await twoTasks(api, made)
			await addLink(page, project, second, `Fundering ${RUN}`)
			await expect(page.locator('.task-dependencies__group', { hasText: 'Blocked by' })).toContainText(`Fundering ${RUN}`)

			await page.goto(new URL(`projects/${project}/tasks/${first}`, PLANNINQ_ROOT).toString())
			const blocks = page.locator('.task-dependencies__group', { hasText: 'Blocks' })
			await expect(blocks).toContainText(`Metselwerk ${RUN}`)
			await blocks.getByRole('button', { name: /remove dependency/i }).click()
			await expect(blocks).not.toContainText(`Metselwerk ${RUN}`)
		} finally {
			await removeObjects(api, made)
		}
	})

	test('a cycle is refused with the server message', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const { project, first, second } = await twoTasks(api, made)
			const res = await api.post('/index.php/apps/planninq/api/dependencies', { data: { blocker: first, blocked: second } })
			expect(res.ok()).toBe(true)

			await addLink(page, project, first, `Metselwerk ${RUN}`)
			await expect(page.getByTestId('task-link-error')).toContainText(/cycle/i)
		} finally {
			await removeObjects(api, made)
		}
	})

	test('a blocked task shows a badge on the board until its blocker is done', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const { project, first, second } = await twoTasks(api, made)
			const res = await api.post('/index.php/apps/planninq/api/dependencies', { data: { blocker: first, blocked: second } })
			expect(res.ok()).toBe(true)

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const card = page.getByTestId('task-card').filter({ hasText: `Metselwerk ${RUN}` })
			await expect(card.getByTestId('task-blocked-badge')).toBeVisible()

			await api.patch(`${OBJECTS}/task/${first}`, { data: { status: 'done' } })
			await page.reload()
			await expect(page.getByTestId('task-card').filter({ hasText: `Metselwerk ${RUN}` }).getByTestId('task-blocked-badge')).toHaveCount(0)
		} finally {
			await removeObjects(api, made)
		}
	})

	test('a related link does not block, and a link shows on the timeline', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const { project, second } = await twoTasks(api, made)
			await addLink(page, project, second, `Fundering ${RUN}`, 'Relates to')
			await expect(page.getByTestId('task-links-related')).toContainText(`Fundering ${RUN}`)

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('task-card').filter({ hasText: `Metselwerk ${RUN}` }).getByTestId('task-blocked-badge')).toHaveCount(0)

			await page.goto(new URL(`projects/${project}/timeline`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('timeline-edge')).toHaveCount(1)
		} finally {
			await removeObjects(api, made)
		}
	})
})
