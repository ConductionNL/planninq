/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for project requests (projects-lifecycle-policy, section 3).
 * The admin is the reviewer; the requester is a user outside the creation
 * policy, driven through the API with its own credentials.
 *
 *   @e2e project-lifecycle::a-requester-fills-in-the-guided-form
 *   @e2e project-lifecycle::a-reviewer-approves-a-request
 *   @e2e project-lifecycle::a-reviewer-rejects-a-request-with-a-reason
 */

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const SETTINGS = '/index.php/apps/planninq/api/settings'
const PASSWORD = `Planninq-e2e-${RUN}!`
const REQUESTER = `aanvrager-${RUN}`

/**
 * An API context signed in as the requester.
 */
async function requesterApi() {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: REQUESTER, password: PASSWORD, send: 'always' },
		extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

test.describe('Project requests', () => {
	let api
	const made: string[] = []

	test.beforeAll(async () => {
		api = await adminApi()
		expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid: REQUESTER, password: PASSWORD } })).ok()).toBe(true)
		expect((await api.post(SETTINGS, { data: { allow_project_creation: 'admins', project_requests: 'on' } })).ok()).toBe(true)
	})

	test.afterAll(async () => {
		await api.post(SETTINGS, { data: { allow_project_creation: 'all', project_requests: 'off' } })
		for (const id of made) {
			await api.delete(`${OBJECTS}/project/${id}`)
		}
		await api.delete(`/ocs/v2.php/cloud/users/${REQUESTER}`)
	})

	/**
	 * Send a request as the requester, the way the form does.
	 *
	 * @param title The project title.
	 */
	async function sendRequest(title: string): Promise<string> {
		const ctx = await requesterApi()
		const settings = await (await ctx.get(SETTINGS)).json()
		expect(settings.canCreateProject).toBe(false)
		expect(settings.canRequestProject).toBe(true)
		const res = await ctx.post('/index.php/apps/planninq/api/projects', { data: { title, requestReason: 'Residents ask for it', startDate: '2027-01-04' } })
		expect(res.status()).toBe(201)
		const project = await res.json()
		expect(project.status).toBe('requested')
		await ctx.dispose()
		made.push(project.id)
		return project.id
	}

	test('a requester fills in the guided form', async ({ page }) => {
		// The form itself, as the admin sees it when creation is open to admins only: the admin may create,
		// so the form is exercised through the requester's API call and the list and board as the reviewer.
		const id = await sendRequest(`Portaal ${RUN}`)
		await page.goto(new URL(`projects/${id}`, PLANNINQ_ROOT).toString())
		await expect(page.getByTestId('project-request-banner')).toContainText('This project is waiting for review')
		await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
		await page.locator('.project-list__actions').getByText('Requested', { exact: true }).click()
		await expect(page.getByText(`Portaal ${RUN}`)).toBeVisible()
	})

	test('a reviewer approves a request', async ({ page }) => {
		const id = await sendRequest(`Goedkeuren ${RUN}`)
		await page.goto(new URL(`projects/${id}`, PLANNINQ_ROOT).toString())
		await page.getByTestId('project-request-review').click()
		await page.getByTestId('project-request-approve').click()
		await expect(page.getByTestId('project-request-banner')).toHaveCount(0)
		const project = await (await api.get(`${OBJECTS}/project/${id}`)).json()
		expect(project.status).toBe('active')
		expect(project.reviewedBy).toBeTruthy()
		const columns = await (await api.get(`${OBJECTS}/column?project=${id}`)).json()
		expect((columns.results ?? []).length).toBeGreaterThan(0)
	})

	test('a reviewer rejects a request with a reason', async ({ page }) => {
		const id = await sendRequest(`Afwijzen ${RUN}`)
		await page.goto(new URL(`projects/${id}`, PLANNINQ_ROOT).toString())
		await page.getByTestId('project-request-review').click()
		await page.getByTestId('project-request-reject').click()
		await page.getByTestId('project-request-note').locator('textarea').fill('Fits in the existing portal project')
		await page.getByTestId('project-request-confirm-reject').click()
		await expect(page.getByTestId('project-request-banner')).toContainText('This request was not approved')
		await expect(page.getByTestId('project-request-reason')).toContainText('Fits in the existing portal project')
		const project = await (await api.get(`${OBJECTS}/project/${id}`)).json()
		expect(project.status).toBe('rejected')
	})
})
