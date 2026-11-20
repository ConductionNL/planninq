/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for setting and showing the case link of projects and tasks
 * (case-link-editing): the Case field on the Details tab of the project
 * settings sidebar, the case badge on the project list and overview, and the
 * Case field on the task page.
 *
 *   @e2e procest-integration::an-owner-links-a-project-to-a-case
 *   @e2e procest-integration::an-owner-clears-the-link
 *   @e2e procest-integration::a-member-cannot-change-the-link
 *   @e2e procest-integration::a-member-links-a-task-to-a-case
 *   @e2e exclude procest-integration::the-case-app-is-not-installed the shared instance runs with the case app; tests/vitest/caseLink.spec.js covers caseAppInstalled() for an instance without it
 *
 * The scenarios need the case app (dossiq) next to planninq; without it the
 * suite skips. Every page runs as a regular user the spec creates, in a
 * context of its own with an empty storage state, never as the admin. The
 * admin only creates the users and the case type.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const CASES = '/index.php/apps/openregister/api/objects/dossiq'
const CASE_TITLE = `Omgevingsvergunning Markt 12 ${RUN}`
// The member's own case, so the task scenario does not hang on who may read the owner's case.
const TASK_CASE_TITLE = `Omgevingsvergunning Markt 14 ${RUN}`
const JSON_HEADERS = { 'Content-Type': 'application/json' }

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
 * Whether the case app is enabled on the instance.
 *
 * @param admin The admin context.
 */
async function caseAppEnabled(admin: APIRequestContext): Promise<boolean> {
	const res = await admin.get('/ocs/v2.php/cloud/apps?filter=enabled&format=json', { failOnStatusCode: false })
	if (!res.ok()) {
		return false
	}
	const apps = (await res.json())?.ocs?.data?.apps ?? []
	return Array.isArray(apps) && apps.includes('dossiq')
}

/**
 * The id of an object a POST answered.
 *
 * @param res The response.
 */
async function idOf(res): Promise<string> {
	const body = await res.json()
	return String(body.id ?? body['@self']?.id ?? '')
}

/**
 * Pick a case in a Case field by typing part of its name.
 *
 * @param page The page.
 * @param scope The element holding the field.
 * @param title The case's name.
 */
async function pickCase(page: Page, scope, title: string): Promise<void> {
	const combobox = scope.getByRole('combobox', { name: 'Case' })
	await combobox.fill(title.slice(0, 24))
	await page.getByRole('option', { name: title }).click()
}

