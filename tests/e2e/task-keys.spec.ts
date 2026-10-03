/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for project keys and readable task keys (tasks-readable-keys).
 *
 *   @e2e work-item-keys::create-a-project-with-a-key
 *   @e2e work-item-keys::a-used-key-is-refused
 *   @e2e work-item-keys::numbered-on-create
 *   @e2e work-item-keys::an-imported-key-is-kept
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).toUpperCase().slice(-7)

test.describe('Readable keys', () => {
	test('create a project with a key from the New project dialog', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: /new project/i }).first().click()
			await page.getByLabel('Project title').fill(`Vergunningen Centrum ${RUN}`)
			await expect(page.getByTestId('project-creation-key').locator('input')).toHaveValue(`VC${RUN[0]}`)
			await page.getByTestId('project-creation-key').locator('input').fill(`V${RUN}`)
			await page.getByRole('button', { name: 'Create project' }).click()

			const res = await api.get(`${OBJECTS}/project?key=V${RUN}`)
			const rows = (await res.json()).results ?? []
			expect(rows).toHaveLength(1)
			made.push(['project', rows[0].id ?? rows[0]['@self']?.id])
		} finally {
			await removeObjects(api, made)
		}
	})

	test('a used key is refused without naming the other project', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			made.push(['project', await createObject(api, 'project', { title: `Secret ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], key: `U${RUN}` })])
			await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: /new project/i }).first().click()
			await page.getByLabel('Project title').fill(`Another ${RUN}`)
			await page.getByTestId('project-creation-key').locator('input').fill(`u${RUN.toLowerCase()}`)
			await expect(page.getByText('This key is already used by another project.')).toBeVisible()
			await expect(page.getByRole('button', { name: 'Create project' })).toBeDisabled()
			await expect(page.getByText(`Secret ${RUN}`)).toHaveCount(0)
		} finally {
			await removeObjects(api, made)
		}
	})

	test('a new task gets the next key and shows it; an imported key is kept', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Keys ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], key: `N${RUN}` })
			made.push(['project', project])
			const first = await createObject(api, 'task', { title: `Fundering ${RUN}`, status: 'open', project })
			const imported = await createObject(api, 'task', { title: `Imported ${RUN}`, status: 'open', project, key: 'PLX-7' })
			const second = await createObject(api, 'task', { title: `Metselwerk ${RUN}`, status: 'open', project })
			made.push(['task', first], ['task', imported], ['task', second])

			const keyOf = async (id: string) => (await (await api.get(`${OBJECTS}/task/${id}`)).json()).key
			expect(await keyOf(first)).toBe(`N${RUN}-1`)
			expect(await keyOf(imported)).toBe('PLX-7')
			expect(await keyOf(second)).toBe(`N${RUN}-2`)

			await page.goto(new URL(`projects/${project}/tasks/${second}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('task-detail-key')).toHaveText(`N${RUN}-2`)
			await expect(page).toHaveTitle(new RegExp(`N${RUN}-2 Metselwerk`))
		} finally {
			await removeObjects(api, made)
		}
	})
})
