/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for an image pasted into a task description (task-description-images).
 *
 *   @e2e task-editing::paste-a-screenshot
 *
 * A regular (non-admin) user owns the project, so they are a project member, and
 * edits the task in a fresh browser context with an empty storage state. The paste
 * is a real `paste` event carrying a PNG in its clipboard data, dispatched on the
 * description text area of the edit dialog: Playwright cannot write an image to
 * the system clipboard of a headless browser, and the dialog reads only the event.
 */

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const TITLE = 'Bewonersavond voorbereiden'

// A 1x1 PNG, the smallest screenshot there is.
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='

/**
 * An API context signed in as `username`.
 *
 * @param username The user id.
 */
async function userApi(username: string) {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password: PASSWORD, send: 'always' },
		// Without an empty state the project's admin session cookie signs the request in as the admin.
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

test.describe('Task description images', () => {
	test('a member pastes a screenshot into the description', async ({ browser }) => {
		const api = await adminApi()
		const member = `lid-${RUN}`
		let project = ''
		let task = ''
		try {
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: member, password: PASSWORD } })).ok()).toBe(true)
			const memberApi = await userApi(member)
			const res = await memberApi.post('/index.php/apps/planninq/api/projects', { data: { title: `Wijkavond ${RUN}` } })
			expect(res.status()).toBe(201)
			project = (await res.json()).id
			const made = await memberApi.post(`${OBJECTS}/task`, { data: { title: TITLE, status: 'open', project, description: 'Zie de schermafbeelding.' } })
			expect(made.ok()).toBe(true)
			const body = await made.json()
			task = body.id ?? body['@self']?.id
			expect(task).toBeTruthy()

			const { context, page } = await signedInPage(browser, member, PASSWORD)
			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			await expect(page.getByRole('heading', { name: new RegExp(TITLE) })).toBeVisible()
			await page.getByTestId('task-edit').click()

			const area = page.getByTestId('task-form-description-drop').locator('textarea')
			await expect(area).toBeVisible()
			await area.click()
			await area.press('End')
			await area.evaluate((el, png) => {
				const bytes = Uint8Array.from(atob(png), (c) => c.charCodeAt(0))
				const data = new DataTransfer()
				data.items.add(new File([bytes], 'schermafbeelding.png', { type: 'image/png' }))
				el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }))
			}, PNG)

			// The upload answers, then the Markdown image link lands in the text.
			await expect(area).toHaveValue(/!\[schermafbeelding\.png\]\([^)]*\/files\/\d+\/preview[^)]*\)/)
			await expect(page.getByTestId('task-form-image-notice')).toHaveCount(0)
			await page.getByTestId('task-form-save').click()

			// THEN the description shows the image.
			const description = page.getByTestId('task-description')
			await expect(description.getByRole('img', { name: 'schermafbeelding.png' })).toBeVisible()
			const stored = await (await memberApi.get(`${OBJECTS}/task/${task}`)).json()
			expect(stored.description).toMatch(/^Zie de schermafbeelding\.\n?[\s\S]*!\[schermafbeelding\.png\]\(/)

			// AND the image is listed on the Files tab of the task.
			const files = await (await memberApi.get(`${OBJECTS}/task/${task}/files`)).json()
			const names = (files.results ?? files ?? []).map((f: { title?: string, name?: string }) => f.title ?? f.name)
			expect(names).toContain('schermafbeelding.png')
			await page.getByRole('tab', { name: /(Attachments|Files)/i }).click()
			await expect(page.getByRole('tabpanel', { name: /(Attachments|Files)/i }).getByText('schermafbeelding.png')).toBeVisible()

			await context.close()
			await memberApi.dispose()
		} finally {
			if (task) {
				await api.delete(`${OBJECTS}/task/${task}`)
			}
			if (project) {
				await api.delete(`${OBJECTS}/project/${project}`)
			}
			await api.delete(`/ocs/v2.php/cloud/users/${member}`)
			await api.dispose()
		}
	})
})
