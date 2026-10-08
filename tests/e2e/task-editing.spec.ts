/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for creating, editing and deleting a task (tasks-create-edit-delete).
 *
 *   @e2e task-editing::create-a-task-from-the-board-header
 *   @e2e task-editing::quick-add-two-tasks-in-a-row
 *   @e2e task-editing::rename-a-task
 *   @e2e task-editing::a-checklist-style-description-renders-as-a-list
 *   @e2e task-editing::script-in-a-description-stays-text
 *   @e2e task-editing::delete-a-task-without-logged-time
 *   @e2e task-editing::a-task-with-logged-time-is-cancelled-instead
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Task editing', () => {
	test('create from the header, quick add two tasks, rename one', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Vergunningen ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			made.push(['column', await createObject(api, 'column', { title: 'To do', project, order: 1, status: 'open' })])
			made.push(['column', await createObject(api, 'column', { title: 'In progress', project, order: 2, status: 'in_progress' })])
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())

			await page.getByTestId('new-task').click()
			await page.getByTestId('task-form-title').locator('input').fill(`Draft the permit letter ${RUN}`)
			await page.getByTestId('task-form-save').click()
			await expect(page.locator('section.kanban-column[data-column="To do"]').getByTestId('task-card').filter({ hasText: `Draft the permit letter ${RUN}` })).toBeVisible()

			const tasks = async () => ((await (await api.get(`${OBJECTS}/task?project=${project}`)).json()).results ?? [])
			const created = (await tasks()).find((task: { title: string }) => task.title === `Draft the permit letter ${RUN}`)
			expect(created).toMatchObject({ status: 'open', priority: 'normal', reporter: ADMIN_USER })

			const lane = page.locator('section.kanban-column[data-column="In progress"]')
			const field = lane.getByTestId('quick-add').locator('input')
			await field.fill('Call the applicant')
			await field.press('Enter')
			await expect(field).toHaveValue('')
			await field.fill('Check the drawings')
			await field.press('Enter')
			await expect(field).toBeFocused()
			await expect(lane.getByTestId('task-card')).toHaveText([/Call the applicant/, /Check the drawings/])
			const quickAdded = (await tasks()).filter((task: { title: string }) => task.title === 'Call the applicant')
			expect(quickAdded[0]).toMatchObject({ status: 'in_progress' })
			for (const task of await tasks()) {
				made.push(['task', task.id ?? task['@self']?.id])
			}

			await page.goto(new URL(`projects/${project}/tasks/${created.id ?? created['@self']?.id}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-edit').click()
			await page.getByTestId('task-form-title').locator('input').fill(`Draft and send the permit letter ${RUN}`)
			await page.getByTestId('task-form-save').click()
			await expect(page.getByRole('heading', { name: new RegExp(`Draft and send the permit letter ${RUN}`) })).toBeVisible()
			const renamed = (await tasks()).find((task: { title: string }) => task.title === `Draft and send the permit letter ${RUN}`)
			expect(renamed).toMatchObject({ priority: 'normal', project })
		} finally {
			await removeObjects(api, made.reverse())
		}
	})

	test('a Markdown description renders as a list, and script stays text', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Markdown ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const task = await createObject(api, 'task', { title: 'Steps', status: 'open', project, description: '## Steps\n- Call the applicant\n- Check the drawings\n\n<script>window.__planninqXss = 1</script>' })
			made.push(['task', task])

			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			const description = page.getByTestId('task-description')
			await expect(description.getByRole('heading', { name: 'Steps' })).toBeVisible()
			await expect(description.getByRole('listitem')).toHaveCount(2)
			await expect(description).toContainText('<script>')
			expect(await page.evaluate(() => (window as unknown as { __planninqXss?: number }).__planninqXss)).toBeUndefined()
		} finally {
			await removeObjects(api, made.reverse())
		}
	})

	test('delete a task without time; a task with time is cancelled instead', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Delete ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const plain = await createObject(api, 'task', { title: `No time ${RUN}`, status: 'open', project })
			const timed = await createObject(api, 'task', { title: `Two hours ${RUN}`, status: 'open', project })
			made.push(['task', timed])
			made.push(['plannedTimeEntry', await createObject(api, 'plannedTimeEntry', { task: timed, user: ADMIN_USER, duration: 120, date: '2026-09-28', project })])

			await page.goto(new URL(`projects/${project}/tasks/${plain}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-delete').click()
			await page.getByTestId('task-delete-confirm').click()
			await expect(page).toHaveURL(new RegExp(`projects/${project}$`))
			expect((await api.get(`${OBJECTS}/task/${plain}`)).status()).toBe(404)

			await page.goto(new URL(`projects/${project}/tasks/${timed}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-delete').click()
			await expect(page.getByTestId('task-delete-has-time')).toBeVisible()
			await page.getByTestId('task-delete-cancel-task').click()
			expect((await (await api.get(`${OBJECTS}/task/${timed}`)).json()).status).toBe('cancelled')
		} finally {
			await removeObjects(api, made.reverse())
		}
	})
})
