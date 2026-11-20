/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for work types (time-timer-and-work-type 2.1, 2.3 and 2.4).
 *
 *   @e2e time-work-type::an-admin-adds-work-types
 *   @e2e time-work-type::an-entry-records-its-work-type
 *   @e2e time-work-type::no-work-types-means-no-field
 *   @e2e time-work-type::the-time-report-splits-minutes-by-work-type
 *
 * The work type list is one admin setting for the whole instance, so the
 * tests run in order and put the list back as they found it. Only "an admin
 * adds work types" runs as the admin, because the admin settings page is the
 * scenario; every other page runs as a regular user the spec creates, with an
 * empty storage state. Saving a time entry needs humaniq, which holds the
 * hours; without it "an entry records its work type" is skipped.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi, createObject, removeObjects } from './portfolio-api.ts'
import { humaniqEnabled, JSON_HEADERS, plannedEntries, removeUserWork, userApi, userWithTasks } from './time-fixtures.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const SETTINGS = '/index.php/apps/planninq/api/settings'
const TYPES = ['Development', 'Meeting', 'Support']

/**
 * The instance's work types as the settings API returns them.
 *
 * @param admin The admin context.
 */
async function workTypes(admin: APIRequestContext): Promise<string[]> {
	const body = await (await admin.get(SETTINGS)).json()
	const raw = body?.config?.work_types ?? body?.work_types ?? []
	const list = typeof raw === 'string' ? JSON.parse(raw || '[]') : raw
	return Array.isArray(list) ? list.map((item) => (typeof item === 'string' ? item : item?.name)).filter(Boolean) : []
}

/**
 * Store the instance's work types.
 *
 * @param admin The admin context.
 * @param names The names.
 */
async function setWorkTypes(admin: APIRequestContext, names: string[]): Promise<void> {
	const res = await admin.post(SETTINGS, { data: { work_types: JSON.stringify(names) }, headers: JSON_HEADERS })
	expect(res.ok(), `work types: ${res.status()}`).toBe(true)
}

/**
 * Open the Log time form on a task.
 *
 * @param page The page.
 * @param project The project id.
 * @param task The task id.
 */
async function openLogTime(page: Page, project: string, task: string) {
	await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
	await page.getByTestId('log-time').click()
	const dialog = page.getByRole('dialog', { name: 'Log time' })
	await expect(dialog.getByTestId('time-entry-duration')).toBeVisible()
	return dialog
}

test.describe('Work types', () => {
	test.describe.configure({ mode: 'serial', timeout: 120_000 })

	let admin: APIRequestContext
	let before: string[] = []
	const user = `werksoort-${RUN}`
	let made: { project?: string, tasks?: string[] } = {}

	test.beforeAll(async () => {
		admin = await adminApi()
		before = await workTypes(admin)
		made = await userWithTasks(admin, user, PASSWORD, `Werksoorten ${RUN}`, [`Sprint review ${RUN}`])
	})

	test.afterAll(async () => {
		if (admin) {
			await setWorkTypes(admin, before)
			await removeUserWork(admin, user, made)
			await admin.dispose()
		}
	})

	test('an admin adds work types', async ({ page, browser }) => {
		await setWorkTypes(admin, [])
		// The admin settings page is admin-only; this page carries the admin session.
		await page.goto(`${BASE_URL}/index.php/settings/admin/planninq`)
		const section = page.getByTestId('work-type-settings')
		await expect(section).toBeVisible({ timeout: 30_000 })
		for (const name of TYPES) {
			await section.getByTestId('work-type-new').locator('input').fill(name)
			await section.getByTestId('work-type-add').click()
			await expect(section.getByTestId('work-type-row').filter({ hasText: name })).toHaveCount(1)
		}
		await expect.poll(() => workTypes(admin)).toEqual(TYPES)

		// The time entry form offers those three names.
		const { context, page: userPage } = await signedInPage(browser, user, PASSWORD)
		const dialog = await openLogTime(userPage, made.project as string, (made.tasks as string[])[0])
		await dialog.getByRole('combobox', { name: 'Work type' }).click()
		await expect(userPage.getByRole('option')).toHaveText(TYPES)
		await context.close()
	})

	test('an entry records its work type', async ({ browser }) => {
		test.skip(!(await humaniqEnabled(admin)), 'saving a time entry needs humaniq, which is not enabled here')
		await setWorkTypes(admin, ['Development', 'Meeting'])
		const task = (made.tasks as string[])[0]
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		const dialog = await openLogTime(page, made.project as string, task)
		await dialog.getByTestId('time-entry-duration').locator('input').fill('45m')
		await dialog.getByRole('combobox', { name: 'Work type' }).click()
		await page.getByRole('option', { name: 'Meeting' }).click()
		await dialog.getByTestId('time-entry-save').click()
		await expect(dialog).toBeHidden()
		await context.close()

		const api = await userApi(user, PASSWORD)
		await expect.poll(async () => (await plannedEntries(api, task)).map((e) => e.workType)).toEqual(['Meeting'])
		await api.dispose()
	})

	test('no work types means no field', async ({ browser }) => {
		await setWorkTypes(admin, [])
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		const dialog = await openLogTime(page, made.project as string, (made.tasks as string[])[0])
		await expect(dialog.getByTestId('time-entry-work-type')).toHaveCount(0)
		await expect(dialog.getByRole('combobox', { name: 'Work type' })).toHaveCount(0)
		await dialog.getByTestId('time-entry-cancel').click()
		await context.close()
	})

	test('the time report splits minutes by work type', async ({ browser }) => {
		await setWorkTypes(admin, ['Development', 'Meeting'])
		const task = (made.tasks as string[])[0]
		const rows: Array<[string, string]> = []
		try {
			// The By work type chart sums the minutes on planninq's own rows, so
			// the rows are seeded there, owned by the user who reads the report.
			rows.push(['plannedTimeEntry', await createObject(admin, 'plannedTimeEntry', { task, project: made.project, user, date: '2026-10-07', duration: 120, workType: 'Development' })])
			rows.push(['plannedTimeEntry', await createObject(admin, 'plannedTimeEntry', { task, project: made.project, user, date: '2026-10-07', duration: 45, workType: 'Meeting' })])

			const { context, page } = await signedInPage(browser, user, PASSWORD)
			await page.goto(new URL('reports/time', PLANNINQ_ROOT).toString())
			const chart = page.locator('.cn-widget-wrapper').filter({ hasText: 'By work type' })
			await expect(chart).toHaveCount(1)
			await expect(chart).toContainText('Development')
			await expect(chart).toContainText('Meeting')
			await expect(chart.locator('.apexcharts-bar-area[val="120"]')).toHaveCount(1)
			await expect(chart.locator('.apexcharts-bar-area[val="45"]')).toHaveCount(1)
			await context.close()
		} finally {
			await removeObjects(admin, rows)
		}
	})
})
