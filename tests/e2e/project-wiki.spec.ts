/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the project wiki (projects-wiki).
 *
 *   @e2e project-wiki::writing-the-first-page
 *   @e2e project-wiki::a-viewer-reads-but-cannot-write
 *   @e2e project-wiki::moving-a-page-under-another-page
 *   @e2e project-wiki::a-page-cannot-move-under-its-own-subpage
 *   @e2e project-wiki::browsing-the-tree-by-keyboard
 *   @e2e project-wiki::restoring-an-earlier-version
 *   @e2e project-wiki::a-second-writer-sees-the-page-is-being-edited
 *
 * Every person in the UI is a regular user with an empty storage state, so the
 * project's roles decide what they see. The admin context seeds the project and
 * its pages through the API and removes them afterwards.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`

/**
 * Create a regular Nextcloud user.
 *
 * @param api The admin context.
 * @param userid The user id.
 * @param displayName The display name.
 */
async function createUser(api: APIRequestContext, userid: string, displayName = userid): Promise<void> {
	expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid, password: PASSWORD, displayName } })).ok()).toBe(true)
}

/**
 * An API context signed in as `username`, without the admin's stored session.
 *
 * @param username The user id.
 */
async function userApi(username: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password: PASSWORD, send: 'always' },
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * The wiki URL of a project, or of one of its pages.
 *
 * @param project The project id.
 * @param page The page id, if any.
 */
function wikiUrl(project: string, page?: string): string {
	return new URL(page ? `projects/${project}/wiki/${page}` : `projects/${project}/wiki`, PLANNINQ_ROOT).toString()
}

/**
 * Write a page body in whichever editor the instance offers: Nextcloud Text
 * when it is installed, the Markdown field otherwise.
 *
 * @param page The page with the editor open.
 * @param text The body to write.
 */
async function writeBody(page: Page, text: string): Promise<void> {
	const editor = page.getByTestId('wiki-page-editor')
	const markdown = editor.getByTestId('wiki-page-markdown')
	if (await markdown.count()) {
		await markdown.locator('textarea').fill(text)
		return
	}
	const rich = editor.getByTestId('wiki-page-text-editor').locator('[contenteditable="true"]').first()
	await rich.click()
	await page.keyboard.type(text)
}

/**
 * The tree item of a page title.
 *
 * @param page The wiki page.
 * @param title The page title.
 */
function treeItem(page: Page, title: string) {
	return page.getByTestId('wiki-tree').getByRole('treeitem', { name: new RegExp(title) })
}

test.describe('Project wiki', () => {
	let api: APIRequestContext
	let project = ''
	const made: Array<[string, string]> = []
	const writer = `schrijver-${RUN}`
	const second = `tweede-${RUN}`
	const viewer = `lezer-${RUN}`
	const manager = `wikibeheer-${RUN}`

	test.beforeAll(async () => {
		api = await adminApi()
		await createUser(api, writer, `Ada Jansen ${RUN}`)
		await createUser(api, second)
		await createUser(api, viewer)
		await createUser(api, manager)
		project = await createObject(api, 'project', {
			title: `Wiki ${RUN}`,
			status: 'active',
			owner: ADMIN_USER,
			managers: [manager],
			members: [ADMIN_USER, writer, second, manager],
			viewers: [viewer],
		})
	})

	test.afterAll(async () => {
		const pages = (await (await api.get(`${OBJECTS}/wikiPage?project=${project}&_limit=200`)).json()).results ?? []
		// Subpages first, so no page is removed while another still points at it.
		for (const page of [...pages].sort((a, b) => Number(Boolean(b.parent)) - Number(Boolean(a.parent)))) {
			await api.delete(`${OBJECTS}/wikiPage/${page.id}`, { failOnStatusCode: false })
		}
		await removeObjects(api, made)
		if (project) {
			await api.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
		}
		for (const userid of [writer, second, viewer, manager]) {
			await api.delete(`/ocs/v2.php/cloud/users/${userid}`)
		}
	})

	// @e2e project-wiki::writing-the-first-page
	test('writing the first page', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, writer, PASSWORD)
		await page.goto(wikiUrl(project))
		await expect(page.getByText('This project has no wiki pages yet.')).toBeVisible()
		await page.getByTestId('wiki-add-page').click()
		await page.getByTestId('wiki-page-title').locator('input').fill('Werkafspraken')
		await writeBody(page, 'We **plannen** elke maandag.')
		await page.getByTestId('wiki-page-save').click()

		await page.waitForURL(/\/wiki\/[0-9a-f-]{36}/)
		await expect(page.getByTestId('wiki-page')).toContainText('Werkafspraken')
		await expect(page.getByTestId('wiki-body').locator('strong')).toHaveText('plannen')
		await expect(treeItem(page, 'Werkafspraken')).toBeVisible()

		// An attached image is stored on the page and rendered in its body.
		await page.getByTestId('wiki-edit').click()
		await page.getByTestId('wiki-page-file-input').setInputFiles({
			name: `pixel-${RUN}.png`,
			mimeType: 'image/png',
			buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/iZk9HQAAAABJRU5ErkJggg==', 'base64'),
		})
		await page.getByTestId('wiki-page-save').click()
		await expect(page.getByTestId('wiki-body').locator('img')).toHaveCount(1)
		await context.close()
	})

	// @e2e project-wiki::a-viewer-reads-but-cannot-write
	test('a viewer reads but cannot write', async ({ browser }) => {
		const wiki = await createObject(api, 'wikiPage', { project, title: `Leesvoer ${RUN}`, body: 'Alleen lezen.', order: 10 })
		const { context, page } = await signedInPage(browser, viewer, PASSWORD)
		await page.goto(wikiUrl(project, wiki))
		await expect(page.getByTestId('wiki-body')).toContainText('Alleen lezen.')
		await expect(page.getByTestId('wiki-edit')).toHaveCount(0)
		await expect(page.getByTestId('wiki-add-page')).toHaveCount(0)
		await context.close()

		const viewerApi = await userApi(viewer)
		const put = await viewerApi.put(`${OBJECTS}/wikiPage/${wiki}`, { data: { project, title: `Leesvoer ${RUN}`, body: 'Overschreven.', order: 10 } })
		expect(put.status()).toBe(403)
		await viewerApi.dispose()
	})

	// @e2e project-wiki::moving-a-page-under-another-page
	// @e2e project-wiki::a-page-cannot-move-under-its-own-subpage
	test('moving a page under another page, and not under its own subpage', async ({ browser }) => {
		const top = await createObject(api, 'wikiPage', { project, title: `Afspraken ${RUN}`, body: 'Boven.', order: 20 })
		const overleg = await createObject(api, 'wikiPage', { project, title: `Overleg ${RUN}`, body: 'Elke week.', order: 30 })

		const { context, page } = await signedInPage(browser, writer, PASSWORD)
		await page.goto(wikiUrl(project, overleg))
		await page.getByRole('button', { name: `Actions for Overleg ${RUN}` }).click()
		await page.getByRole('menuitem', { name: 'Move', exact: true }).click()
		const dialog = page.getByTestId('wiki-move-dialog')
		await dialog.getByTestId('wiki-move-target').click()
		await page.getByRole('option', { name: `Afspraken ${RUN}` }).click()
		await dialog.getByTestId('wiki-move-confirm').click()

		await expect.poll(async () => (await (await api.get(`${OBJECTS}/wikiPage/${overleg}`)).json()).parent).toBe(top)
		await expect(treeItem(page, `Overleg ${RUN}`)).toHaveAttribute('aria-level', '2')
		await expect(page.getByTestId('wiki-breadcrumb')).toHaveText(`Afspraken ${RUN} / Overleg ${RUN}`)

		// The move dialog of the parent does not offer its own subpage.
		await page.goto(wikiUrl(project, top))
		await page.getByRole('button', { name: `Actions for Afspraken ${RUN}` }).click()
		await page.getByRole('menuitem', { name: 'Move', exact: true }).click()
		await page.getByTestId('wiki-move-dialog').getByTestId('wiki-move-target').click()
		await expect(page.getByRole('option', { name: 'Top level' })).toBeVisible()
		await expect(page.getByRole('option', { name: `Overleg ${RUN}` })).toHaveCount(0)
		await context.close()

		// The object API refuses the same parent.
		const writerApi = await userApi(writer)
		const patch = await writerApi.patch(`${OBJECTS}/wikiPage/${top}`, { data: { parent: overleg } })
		expect(patch.ok()).toBe(false)
		await writerApi.dispose()
		expect((await (await api.get(`${OBJECTS}/wikiPage/${top}`)).json()).parent ?? null).toBe(null)
	})

	// @e2e project-wiki::browsing-the-tree-by-keyboard
	test('browsing the tree by keyboard', async ({ browser }) => {
		const parent = await createObject(api, 'wikiPage', { project, title: `Aaa start ${RUN}`, body: 'Ouder.', order: -10 })
		const child = await createObject(api, 'wikiPage', { project, title: `Kind ${RUN}`, body: 'Het kind.', parent, order: 0 })

		const { context, page } = await signedInPage(browser, second, PASSWORD)
		await page.goto(wikiUrl(project))
		const tree = page.getByTestId('wiki-tree')
		await tree.getByRole('treeitem').first().focus()
		const parentItem = treeItem(page, `Aaa start ${RUN}`)
		for (let step = 0; step < 20 && !(await parentItem.evaluate((node) => node === document.activeElement)); step++) {
			await page.keyboard.press('ArrowDown')
		}
		await expect(parentItem).toBeFocused()
		await page.keyboard.press('ArrowRight')
		await expect(parentItem).toHaveAttribute('aria-expanded', 'true')
		await page.keyboard.press('ArrowDown')
		await expect(treeItem(page, `Kind ${RUN}`)).toBeFocused()
		await page.keyboard.press('Enter')
		await page.waitForURL(new RegExp(`/wiki/${child}`))
		await expect(page.getByTestId('wiki-body')).toContainText('Het kind.')
		await expect(parentItem).toHaveAttribute('aria-expanded', 'true')
		await context.close()
	})

	// @e2e project-wiki::restoring-an-earlier-version
	test('restoring an earlier version', async ({ browser }) => {
		const wiki = await createObject(api, 'wikiPage', { project, title: `Versies ${RUN}`, body: 'Versie een.', order: 40 })
		for (const body of ['Versie twee.', 'Versie drie.']) {
			expect((await api.patch(`${OBJECTS}/wikiPage/${wiki}`, { data: { body } })).ok()).toBe(true)
		}

		const { context, page } = await signedInPage(browser, manager, PASSWORD)
		await page.goto(wikiUrl(project, wiki))
		await expect(page.getByTestId('wiki-body')).toContainText('Versie drie.')
		await page.getByTestId('wiki-history').click()
		const history = page.getByTestId('wiki-history-list')
		const restores = history.locator('[data-testid^="wiki-restore-"]')
		await expect(restores.first()).toBeVisible()
		const before = await history.getByRole('listitem').count()
		// The list is newest first, so the first version is the last entry.
		await restores.last().click()

		await expect(page.getByTestId('wiki-body')).toContainText('Versie een.')
		await expect.poll(async () => (await (await api.get(`${OBJECTS}/wikiPage/${wiki}`)).json()).body).toBe('Versie een.')
		await page.getByTestId('wiki-history').click()
		await page.getByTestId('wiki-history').click()
		await expect.poll(() => page.getByTestId('wiki-history-list').getByRole('listitem').count()).toBeGreaterThan(before)
		await context.close()
	})

	// @e2e project-wiki::a-second-writer-sees-the-page-is-being-edited
	test('a second writer sees the page is being edited', async ({ browser }) => {
		const wiki = await createObject(api, 'wikiPage', { project, title: `Samen ${RUN}`, body: 'Een tekst.', order: 50 })

		const ada = await signedInPage(browser, writer, PASSWORD)
		await ada.page.goto(wikiUrl(project, wiki))
		await ada.page.getByTestId('wiki-edit').click()
		await expect(ada.page.getByTestId('wiki-page-editor')).toBeVisible()

		const other = await signedInPage(browser, second, PASSWORD)
		await other.page.goto(wikiUrl(project, wiki))
		await expect(other.page.getByTestId('wiki-locked')).toHaveText(`Ada Jansen ${RUN} is editing this page`)
		await expect(other.page.getByTestId('wiki-edit')).toBeDisabled()

		await ada.page.getByTestId('wiki-page-cancel').click()
		await other.page.reload()
		await expect(other.page.getByTestId('wiki-locked')).toHaveCount(0)
		await expect(other.page.getByTestId('wiki-edit')).toBeEnabled()
		await ada.context.close()
		await other.context.close()
	})
})
