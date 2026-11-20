/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for linking a file that already lives in Nextcloud Files to a
 * task (task-attachments-from-files). The member picks the file through the
 * Nextcloud file picker on the task page; the task stores a reference to the
 * file's path, and the file stays in its own folder with no copy made.
 *
 *   @e2e task-collaboration::attach-a-file-from-files
 *
 * Runs as a regular user the spec creates, in a context of its own with an
 * empty storage state, never as the admin.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const FOLDER = `Vergaderingen-${RUN}`
const FILE = 'Presentielijst.docx'

/**
 * An API context signed in as `username`.
 *
 * @param username The user id.
 */
async function userApi(username: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password: PASSWORD, send: 'always' },
		// Without an empty state the project's admin session cookie signs the request in as the admin.
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * The names of the entries directly in one of the user's folders (WebDAV PROPFIND, depth 1).
 *
 * @param api The user's context.
 * @param user The user id.
 * @param folder The folder path, '' for the root.
 */
async function folderEntries(api: APIRequestContext, user: string, folder: string): Promise<string[]> {
	const base = `/remote.php/dav/files/${user}/${folder ? `${encodeURIComponent(folder)}/` : ''}`
	const res = await api.fetch(base, { method: 'PROPFIND', headers: { Depth: '1', Accept: 'application/xml' } })
	expect(res.status(), `propfind ${folder || '/'}`).toBe(207)
	const xml = await res.text()
	return [...xml.matchAll(/<d:href>([^<]+)<\/d:href>/g)]
		.map((m) => decodeURIComponent(m[1]))
		.filter((href) => href !== base && decodeURIComponent(href) !== decodeURIComponent(base))
		.map((href) => href.replace(/\/$/, '').split('/').pop() as string)
}

test.describe('Files from Files on a task', () => {
	test('a member attaches a file that is already in Files', async ({ browser }) => {
		const admin = await adminApi()
		const member = `bestand-${RUN}`
		let project = ''
		let task = ''
		try {
			expect((await admin.post('/ocs/v2.php/cloud/users', { data: { userid: member, password: PASSWORD } })).ok()).toBe(true)
			const api = await userApi(member)

			// GIVEN a file "Presentielijst.docx" in the member's Nextcloud Files.
			expect((await api.fetch(`/remote.php/dav/files/${member}/${FOLDER}/`, { method: 'MKCOL' })).status()).toBe(201)
			const put = await api.put(`/remote.php/dav/files/${member}/${FOLDER}/${FILE}`, {
				data: Buffer.from(`Presentielijst ${RUN}`),
				headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' },
			})
			expect([201, 204]).toContain(put.status())

			// AND a project the member owns (so is a member of), with one task.
			const made = await api.post('/index.php/apps/planninq/api/projects', { data: { title: `Bestanden ${RUN}` }, headers: { 'Content-Type': 'application/json' } })
			expect(made.status()).toBe(201)
			project = (await made.json()).id
			const created = await api.post(`${OBJECTS}/task`, { data: { title: `Vergadering voorbereiden ${RUN}`, status: 'open', project }, headers: { 'Content-Type': 'application/json' } })
			expect(created.ok(), `task: ${created.status()}`).toBe(true)
			task = (await created.json()).id

			// WHEN they choose "Pick from Files" on the task and select that file.
			const { context, page } = await signedInPage(browser, member, PASSWORD)
			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			const section = page.getByTestId('task-linked-files')
			await expect(section.getByTestId('cn-related-files-empty')).toBeVisible()
			await section.getByRole('button', { name: 'Pick from Files' }).click()

			const picker = page.getByRole('dialog', { name: /Pick from Files/ })
			await expect(picker).toBeVisible()
			await picker.getByRole('row', { name: new RegExp(FOLDER) }).getByRole('button', { name: new RegExp(FOLDER) }).click()
			await picker.getByRole('row', { name: new RegExp(FILE.replace('.', '\\.')) }).click()
			await picker.getByRole('button', { name: /^Choose/ }).click()
			await expect(picker).toBeHidden()

			// THEN the task lists "Presentielijst.docx" with its folder in Files.
			const row = section.locator(`[data-file-path="/${FOLDER}/${FILE}"]`)
			await expect(row.getByTestId('cn-related-files-open')).toHaveText(FILE)
			await expect(row).toContainText(`/${FOLDER}/${FILE}`)

			// The reference survives a reload: it is stored on the task, not held in the page.
			await page.reload()
			await expect(page.getByTestId('task-linked-files').locator(`[data-file-path="/${FOLDER}/${FILE}"]`)).toBeVisible()
			await context.close()

			const stored = await (await api.get(`${OBJECTS}/task/${task}`)).json()
			expect(stored.linkedFiles).toEqual([{ path: `/${FOLDER}/${FILE}`, name: FILE }])

			// AND the file stays in its folder, with no copy made.
			expect(await folderEntries(api, member, FOLDER)).toEqual([FILE])
			expect(await folderEntries(api, member, '')).not.toContain(FILE)
			await api.dispose()
		} finally {
			if (task) {
				await admin.delete(`${OBJECTS}/task/${task}`, { failOnStatusCode: false })
			}
			if (project) {
				await admin.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
			}
			await admin.delete(`/ocs/v2.php/cloud/users/${member}`, { failOnStatusCode: false })
			await admin.dispose()
		}
	})
})
