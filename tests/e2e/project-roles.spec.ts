/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for roles in the interface (projects-members-and-roles, section 4).
 *
 *   @e2e project-membership::a-group-member-sees-a-project-shared-with-the-group
 *   @e2e project-membership::a-viewer-reads-the-board-and-cannot-change-it
 *   @e2e project-membership::a-member-cannot-manage-members
 *   @e2e project-membership::leaving-the-group-removes-access
 */

import type { Browser } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`

/**
 * An API context signed in as `username`.
 *
 * @param username The user id.
 */
async function userApi(username: string) {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password: PASSWORD, send: 'always' },
		extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * A browser page signed in as `username`, in a context of its own.
 *
 * @param browser The browser.
 * @param username The user id.
 */
async function signedInPage(browser: Browser, username: string) {
	const context = await browser.newContext({ baseURL: BASE_URL, storageState: { cookies: [], origins: [] } })
	const page = await context.newPage()
	await page.goto('/index.php/login')
	await page.locator('input[name="user"]').fill(username)
	await page.locator('input[name="password"]').fill(PASSWORD)
	await page.locator('button[type="submit"]').first().click()
	await page.waitForSelector('#header, header.header', { timeout: 20_000 })
	return { context, page }
}

test.describe('Project roles', () => {
	test('roles decide what each person sees', async ({ browser }) => {
		const api = await adminApi()
		const group = `ploeg-${RUN}`
		const inGroup = `gm-${RUN}`
		const viewer = `kijker-${RUN}`
		const member = `lid-${RUN}`
		let project = ''
		let task = ''
		try {
			expect((await api.post('/ocs/v2.php/cloud/groups', { data: { groupid: group } })).ok()).toBe(true)
			for (const [userid, groups] of [[inGroup, [group]], [viewer, []], [member, []]] as Array<[string, string[]]>) {
				expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid, password: PASSWORD, groups } })).ok()).toBe(true)
			}
			project = await createObject(api, 'project', {
				title: `Rollen ${RUN}`,
				status: 'active',
				owner: ADMIN_USER,
				members: [ADMIN_USER, member],
				viewers: [viewer],
				memberGroups: [group],
			})

			task = await createObject(api, 'task', { title: `Taak ${RUN}`, project, status: 'todo' })

			// A group member sees a project shared with the group.
			const g = await signedInPage(browser, inGroup)
			await g.page.goto(new URL('projects', PLANNINQ_ROOT).toString())
			await expect(g.page.getByText(`Rollen ${RUN}`)).toBeVisible()

			// Leaving the group removes access.
			expect((await api.delete(`/ocs/v2.php/cloud/users/${inGroup}/groups`, { params: { groupid: group } })).ok()).toBe(true)
			await g.page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await expect(g.page.getByText('You do not have access to this project')).toBeVisible()
			await g.context.close()

			// A viewer reads the board and cannot change it.
			const v = await signedInPage(browser, viewer)
			await v.page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await expect(v.page.getByTestId('board-read-only')).toBeVisible()
			await v.context.close()
			const vApi = await userApi(viewer)
			expect((await vApi.put(`${OBJECTS}/task/${task}`, { data: { title: `Taak ${RUN} anders`, project, status: 'todo' } })).status()).toBe(403)
			await vApi.dispose()

			// A member cannot manage members.
			const m = await signedInPage(browser, member)
			await m.page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await m.page.getByRole('button', { name: 'Project settings' }).click()
			await m.page.getByRole('tab', { name: 'Members' }).click()
			await expect(m.page.getByTestId('members-read-only')).toBeVisible()
			await expect(m.page.getByTestId('member-search')).toHaveCount(0)
			await m.context.close()
			const mApi = await userApi(member)
			expect((await mApi.patch(`${OBJECTS}/project/${project}`, { data: { members: [ADMIN_USER, member, viewer] } })).status()).toBe(403)
			await mApi.dispose()
		} finally {
			if (task) {
				await api.delete(`${OBJECTS}/task/${task}`)
			}
			if (project) {
				await api.delete(`${OBJECTS}/project/${project}`)
			}
			for (const userid of [inGroup, viewer, member]) {
				await api.delete(`/ocs/v2.php/cloud/users/${userid}`)
			}
			await api.delete(`/ocs/v2.php/cloud/groups/${group}`)
		}
	})
})
