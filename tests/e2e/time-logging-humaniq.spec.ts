/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for logged hours living on humaniq's TimeEntry
 * (plannedtimeentry-reads-humaniqs-hours, task 5.3).
 *
 *   @e2e plannedtimeentry-reads-humaniqs-hours::logging-time-writes-one-humaniq-time-entry
 *   @e2e plannedtimeentry-reads-humaniqs-hours::the-timesheet-shows-the-humaniq-hours
 *   @e2e plannedtimeentry-reads-humaniqs-hours::no-humaniq-no-empty-widget
 *   @e2e plannedtimeentry-reads-humaniqs-hours::no-humaniq-logging-time-says-why
 *   @e2e exclude plannedtimeentry-reads-humaniqs-hours::a-rate-stays-in-planninq tests/vitest/loggedHours.spec.js writes the rate to plannedTimeEntry only, against the real schemas
 *   @e2e exclude plannedtimeentry-reads-humaniqs-hours::an-hour-changed-in-humaniq-shows-in-planninq needs an HR officer role in humaniq; tests/vitest/loggedHours.spec.js joins a changed hours value on the next read
 *   @e2e exclude plannedtimeentry-reads-humaniqs-hours::migration-converts-minutes-to-hours a repair step, covered by its PHPUnit test (5.2)
 *   @e2e exclude plannedtimeentry-reads-humaniqs-hours::running-the-repair-twice-changes-nothing a repair step, covered by its PHPUnit test (5.2)
 *
 * One instance either has humaniq or it does not, so each half skips on the
 * other kind of instance; run the suite on both to cover both. Every page
 * runs as a regular user the spec creates, in a context of its own with an
 * empty storage state; the admin only creates and removes that user.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { inputByTestId, PLANNINQ_ROOT } from './nav.ts'
import { adminApi, OBJECTS } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const TIME_ENTRIES = '/index.php/apps/openregister/api/objects/humaniq/TimeEntry'
const EMPLOYEES = '/index.php/apps/openregister/api/objects/humaniq/Employee'
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
 * Whether humaniq is enabled on the instance.
 *
 * @param admin The admin context.
 */
async function humaniqEnabled(admin: APIRequestContext): Promise<boolean> {
	const res = await admin.get('/ocs/v2.php/cloud/apps?filter=enabled&format=json', { failOnStatusCode: false })
	expect(res.ok(), `app list: ${res.status()}`).toBe(true)
	const apps = (await res.json())?.ocs?.data?.apps ?? []
	return Array.isArray(apps) && apps.includes('humaniq')
}

/**
 * A fresh user with a project they own and one task on it.
 *
 * @param admin The admin context.
 * @param user The user id to create.
 * @return the project and task ids
 */
async function userWithTask(admin: APIRequestContext, user: string): Promise<{ project: string, task: string }> {
	expect((await admin.post('/ocs/v2.php/cloud/users', { data: { userid: user, password: PASSWORD } })).ok()).toBe(true)
	const api = await userApi(user)
	try {
		const created = await api.post('/index.php/apps/planninq/api/projects', { data: { title: `Uren ${RUN}` }, headers: JSON_HEADERS })
		expect(created.status()).toBe(201)
		const project = (await created.json()).id
		const t = await api.post(`${OBJECTS}/task`, { data: { title: `Prepare the council decision ${RUN}`, status: 'open', project }, headers: JSON_HEADERS })
		expect(t.ok(), `task: ${t.status()}`).toBe(true)
		const body = await t.json()
		return { project, task: String(body.id ?? body['@self']?.id) }
	} finally {
		await api.dispose()
	}
}

/**
 * The humaniq employee record of `user`. humaniq books hours on an employee
 * and refuses a booking by an account no employee is linked to ("Er is geen
 * medewerker gekoppeld aan uw account"), so a member who logs time is one.
 *
 * @param admin The admin context.
 * @param user The user id to link.
 * @return the employee id
 */
async function employeeFor(admin: APIRequestContext, user: string): Promise<string> {
	const res = await admin.post(EMPLOYEES, {
		data: { firstName: 'Uren', lastName: `Medewerker ${RUN}`, startDate: '2026-01-01', nextcloudUserId: user },
		headers: JSON_HEADERS,
	})
	expect(res.ok(), `employee: ${res.status()}`).toBe(true)
	const body = await res.json()
	return String(body.id ?? body['@self']?.id)
}

/**
 * The plannedTimeEntry rows on a task.
 *
 * @param api A signed-in context.
 * @param task The task id.
 */
async function plannedEntries(api: APIRequestContext, task: string): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(`${OBJECTS}/plannedTimeEntry?task=${task}&_limit=50`)
	expect(res.ok(), `plannedTimeEntry: ${res.status()}`).toBe(true)
	return (await res.json()).results ?? []
}

