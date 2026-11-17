/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * projects-public-share task 2.1: a read-only board opened by link, by a
 * visitor without an account.
 *
 * The share is made the way decision 1 describes it: an OpenRegister view on
 * the project's columns and tasks, and a `read` access link on that view. The
 * password and revoke cases mint through OpenRegister's API as the project's
 * owner; "the Share tab creates and switches off a link" drives the Share tab
 * (task 3.1) as the owner. The visitor's half runs in a
 * fresh browser context with an empty storage state, so no session leaks in.
 * Base URL and credentials come from the environment (tests/e2e/base-url.ts,
 * tests/e2e/portfolio-api.ts).
 */

import type { APIRequestContext, Browser } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, createObject, removeObjects } from './portfolio-api.ts'

const OR = '/index.php/apps/openregister/api'

type Share = { anchor: string, linkId: number, viewId: string }

async function shareBoard(api: APIRequestContext, projectId: string, title: string, password?: string): Promise<Share> {
	const view = await api.post(`${OR}/views`, {
		data: { name: title, query: { registers: ['planninq'], schemas: ['column', 'task'], filters: { project: projectId } } },
	})
	expect(view.ok(), `view: ${view.status()}`).toBe(true)
	const viewBody = await view.json()
	const created = viewBody.view ?? viewBody
	const viewId = String(created.uuid ?? created.id)

	const expiresAt = new Date(Date.now() + 7 * 24 * 3600 * 1000).toISOString()
	const link = await api.post(`${OR}/access-links`, {
		data: { subjectType: 'view', subjectId: viewId, capabilities: ['read'], expiresAt, label: title, ...(password ? { password } : {}) },
	})
	expect(link.status(), 'mint').toBe(201)
	const linkBody = await link.json()
	const anchor = String(linkBody.anchor ?? linkBody.link?.anchor ?? String(linkBody.url ?? '').split('/').pop())

	return { anchor, linkId: Number(linkBody.id ?? linkBody.link?.id), viewId }
}

async function visitorPage(browser: Browser) {
	const context = await browser.newContext({ baseURL: BASE_URL, storageState: { cookies: [], origins: [] } })
	return { context, page: await context.newPage() }
}

test.describe('a project board shared by link', () => {
	let api: APIRequestContext
	const made: Array<[string, string]> = []
	let projectId = ''
	const title = `Fietspad ${Date.now()}`

	test.beforeAll(async () => {
		api = await adminApi()
		projectId = await createObject(api, 'project', { title, key: `FP${Date.now() % 100000}` })
		made.push(['project', projectId])
		const columnId = await createObject(api, 'column', { title: 'To do', project: projectId, order: 1 })
		made.push(['column', columnId])
		const taskId = await createObject(api, 'task', {
			title: 'Paint the lines',
			project: projectId,
			column: columnId,
			columnOrder: 1,
			status: 'open',
			description: 'Ask Pieter for the paint',
			labels: ['road'],
		})
		made.push(['task', taskId])
	})

	test.afterAll(async () => {
		await removeObjects(api, made)
		await api.dispose()
	})

	test('creating a link and opening it without an account', async ({ browser }) => {
		const share = await shareBoard(api, projectId, title)
		const { context, page } = await visitorPage(browser)
		try {
			const response = await page.goto(`/index.php/apps/planninq/public/${share.anchor}`)
			expect(response?.headers()['x-robots-tag']).toContain('noindex')
			await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/)
			await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible()
			await expect(page.getByRole('region', { name: 'To do' })).toContainText('Paint the lines')
			await expect(page.getByText('road')).toBeVisible()
			// People and free text never leave through a link (decision 3).
			await expect(page.getByText('Ask Pieter for the paint')).toHaveCount(0)
			await expect(page.locator('form:not(.planninq-public-board__password), textarea')).toHaveCount(0)
		} finally {
			await context.close()
			await api.delete(`${OR}/access-links/${share.linkId}`, { failOnStatusCode: false })
			await api.delete(`${OR}/views/${share.viewId}`, { failOnStatusCode: false })
		}
	})

	test('a password-protected link', async ({ browser }) => {
		const share = await shareBoard(api, projectId, title, 'fiets-2026')
		const { context, page } = await visitorPage(browser)
		try {
			await page.goto(`/index.php/apps/planninq/public/${share.anchor}`)
			await expect(page.getByText('This board is closed with a password.')).toBeVisible()
			await page.getByLabel('Password').fill('wrong')
			await page.getByRole('button', { name: 'Open board' }).click()
			await expect(page.getByRole('alert')).toContainText('That password is not right.')
			await page.getByLabel('Password').fill('fiets-2026')
			await page.getByRole('button', { name: 'Open board' }).click()
			await expect(page.getByRole('region', { name: 'To do' })).toContainText('Paint the lines')
		} finally {
			await context.close()
			await api.delete(`${OR}/access-links/${share.linkId}`, { failOnStatusCode: false })
			await api.delete(`${OR}/views/${share.viewId}`, { failOnStatusCode: false })
		}
	})

	test('the Share tab creates a link and switches it off', async ({ browser, page }) => {
		await page.goto(new URL(`projects/${projectId}`, PLANNINQ_ROOT).toString())
		await page.getByRole('button', { name: 'Project settings' }).click()
		await page.getByRole('tab', { name: 'Share' }).click()
		await page.getByRole('button', { name: 'Create link' }).click()
		const url = await page.getByLabel('Link', { exact: true }).inputValue()
		expect(url).toMatch(/\/apps\/planninq\/public\/[A-Za-z0-9]+$/)
		await expect(page.getByRole('button', { name: 'Copy link' })).toBeVisible()
		const listed = page.getByTestId('project-share-links').getByRole('listitem')
		await expect(listed).toHaveCount(1)

		const { context, page: visitor } = await visitorPage(browser)
		try {
			await visitor.goto(url)
			await expect(visitor.getByRole('region', { name: 'To do' })).toContainText('Paint the lines')
			await expect(visitor.getByText('Ask Pieter for the paint')).toHaveCount(0)

			await listed.first().getByRole('button', { name: 'Switch off' }).click()
			await expect(page.getByTestId('project-share-empty')).toBeVisible()
			await visitor.reload()
			await expect(visitor.getByRole('alert')).toContainText('This link does not work any more.')
		} finally {
			await context.close()
		}
	})

	test('a revoked link stops working', async ({ browser }) => {
		const share = await shareBoard(api, projectId, title)
		await api.delete(`${OR}/access-links/${share.linkId}`)
		const { context, page } = await visitorPage(browser)
		try {
			await page.goto(`/index.php/apps/planninq/public/${share.anchor}`)
			await expect(page.getByRole('alert')).toContainText('This link does not work any more.')
			await expect(page.getByText('Paint the lines')).toHaveCount(0)
		} finally {
			await context.close()
			await api.delete(`${OR}/views/${share.viewId}`, { failOnStatusCode: false })
		}
	})
})
