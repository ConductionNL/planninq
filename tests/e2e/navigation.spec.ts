/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the manifest-derived shell (adopt-cnapproot-manifest-shell):
 * the navigation is the manifest's menu[] rendered by CnAppRoot, and the
 * routes come from its pages[].
 *
 *   @e2e planninq-app-shell::navigation-renders-from-the-manifest
 *   @e2e planninq-app-shell::all-five-routes-remain-reachable
 *   @e2e planninq-app-shell::unknown-route-falls-back-to-dashboard
 *
 * Runs as a regular user the spec creates, in a context of its own with an
 * empty storage state, so the admin-only menu entries must be absent.
 */

import type { Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

type MenuItem = { id: string, label: string, section?: string, permission?: string, order?: number }

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const MENU: MenuItem[] = JSON.parse(readFileSync(join(__dirname, '..', '..', 'src', 'manifest.json'), 'utf8')).menu

/**
 * The vertical position of a menu entry, or null when it is not shown.
 *
 * @param page The page.
 * @param id The menu item id.
 */
async function entryTop(page: Page, id: string): Promise<number | null> {
	const entry = page.locator(`[data-testid="cn-nav"] [data-testid="cn-nav-entry-${id}"]`).first()
	if ((await entry.count()) === 0) {
		return null
	}
	return (await entry.boundingBox())?.y ?? null
}

test.describe('Manifest-derived shell', () => {
	test.describe.configure({ mode: 'serial', timeout: 120_000 })

	const user = `shell-${RUN}`
	const made: { project?: string, tasks?: string[] } = {}

	test.beforeAll(async () => {
		const admin = await adminApi()
		expect((await admin.post('/ocs/v2.php/cloud/users', { data: { userid: user, password: PASSWORD } })).ok()).toBe(true)
		await admin.dispose()
		const api = await request.newContext({
			baseURL: BASE_URL,
			httpCredentials: { username: user, password: PASSWORD, send: 'always' },
			// Without an empty state the project's admin session cookie signs the request in as the admin.
			storageState: { cookies: [], origins: [] },
			extraHTTPHeaders: { Accept: 'application/json', 'Content-Type': 'application/json', 'OCS-APIRequest': 'true' },
		})
		const created = await api.post('/index.php/apps/planninq/api/projects', { data: { title: `Shell ${RUN}` } })
		expect(created.status()).toBe(201)
		made.project = String((await created.json()).id)
		const task = await api.post(`${OBJECTS}/task`, { data: { title: `Check the drawings ${RUN}`, status: 'open', project: made.project } })
		expect(task.ok(), `task: ${task.status()}`).toBe(true)
		const body = await task.json()
		made.tasks = [String(body.id ?? body['@self']?.id)]
		await api.dispose()
	})

	test.afterAll(async () => {
		const admin = await adminApi()
		for (const task of made.tasks ?? []) {
			await admin.delete(`${OBJECTS}/task/${task}`, { failOnStatusCode: false })
		}
		if (made.project) {
			await admin.delete(`${OBJECTS}/project/${made.project}`, { failOnStatusCode: false })
		}
		await admin.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
		await admin.dispose()
	})

	test('the navigation renders from the manifest', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(PLANNINQ_ROOT)
		const nav = page.locator('[data-testid="cn-nav"]')
		await expect(nav).toBeVisible({ timeout: 30_000 })

		// Every main entry a regular user may see is there, in the manifest's order.
		const main = MENU.filter((item) => !item.section && !item.permission).sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
		const tops: number[] = []
		for (const item of main) {
			const top = await entryTop(page, item.id)
			expect(top, `menu entry ${item.id} is shown`).not.toBeNull()
			await expect(nav.locator(`[data-testid="cn-nav-entry-${item.id}"]`).first()).toContainText(item.label)
			tops.push(top as number)
		}
		expect([...tops].sort((a, b) => a - b)).toEqual(tops)

		// The footer entries are there too.
		for (const item of MENU.filter((entry) => entry.section === 'footer' && !entry.permission)) {
			await expect(nav.locator('.cn-app-nav__footer-list')).toContainText(item.label)
		}

		// Admin-only entries are not offered to a regular user.
		for (const item of MENU.filter((entry) => entry.permission === 'admin')) {
			await expect(nav.locator(`[data-testid="cn-nav-entry-${item.id}"]`)).toHaveCount(0)
		}

		// No navigation entry is rendered outside the shared shell's navigation.
		const everywhere = await page.locator('.app-navigation-entry').count()
		const inShell = await nav.locator('.app-navigation-entry').count()
		expect(everywhere).toBe(inShell)
		await context.close()
	})

	test('all five routes remain reachable', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		const project = made.project as string
		const task = (made.tasks as string[])[0]
		const routes: Array<[string, () => Promise<void>]> = [
			['', () => expect(page.getByTestId('cn-dashboard-page')).toBeVisible({ timeout: 30_000 })],
			['projects', () => expect(page.getByText(`Shell ${RUN}`).first()).toBeVisible({ timeout: 30_000 })],
			[`projects/${project}`, () => expect(page.getByTestId('board-search')).toBeVisible({ timeout: 30_000 })],
			[`projects/${project}/backlog`, () => expect(page.getByTestId('backlog-search')).toBeVisible({ timeout: 30_000 })],
			[`projects/${project}/tasks/${task}`, () => expect(page.getByTestId('log-time')).toBeVisible({ timeout: 30_000 })],
		]
		for (const [path, shown] of routes) {
			const url = new URL(path, PLANNINQ_ROOT).toString()
			await page.goto(url)
			await shown()
			expect(page.url().replace(/[?#].*$/, '').replace(/\/$/, '')).toBe(url.replace(/\/$/, ''))
		}
		await context.close()
	})

	test('an unknown route falls back to the dashboard', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL(`no-such-page-${RUN}/deeper`, PLANNINQ_ROOT).toString())
		await expect(page).toHaveURL(/\/apps\/planninq\/?(\?|#|$)/, { timeout: 30_000 })
		await expect(page.getByTestId('cn-dashboard-page')).toBeVisible({ timeout: 30_000 })
		await context.close()
	})
})