test.describe('Logged hours live on humaniq', () => {
	test.describe.configure({ timeout: 120_000 })

	test('logging 90 minutes writes one humaniq time entry and the timesheet shows 1h 30m', async ({ browser }) => {
		const admin = await adminApi()
		const user = `uren-${RUN}`
		const made: { project?: string, task?: string, employee?: string } = {}
		try {
			test.skip(!(await humaniqEnabled(admin)), 'humaniq is not enabled on this instance; the other test covers that case')
			Object.assign(made, await userWithTask(admin, user))
			made.employee = await employeeFor(admin, user)

			// WHEN a project member logs 90 minutes on the task.
			const { context, page } = await signedInPage(browser, user, PASSWORD)
			await page.goto(new URL(`projects/${made.project}/tasks/${made.task}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('log-time').click()
			const dialog = page.getByRole('dialog', { name: 'Log time' })
			await expect(dialog.getByTestId('time-entry-needs-hours-app')).toHaveCount(0)
			await inputByTestId(dialog, 'time-entry-duration').fill('90m')
			const workType = dialog.getByRole('combobox', { name: 'Work type' })
			if (await workType.count()) {
				await workType.click()
				await page.getByRole('option').first().click()
			}
			await dialog.getByTestId('time-entry-save').click()
			await expect(dialog).toBeHidden()

			// THEN one humaniq TimeEntry holds 1.5 hours on that task, for that member.
			const api = await userApi(user)
			await expect.poll(async () => {
				const res = await api.get(`${TIME_ENTRIES}?domainObjectRef=${made.task}&_limit=50`)
				return res.ok() ? ((await res.json()).results ?? []).length : -res.status()
			}).toBe(1)
			const [entry] = (await (await api.get(`${TIME_ENTRIES}?domainObjectRef=${made.task}&_limit=50`)).json()).results
			expect(Number(entry.hours)).toBe(1.5)
			expect(entry.userId).toBe(user)
			expect(entry.projectId).toBe(made.project)
			expect(String(entry.domainObjectType)).toMatch(/(^|:)task$/)

			// AND one plannedTimeEntry points at it.
			const planned = await plannedEntries(api, made.task as string)
			expect(planned).toHaveLength(1)
			expect(planned[0].timeEntry).toBe(entry.id ?? entry['@self']?.id)
			await api.dispose()

			// The timesheet shows one entry of "1h 30m", read from humaniq.
			await page.goto(new URL('timesheet', PLANNINQ_ROOT).toString())
			const rows = page.getByTestId('timesheet-row').filter({ hasText: `Prepare the council decision ${RUN}` })
			await expect(rows).toHaveCount(1)
			await expect(rows.first()).toContainText('1h 30m')
			await context.close()
		} finally {
			if (made.employee) {
				await admin.delete(`${EMPLOYEES}/${made.employee}`, { failOnStatusCode: false })
			}
			if (made.task) {
				await admin.delete(`${OBJECTS}/task/${made.task}`, { failOnStatusCode: false })
			}
			if (made.project) {
				await admin.delete(`${OBJECTS}/project/${made.project}`, { failOnStatusCode: false })
			}
			await admin.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
			await admin.dispose()
		}
	})

	test('without humaniq the time widgets are absent and Log time names humaniq', async ({ browser }) => {
		const admin = await adminApi()
		const user = `geenuren-${RUN}`
		const made: { project?: string, task?: string } = {}
		try {
			test.skip(await humaniqEnabled(admin), 'humaniq is enabled on this instance; the other test covers that case')
			Object.assign(made, await userWithTask(admin, user))
			const { context, page } = await signedInPage(browser, user, PASSWORD)

			// The time report renders none of the four humaniq widgets, not even empty.
			await page.goto(new URL('reports/time', PLANNINQ_ROOT).toString())
			await expect(page).toHaveURL(/\/reports\/time(\?|$)/)
			await expect(page.getByRole('heading', { name: 'Time spent' }).first()).toBeVisible()
			for (const title of ['Per person', 'Most recent']) {
				await expect(page.getByText(title, { exact: true })).toHaveCount(0)
			}
			await expect(page.getByText('Entries', { exact: true })).toHaveCount(0)
			await expect(page.getByText('Hours', { exact: true })).toHaveCount(0)

			// "Log time" says why it cannot save, and saving is not offered.
			await page.goto(new URL(`projects/${made.project}/tasks/${made.task}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('log-time').click()
			const dialog = page.getByRole('dialog', { name: 'Log time' })
			await expect(dialog.getByTestId('time-entry-needs-hours-app')).toHaveText('Time logging needs the humaniq app')
			await inputByTestId(dialog, 'time-entry-duration').fill('90m')
			await expect(dialog.getByTestId('time-entry-save')).toBeDisabled()
			await dialog.getByTestId('time-entry-cancel').click()
			await context.close()

			// No object was written.
			const api = await userApi(user)
			expect(await plannedEntries(api, made.task as string)).toHaveLength(0)
			await api.dispose()
		} finally {
			if (made.task) {
				await admin.delete(`${OBJECTS}/task/${made.task}`, { failOnStatusCode: false })
			}
			if (made.project) {
				await admin.delete(`${OBJECTS}/project/${made.project}`, { failOnStatusCode: false })
			}
			await admin.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
			await admin.dispose()
		}
	})
})
