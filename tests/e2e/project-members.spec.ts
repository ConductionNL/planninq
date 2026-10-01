/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for finding colleagues on the Members tab without admin rights
 * (projects-members-and-roles, section 3).
 *
 *   @e2e project-membership::a-regular-owner-adds-a-colleague-by-name
 *   @e2e project-membership::the-admins-search-limits-apply
 */

import type { Browser } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const CORE_CONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/core'

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

/**
 * Open the Members tab of a project's settings sidebar.
 *
 * @param page The page.
 * @param project The project id.
 */
async function openMembers(page, project: string) {
	await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
	await page.getByRole('button', { name: 'Project settings' }).click()
	await page.getByRole('tab', { name: 'Members' }).click()
}

test.describe('Project members', () => {
	test('a regular owner adds a colleague by name', async ({ browser }) => {
		const api = await adminApi()
		const owner = `eig-${RUN}`
		const ada = `ada-${RUN}`
		let project = ''
		try {
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: owner, password: PASSWORD } })).ok()).toBe(true)
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: ada, password: PASSWORD, displayName: `Ada Jansen ${RUN}` } })).ok()).toBe(true)
			const ownerApi = await userApi(owner)
			const res = await ownerApi.post('/index.php/apps/planninq/api/projects', { data: { title: `Leden ${RUN}` } })
			expect(res.status()).toBe(201)
			project = (await res.json()).id
			await ownerApi.dispose()

			const { context, page } = await signedInPage(browser, owner)
			await openMembers(page, project)
			await page.getByRole('combobox', { name: 'Add member' }).fill('Ada')
			await page.getByTestId(`member-option-user:${ada}`).click()
			await expect(page.getByTestId(`project-member-user:${ada}`)).toContainText(`Ada Jansen ${RUN}`)
			await context.close()

			const stored = await (await api.get(`${OBJECTS}/project/${project}`)).json()
			expect(stored.members).toContain(ada)
			const adaApi = await userApi(ada)
			expect((await adaApi.get(`${OBJECTS}/project/${project}`)).ok()).toBe(true)
			await adaApi.dispose()
		} finally {
			if (project) {
				await api.delete(`${OBJECTS}/project/${project}`)
			}
			await api.delete(`/ocs/v2.php/cloud/users/${owner}`)
			await api.delete(`/ocs/v2.php/cloud/users/${ada}`)
		}
	})

	test('the admin\'s search limits apply', async ({ browser }) => {
		const api = await adminApi()
		const owner = `eig2-${RUN}`
		const bram = `bram-${RUN}`
		const group = `team-${RUN}`
		let project = ''
		try {
			expect((await api.post('/ocs/v2.php/cloud/groups', { data: { groupid: group } })).ok()).toBe(true)
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: owner, password: PASSWORD, groups: [group] } })).ok()).toBe(true)
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: bram, password: PASSWORD, displayName: `Bram Visser ${RUN}` } })).ok()).toBe(true)
			expect((await api.post(`${CORE_CONFIG}/shareapi_restrict_user_enumeration_to_group`, { data: { value: 'yes' } })).ok()).toBe(true)
			const ownerApi = await userApi(owner)
			const res = await ownerApi.post('/index.php/apps/planninq/api/projects', { data: { title: `Grenzen ${RUN}` } })
			expect(res.status()).toBe(201)
			project = (await res.json()).id
			await ownerApi.dispose()

			const { context, page } = await signedInPage(browser, owner)
			await openMembers(page, project)
			await page.getByRole('combobox', { name: 'Add member' }).fill('Bram')
			await expect(page.getByTestId('member-search-empty')).toHaveText('No one found. Your admin\'s sharing settings decide who you can find.')
			await expect(page.getByTestId(`member-option-user:${bram}`)).toHaveCount(0)
			await context.close()
		} finally {
			await api.delete(`${CORE_CONFIG}/shareapi_restrict_user_enumeration_to_group`)
			if (project) {
				await api.delete(`${OBJECTS}/project/${project}`)
			}
			await api.delete(`/ocs/v2.php/cloud/users/${owner}`)
			await api.delete(`/ocs/v2.php/cloud/users/${bram}`)
			await api.delete(`/ocs/v2.php/cloud/groups/${group}`)
		}
	})
})