test.describe('Case link on projects and tasks', () => {
	let admin: APIRequestContext
	const owner = `zaakeig-${RUN}`
	const member = `zaaklid-${RUN}`
	let caseType = ''
	let caseId = ''
	let taskCaseId = ''
	let project = ''
	let task = ''

	test.beforeAll(async () => {
		admin = await adminApi()
		test.skip(!(await caseAppEnabled(admin)), 'The case app (dossiq) is not enabled on this instance')

		for (const userid of [owner, member]) {
			expect((await admin.post('/ocs/v2.php/cloud/users', { data: { userid, password: PASSWORD } })).ok()).toBe(true)
		}
		const type = await admin.post(`${CASES}/caseType`, { data: { title: `Omgevingsvergunning ${RUN}` }, headers: JSON_HEADERS })
		expect(type.ok(), `case type: ${type.status()}`).toBe(true)
		caseType = await idOf(type)

		const ownerApi = await userApi(owner)
		const made = await ownerApi.post(`${CASES}/case`, { data: { title: CASE_TITLE, caseType }, headers: JSON_HEADERS })
		expect(made.ok(), `case: ${made.status()}`).toBe(true)
		caseId = await idOf(made)

		const created = await ownerApi.post('/index.php/apps/planninq/api/projects', { data: { title: `Markt 12 ${RUN}` }, headers: JSON_HEADERS })
		expect(created.status()).toBe(201)
		project = (await created.json()).id
		const patched = await ownerApi.patch(`${OBJECTS}/project/${project}`, { data: { members: [member] }, headers: JSON_HEADERS })
		expect(patched.ok(), `members: ${patched.status()}`).toBe(true)
		const t = await ownerApi.post(`${OBJECTS}/task`, { data: { title: `Check the drawings ${RUN}`, status: 'open', project }, headers: JSON_HEADERS })
		expect(t.ok(), `task: ${t.status()}`).toBe(true)
		task = await idOf(t)
		await ownerApi.dispose()

		const memberApi = await userApi(member)
		const own = await memberApi.post(`${CASES}/case`, { data: { title: TASK_CASE_TITLE, caseType }, headers: JSON_HEADERS })
		expect(own.ok(), `member case: ${own.status()}`).toBe(true)
		taskCaseId = await idOf(own)
		await memberApi.dispose()
	})

	test.afterAll(async () => {
		if (!admin) {
			return
		}
		if (task) {
			await admin.delete(`${OBJECTS}/task/${task}`, { failOnStatusCode: false })
		}
		if (project) {
			await admin.delete(`${OBJECTS}/project/${project}`, { failOnStatusCode: false })
		}
		for (const id of [caseId, taskCaseId].filter(Boolean)) {
			await admin.delete(`${CASES}/case/${id}`, { failOnStatusCode: false })
		}
		if (caseType) {
			await admin.delete(`${CASES}/caseType/${caseType}`, { failOnStatusCode: false })
		}
		for (const userid of [owner, member]) {
			await admin.delete(`/ocs/v2.php/cloud/users/${userid}`, { failOnStatusCode: false })
		}
		await admin.dispose()
	})

	test('an owner links a project to a case, and clears the link', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, owner, PASSWORD)
		await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
		await page.getByRole('button', { name: 'Project settings' }).click()
		await page.getByRole('tab', { name: 'Details' }).click()

		// WHEN the owner picks the case in the "Case" field and saves.
		const field = page.getByTestId('project-case')
		await pickCase(page, field, CASE_TITLE)
		await page.getByRole('button', { name: 'Save', exact: true }).click()

		// THEN the project stores that case's id as caseReference.
		const api = await userApi(owner)
		await expect.poll(async () => (await (await api.get(`${OBJECTS}/project/${project}`)).json()).caseReference).toBe(caseId)

		// AND the project list shows the badge, which opens the case in the case app.
		await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
		const badge = page.getByTestId('case-badge').filter({ hasText: `Case: ${CASE_TITLE}` })
		await expect(badge).toHaveCount(1)
		await expect(badge).toHaveAttribute('href', new RegExp(`/apps/dossiq/cases/${caseId}$`))

		// The overview shows the same badge.
		await page.goto(new URL(`projects/${project}/overview`, PLANNINQ_ROOT).toString())
		await expect(page.getByTestId('case-badge')).toHaveText(`Case: ${CASE_TITLE}`)

		await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
		await page.getByTestId('case-badge').filter({ hasText: `Case: ${CASE_TITLE}` }).click()
		await expect(page).toHaveURL(new RegExp(`/apps/dossiq/cases/${caseId}`))

		// WHEN the owner clears the field and saves, THEN no caseReference and no badge.
		await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
		await page.getByRole('button', { name: 'Project settings' }).click()
		await page.getByRole('tab', { name: 'Details' }).click()
		await page.getByTestId('project-case').locator('.vs__clear').click()
		await page.getByRole('button', { name: 'Save', exact: true }).click()
		await expect.poll(async () => (await (await api.get(`${OBJECTS}/project/${project}`)).json()).caseReference ?? null).toBeNull()
		await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
		await expect(page.getByRole('heading', { name: `Markt 12 ${RUN}` }).or(page.getByText(`Markt 12 ${RUN}`)).first()).toBeVisible()
		await expect(page.getByTestId('case-badge').filter({ hasText: CASE_TITLE })).toHaveCount(0)

		await api.dispose()
		await context.close()
	})

	test('a member cannot change the link', async ({ browser }) => {
		const ownerApi = await userApi(owner)
		expect((await ownerApi.patch(`${OBJECTS}/project/${project}`, { data: { caseReference: caseId }, headers: JSON_HEADERS })).ok()).toBe(true)
		await ownerApi.dispose()

		const { context, page } = await signedInPage(browser, member, PASSWORD)
		await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
		await page.getByRole('button', { name: 'Project settings' }).click()
		await page.getByRole('tab', { name: 'Details' }).click()

		// THEN the linked case is shown as a link without an edit control.
		const field = page.getByTestId('project-case')
		await expect(field.getByTestId('case-badge')).toHaveAttribute('href', new RegExp(`/apps/dossiq/cases/${caseId}$`))
		await expect(field.getByRole('combobox')).toHaveCount(0)
		await context.close()
	})

	test('a member links a task to a case', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, member, PASSWORD)
		await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())

		// WHEN the member picks the case in the task's "Case" field.
		const field = page.getByTestId('task-case')
		await pickCase(page, field, TASK_CASE_TITLE)

		// THEN the task stores that case's id as zaakUuid.
		const api = await userApi(member)
		await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json()).zaakUuid).toBe(taskCaseId)
		await api.dispose()

		// AND the task page shows the case name as a link to the case.
		await page.reload()
		const link = page.getByTestId('task-case-link')
		await expect(link).toContainText(TASK_CASE_TITLE)
		await expect(link).toHaveAttribute('href', new RegExp(`/apps/dossiq/cases/${taskCaseId}$`))
		await context.close()
	})
})
