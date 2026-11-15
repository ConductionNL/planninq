/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for limiting project creation to chosen groups
 * (projects-lifecycle-policy, section 2).
 *
 *   @e2e project-lifecycle::only-the-chosen-groups-may-create
 */

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const SETTINGS = '/index.php/apps/planninq/api/settings'
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

test.describe('Project creation by group', () => {
	test('only the chosen groups may create', async ({ page }) => {
		const api = await adminApi()
		const group = `projectleiders-${RUN}`
		const inside = `pl-${RUN}`
		const outside = `staf-${RUN}`
		const created: string[] = []
		try {
			expect((await api.post('/ocs/v2.php/cloud/groups', { data: { groupid: group } })).ok()).toBe(true)
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: inside, password: PASSWORD, groups: [group] } })).ok()).toBe(true)
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: outside, password: PASSWORD } })).ok()).toBe(true)
			expect((await api.post(SETTINGS, { data: { allow_project_creation: 'groups', project_creation_groups: JSON.stringify([group]) } })).ok()).toBe(true)

			for (const [user, allowed] of [[inside, true], [outside, false]] as Array<[string, boolean]>) {
				const ctx = await userApi(user)
				const settings = await (await ctx.get(SETTINGS)).json()
				expect(settings.canCreateProject, user).toBe(allowed)
				const res = await ctx.post('/index.php/apps/planninq/api/projects', { data: { title: `Groepen ${RUN} ${user}` } })
				expect(res.status(), user).toBe(allowed ? 201 : 403)
				if (res.ok()) {
					created.push((await res.json()).id)
				}
				await ctx.dispose()
			}

			await page.goto(new URL('/index.php/settings/admin/planninq', BASE_URL).toString())
			await page.getByLabel('Allow project creation').selectOption('groups')
			await expect(page.getByTestId('creation-groups')).toBeVisible()
		} finally {
			await api.post(SETTINGS, { data: { allow_project_creation: 'all', project_creation_groups: '[]' } })
			for (const id of created) {
				await api.delete(`${OBJECTS}/project/${id}`)
			}
			await api.delete(`/ocs/v2.php/cloud/users/${inside}`)
			await api.delete(`/ocs/v2.php/cloud/users/${outside}`)
			await api.delete(`/ocs/v2.php/cloud/groups/${group}`)
		}
	})
})
