/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for subtasks, the checklist, rollups, duplicating and deleting
 * a parent (tasks-subtasks-checklist).
 *
 *   @e2e task-breakdown::add-a-subtask
 *   @e2e task-breakdown::a-subtask-has-no-subtasks-section
 *   @e2e task-breakdown::tick-a-checklist-item
 *   @e2e task-breakdown::rollup-on-the-parent
 *   @e2e task-breakdown::duplicate-a-task-tree
 *   @e2e task-breakdown::keep-the-subtasks
 */

import type { LoggedHours } from './logged-hours.ts'

import { expect, test } from '@playwright/test'
import { humaniqEnabled, logHours, removeLoggedHours } from './logged-hours.ts'
import { inputByTestId, PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Task breakdown', () => {
	// Logged hours are seeded on humaniq too, a few more requests per test.
	test.describe.configure({ timeout: 90_000 })

	test('add a subtask, tick a checklist item, see the rollup', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		const booked: LoggedHours[] = []
		try {
			const project = await createObject(api, 'project', { title: `Raad ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const column = await createObject(api, 'column', { title: 'To do', project, order: 1, status: 'open' })
			made.push(['column', column])
			const checklist = ['a', 'b', 'c', 'd', 'e'].map((id, i) => ({ id, text: `Step ${id}`, done: i < 2 }))
			const parent = await createObject(api, 'task', { title: `Prepare the council decision ${RUN}`, status: 'open', project, column, columnOrder: 1000, estimatedDuration: 60, checklist })
			const s1 = await createObject(api, 'task', { title: 'Estimate two', status: 'open', project, column, columnOrder: 2000, parent, estimatedDuration: 120 })
			const s2 = await createObject(api, 'task', { title: 'Estimate three', status: 'open', project, column, columnOrder: 3000, parent, estimatedDuration: 180 })
			made.push(['task', s1], ['task', s2], ['task', parent])
			// Logged hours live on humaniq; without it the rollup has no hours to add.
			const withHours = await humaniqEnabled(api)
			if (withHours) {
				booked.push(await logHours(api, { task: s1, project, user: ADMIN_USER, minutes: 90, date: '2026-09-28' }))
			}

			await page.goto(new URL(`projects/${project}/tasks/${parent}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('subtask-rollup')).toContainText('Subtasks: 5h estimated')
			if (withHours) {
				await expect(page.getByTestId('subtask-rollup')).toContainText('Subtasks: 5h estimated, 1h 30m logged')
			}
			await expect(page.getByTestId('subtask-rollup')).toContainText('Total estimate: 6h')

			const add = inputByTestId(page, 'subtask-add')
			await add.fill('Collect the advice')
			await add.press('Enter')
			await expect(page.getByTestId('subtask-progress')).toHaveText('0 of 3 done')
			const children = ((await (await api.get(`${OBJECTS}/task?parent=${parent}`)).json()).results ?? [])
			const added = children.find((task: { title: string }) => task.title === 'Collect the advice')
			expect(added).toMatchObject({ parent, project })
			made.unshift(['task', added.id ?? added['@self']?.id])

			// The test id sits on NcCheckboxRadioSwitch's hidden input; its label takes the click.
			await page.getByText('Step c', { exact: true }).click()
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const card = page.getByTestId('task-card').filter({ hasText: `Prepare the council decision ${RUN}` })
			await expect(card.getByTestId('task-card-checklist')).toHaveText('3/5')
			await expect(page.getByTestId('task-card').filter({ hasText: 'Collect the advice' }).getByTestId('task-card-parent')).toBeVisible()

			await page.goto(new URL(`projects/${project}/tasks/${s1}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('task-checklist')).toBeVisible()
			await expect(page.getByTestId('task-subtasks')).toHaveCount(0)
		} finally {
			await removeLoggedHours(api, booked)
			await removeObjects(api, made)
		}
	})

	test('duplicate a task tree, then delete a parent and keep its subtasks', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Kopie ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const parent = await createObject(api, 'task', { title: `Tree ${RUN}`, status: 'open', project, checklist: [{ id: 'c1', text: 'One', done: true }] })
			const s1 = await createObject(api, 'task', { title: `Leaf one ${RUN}`, status: 'open', project, parent })
			const s2 = await createObject(api, 'task', { title: `Leaf two ${RUN}`, status: 'open', project, parent })
			made.push(['task', s1], ['task', s2])

			await page.goto(new URL(`projects/${project}/tasks/${parent}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-duplicate').click()
			await expect(page.locator('.task-detail__title')).toHaveText(new RegExp(`Copy of Tree ${RUN}`))
			const all = ((await (await api.get(`${OBJECTS}/task?project=${project}`)).json()).results ?? [])
			const copy = all.find((task: { title: string }) => task.title === `Copy of Tree ${RUN}`)
			expect(copy).toMatchObject({ status: 'open', checklist: [{ id: 'c1', text: 'One', done: false }] })
			const copyId = copy.id ?? copy['@self']?.id
			const copiedChildren = all.filter((task: { parent: string }) => task.parent === copyId)
			expect(copiedChildren).toHaveLength(2)
			for (const task of copiedChildren) {
				made.unshift(['task', task.id ?? task['@self']?.id])
			}
			made.push(['task', copyId])

			await page.goto(new URL(`projects/${project}/tasks/${parent}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-delete').click()
			await expect(page.getByTestId('task-delete-has-subtasks')).toBeVisible()
			await page.getByTestId('task-delete-keep-subtasks').click()
			await expect(page).toHaveURL(new RegExp(`projects/${project}$`))
			expect((await api.get(`${OBJECTS}/task/${parent}`)).status()).toBe(404)
			expect((await (await api.get(`${OBJECTS}/task/${s1}`)).json()).parent ?? null).toBeNull()
		} finally {
			await removeObjects(api, made)
		}
	})
})
