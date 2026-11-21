/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the owning group (projects-members-and-roles, task 5.1).
 *
 *   @e2e project-membership::a-colleague-in-the-owning-group-manages-the-project-after-the-creator-leaves
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, createObject, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`

/**
 * Open the project's settings sidebar on the Members tab.
 *
 * @param page The page.
 * @param project The project uuid.
 */
async function openMembers(page: Page, project: string) {
	await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
	await page.getByRole('button', { name: 'Project settings' }).click()
	await page.getByRole('tab', { name: 'Members' }).click()
}

test.describe('Owning group', () => {
	test('a colleague in the owning group manages the project after the creator leaves', async ({ browser }) => {
		const api = await adminApi()
		const group = `infra-${RUN}`
		const creator = `maker-${RUN}`
		const colleague = `collega-${RUN}`
		let project = ''
		try {
			expect((await api.post('/ocs/v2.php/cloud/groups', { data: { groupid: group } })).ok()).toBe(true)
			for (const [userid, groups] of [[creator, [group]], [colleague, [group]]] as Array<[string, string[]]>) {
				expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid, password: PASSWORD, groups } })).ok()).toBe(true)
			}
			project = await createObject(api, 'project', { title: `Groep ${RUN}`, status: 'active', owner: creator, members: [creator] })

			// The owner sets "Owned by group" on the Members tab.
			const c = await signedInPage(browser, creator, PASSWORD)
			await openMembers(c.page, project)
			await c.page.getByRole('combobox', { name: 'Owned by group' }).fill(group.slice(0, 6))
			await c.page.getByRole('option', { name: group }).click()
			await expect(c.page.getByTestId('owner-group-current')).toHaveText(`Owned by group: ${group}`)

			// The owner leaves through the "Leave project" dialog.
			await c.page.getByRole('button', { name: 'Leave project' }).click()
			await c.page.getByRole('dialog').getByRole('button', { name: 'Leave project' }).click()
			await c.context.close()

			// The colleague, with no role of their own, still changes the title.
			const g = await signedInPage(browser, colleague, PASSWORD)
			await openMembers(g.page, project)
			await expect(g.page.getByTestId('owner-group-current')).toHaveText(`Owned by group: ${group}`)
			await g.page.getByRole('tab', { name: 'Details' }).click()
			await g.page.getByLabel('Title').fill(`Groep ${RUN} verder`)
			await g.page.getByRole('button', { name: 'Save' }).click()
			await expect(g.page.getByText(`Groep ${RUN} verder`).first()).toBeVisible()
			await g.context.close()
		} finally {
			if (project) {
				await api.delete(`${OBJECTS}/project/${project}`)
			}
			for (const userid of [creator, colleague]) {
				await api.delete(`/ocs/v2.php/cloud/users/${userid}`)
			}
			await api.delete(`/ocs/v2.php/cloud/groups/${group}`)
		}
	})
})
