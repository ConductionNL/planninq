/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the Retention section of the planninq admin settings
 * (retention-on-planninq-records task 2.1): the admin picks a selection-list
 * category for projects, saves it, and the pick comes back from the server;
 * a non-admin is refused by the endpoint. When the instance has no selection
 * list, the section says so and offers nothing to pick, which is asserted
 * instead. Base URL and admin credentials come from the environment
 * (base-url.ts, portfolio-api.ts). Not run here yet.
 *
 *   @e2e record-retention::planninq-records-carry-a-retention-category
 */

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { adminApi } from './portfolio-api.ts'

const ENDPOINT = '/index.php/apps/planninq/api/settings/retention'
const RUN_ID = `ret${Date.now().toString(36)}`
const PASSWORD = `Retention-${RUN_ID}-pass!`

test.describe('Retention settings', () => {
	test.describe.configure({ timeout: 120_000 })

	test('an admin picks the category for projects and it is stored', async ({ page }) => {
		const api = await adminApi()
		const before = await (await api.get(ENDPOINT)).json()
		try {
			await page.goto(`${BASE_URL}/index.php/settings/admin/planninq`)
			const section = page.getByTestId('retention-settings')
			await expect(section).toBeVisible({ timeout: 30_000 })

			if ((before.categories ?? []).length === 0) {
				await expect(section.getByTestId('retention-no-list')).toBeVisible()
				await expect(section.getByTestId('retention-save')).toBeDisabled()
				return
			}

			const category = String(before.categories[0].category)
			await section.getByTestId('retention-project').click()
			await page.getByRole('option', { name: new RegExp(`^${category}\\b`) }).click()
			await section.getByTestId('retention-save').click()
			await expect(section.getByTestId('retention-message')).toContainText('Retention saved.')

			const after = await (await api.get(ENDPOINT)).json()
			expect(after.picks.project).toBe(category)
			expect(after.picks.task).toBe(before.picks.task ?? '')
		} finally {
			await api.put(ENDPOINT, { data: { picks: before.picks ?? {} } })
			await api.dispose()
		}
	})

	test('a non-admin cannot read or change the retention', async () => {
		const api = await adminApi()
		const user = `${RUN_ID}-user`
		expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: user, password: PASSWORD } })).ok()).toBe(true)
		const member = await request.newContext({
			baseURL: BASE_URL,
			httpCredentials: { username: user, password: PASSWORD, send: 'always' },
			storageState: { cookies: [], origins: [] },
			extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
		})
		try {
			expect((await member.get(ENDPOINT)).status()).toBe(403)
			expect((await member.put(ENDPOINT, { data: { picks: { project: '' } } })).status()).toBe(403)
		} finally {
			await member.dispose()
			await api.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
			await api.dispose()
		}
	})
})
