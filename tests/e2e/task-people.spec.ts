/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for people, priority and labels on a task (tasks-assignment-priority-labels).
 *
 *   @e2e task-people-and-tags::assign-a-task-on-the-task-page
 *   @e2e task-people-and-tags::only-members-are-offered
 *   @e2e task-people-and-tags::two-people-on-one-task
 *   @e2e task-people-and-tags::raise-a-priority-from-the-board
 *   @e2e task-people-and-tags::attach-a-label
 *
 * The project's second member is a user id only (no account is created), so
 * the picker falls back to showing the id; that is enough to prove who is
 * offered and what is stored.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const BRAM = `bram${RUN}`

test.describe('People, priority and labels', () => {
	test('assign, share, and only members are offered', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Vergunningen ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, BRAM] })
			made.push(['project', project])
			const column = await createObject(api, 'column', { title: 'To do', project, order: 1, status: 'open' })
			made.push(['column', column])
			const task = await createObject(api, 'task', { title: `Unassigned ${RUN}`, status: 'open', project, column, columnOrder: 1000 })
			made.push(['task', task])
			const stored = async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json())

			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			const responsible = page.getByTestId('task-responsible')
			await responsible.click()
			await expect(page.getByRole('option', { name: `carla${RUN}` })).toHaveCount(0)
			await page.getByRole('option', { name: BRAM }).click()
			await expect.poll(async () => (await stored()).assignedTo).toBe(BRAM)

			await page.getByTestId('task-shared-with').click()
			await page.getByRole('option').filter({ hasText: ADMIN_USER }).first().click()
			await expect.poll(async () => (await stored()).sharedWith).toEqual([ADMIN_USER])
			expect((await stored()).assignedTo).toBe(BRAM)

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const people = page.getByTestId('task-card').filter({ hasText: `Unassigned ${RUN}` }).getByTestId('task-card-people').locator('li')
			await expect(people).toHaveCount(2)
			await expect(people.first()).toContainText(BRAM)
		} finally {
			await removeObjects(api, made.reverse())
		}
	})

	test('raise a priority from the board and attach a label', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Triage ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const column = await createObject(api, 'column', { title: 'To do', project, order: 1, status: 'open' })
			made.push(['column', column])
			const label = await createObject(api, 'label', { title: `Juridisch ${RUN}`, color: '#1a5fb4' })
			made.push(['label', label])
			const task = await createObject(api, 'task', { title: `Triage me ${RUN}`, status: 'open', priority: 'normal', project, column, columnOrder: 1000 })
			made.push(['task', task])

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const card = page.getByTestId('task-card').filter({ hasText: `Triage me ${RUN}` })
			await card.getByRole('button', { name: 'Move task to another column' }).click()
			await page.getByTestId('set-priority-urgent').click()
			await expect(card).toContainText('Urgent')
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json()).priority).toBe('urgent')

			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-labels').click()
			await page.getByRole('option', { name: `Juridisch ${RUN}` }).click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json()).labels).toEqual([label])

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await expect(card).toContainText(`Juridisch ${RUN}`)
			await page.getByTestId('label-filter-chip').filter({ hasText: `Juridisch ${RUN}` }).click()
			await expect(page.getByTestId('task-card')).toHaveCount(1)
		} finally {
			await removeObjects(api, made.reverse())
		}
	})
})
