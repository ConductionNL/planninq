/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the admin settings in the shared shell
 * (adopt-cnapproot-manifest-shell, 3.1).
 *
 *   @e2e planninq-app-shell::admin-settings-render-and-save-through-the-shared-shell
 *
 * The admin settings page is admin-only, so this spec uses the admin session
 * the project's storage state carries. It puts the setting back as it found it.
 */

import { expect, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { adminApi } from './portfolio-api.ts'

const SETTINGS = '/index.php/apps/planninq/api/settings'

test.describe('Admin settings in the shared shell', () => {
	test('changing who may create projects saves through the settings endpoint', async ({ page }) => {
		const admin = await adminApi()
		const read = async (): Promise<string> => {
			const body = await (await admin.get(SETTINGS)).json()
			return String((body?.config ?? body)?.allow_project_creation ?? 'all')
		}
		const before = await read()
		const next = before === 'admins' ? 'all' : 'admins'
		try {
			await page.goto(`${BASE_URL}/index.php/settings/admin/planninq`)

			// The page renders inside the shared admin settings shell.
			await expect(page.locator('.cn-admin-settings-shell, [data-testid^="cn-admin-settings"]').first()).toBeVisible({ timeout: 30_000 })
			const select = page.locator('#allow-project-creation')
			await expect(select).toHaveValue(before)

			const saved = page.waitForRequest((req) => req.method() === 'POST' && /\/apps\/planninq\/api\/settings$/.test(new URL(req.url()).pathname))
			await select.selectOption(next)
			await select.locator('xpath=ancestor::form').getByRole('button', { name: 'Save' }).click()

			// The change goes to POST /apps/planninq/api/settings, in the same shape.
			const request = await saved
			expect(request.postDataJSON()).toMatchObject({ allow_project_creation: next })
			await expect(page.getByText('Creation policy saved successfully')).toBeVisible()
			await expect.poll(read).toBe(next)

			// And it is still there after a reload.
			await page.reload()
			await expect(page.locator('#allow-project-creation')).toHaveValue(next)
		} finally {
			await admin.post(SETTINGS, { data: { allow_project_creation: before }, headers: { 'Content-Type': 'application/json' } })
			await admin.dispose()
		}
	})
})
