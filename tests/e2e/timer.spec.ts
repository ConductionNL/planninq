/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the running timer (time-timer-and-work-type 1.3 and 1.4).
 *
 *   @e2e time-timer::a-person-starts-a-timer-on-a-task-and-sees-it-on-my-tasks
 *   @e2e time-timer::the-timer-survives-a-reload
 *   @e2e time-timer::starting-a-second-timer-asks-first
 *   @e2e time-timer::stopping-opens-the-form-with-the-measured-time
 *   @e2e time-timer::cancelling-the-form-books-nothing
 *   @e2e time-timer::discard-books-nothing
 *   @e2e time-timer::a-long-timer-is-flagged
 *
 * "Start and stop a timer on a phone" is in mobile.spec.ts, which runs on the
 * phone projects. Saving a booked entry needs humaniq, which holds the hours
 * (plannedtimeentry-reads-humaniqs-hours); without it the save step of
 * "stopping opens the form" is skipped and the rest still runs.
 *
 * Every page runs as a regular user the spec creates, in a context of its own
 * with an empty storage state; the admin only creates and removes that user.
 * A timer is started through the page where the scenario starts one, and set
 * through the settings API where the scenario needs one that has already run.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { adminApi } from './portfolio-api.ts'
import { humaniqEnabled, plannedEntries, removeUserWork, secondsAgo, setRunningTimer, TIME_ENTRIES, userApi, userWithTasks } from './time-fixtures.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const EXPORT = `Export to CSV ${RUN}`
const PRINTER = `Printer 2nd floor ${RUN}`

/**
 * Today in the browser's own time zone, as YYYY-MM-DD.
 *
 * @param page The page.
 */
async function browserToday(page: Page): Promise<string> {
	return page.evaluate(() => {
		const d = new Date()
		return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
	})
}

/**
 * The elapsed minutes a timer clock shows ("h:mm:ss").
 *
 * @param clock The clock text.
 */
function clockMinutes(clock: string): number {
	const [h, m] = clock.trim().split(':').map(Number)
	return h * 60 + m
}

test.describe('Running timer', () => {
	test.describe.configure({ mode: 'serial', timeout: 120_000 })

	let admin: APIRequestContext
	let api: APIRequestContext
	const user = `timer-${RUN}`
	let made: { project?: string, tasks?: string[] } = {}
	let exportTask = ''
	let printerTask = ''

	test.beforeAll(async () => {
		admin = await adminApi()
		made = await userWithTasks(admin, user, PASSWORD, `Timers ${RUN}`, [EXPORT, PRINTER])
		;[exportTask, printerTask] = made.tasks as string[]
		api = await userApi(user, PASSWORD)
	})

	test.afterEach(async () => {
		await setRunningTimer(api, null)
	})

	test.afterAll(async () => {
		await api?.dispose()
		if (admin) {
			await removeUserWork(admin, user, made)
			await admin.dispose()
		}
	})

	test('a person starts a timer on a task and sees it on My tasks', async ({ browser }) => {
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL(`projects/${made.project}/tasks/${exportTask}`, PLANNINQ_ROOT).toString())
		await page.getByTestId('start-timer').click()
		await expect(page.getByTestId('running-timer')).toBeVisible()

		await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
		const timer = page.getByTestId('running-timer')
		await expect(timer.getByTestId('running-timer-task')).toHaveText(EXPORT)
		await expect(timer.getByTestId('running-timer-clock')).toHaveText(/^\d+:\d{2}:\d{2}$/)
		await expect(timer.getByTestId('running-timer-stop')).toBeVisible()
		await expect(timer.getByTestId('running-timer-discard')).toBeVisible()

		// Starting a timer writes no time entry.
		expect(await plannedEntries(api, exportTask)).toHaveLength(0)
		await context.close()
	})

	test('the timer survives a reload and keeps counting', async ({ browser }) => {
		await setRunningTimer(api, { task: exportTask, startedAt: secondsAgo(20 * 60) })
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL('timesheet', PLANNINQ_ROOT).toString())
		const clock = page.getByTestId('running-timer').getByTestId('running-timer-clock')
		await expect(clock).toHaveText(/^0:2[01]:\d{2}$/)

		await page.reload()
		await expect(clock).toHaveText(/^0:2[01]:\d{2}$/)
		const before = await clock.textContent()
		await expect.poll(async () => await clock.textContent(), { timeout: 5_000 }).not.toBe(before)
		expect(clockMinutes(String(await clock.textContent()))).toBeGreaterThanOrEqual(20)
		await context.close()
	})

	test('starting a second timer asks first', async ({ browser }) => {
		await setRunningTimer(api, { task: exportTask, startedAt: secondsAgo(60) })
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL(`projects/${made.project}/tasks/${printerTask}`, PLANNINQ_ROOT).toString())
		await expect(page.getByTestId('running-timer-task')).toHaveText(EXPORT)
		await page.getByTestId('start-timer').click()
		await expect(page.getByTestId('timer-switch-question')).toHaveText(`A timer is running on ${EXPORT}. Stop it and start one on ${PRINTER}?`)

		// Declining leaves the first timer running.
		await page.getByTestId('timer-switch-cancel').click()
		await expect(page.getByTestId('running-timer-task')).toHaveText(EXPORT)
		await context.close()
	})

	test('stopping opens the form with the measured time, and saving books it', async ({ browser }) => {
		const withHumaniq = await humaniqEnabled(admin)
		await setRunningTimer(api, { task: exportTask, startedAt: secondsAgo(42 * 60 + 10) })
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
		await page.getByTestId('running-timer-stop').click()

		const dialog = page.getByRole('dialog', { name: 'Log time' })
		await expect(dialog.getByTestId('time-entry-duration').locator('input')).toHaveValue('43m')
		await expect(dialog.locator('#time-entry-date')).toHaveValue(await browserToday(page))

		if (withHumaniq) {
			const workType = dialog.getByRole('combobox', { name: 'Work type' })
			if (await workType.count()) {
				await workType.click()
				await page.getByRole('option').first().click()
			}
			await dialog.getByTestId('time-entry-save').click()
			await expect(dialog).toBeHidden()
			await page.goto(new URL('timesheet', PLANNINQ_ROOT).toString())
			const row = page.getByTestId('timesheet-row').filter({ hasText: EXPORT })
			await expect(row).toHaveCount(1)
			await expect(row).toContainText('43m')
			await expect(page.getByTestId('running-timer')).toHaveCount(0)
			const entries = (await (await api.get(`${TIME_ENTRIES}?domainObjectRef=${exportTask}&_limit=50`)).json()).results ?? []
			expect(entries.map((e) => Number(e.hours))).toEqual([43 / 60])
		} else {
			await expect(dialog.getByTestId('time-entry-needs-hours-app')).toHaveText('Time logging needs the humaniq app')
		}
		await context.close()
	})

	test('cancelling the form books nothing and leaves no timer', async ({ browser }) => {
		await setRunningTimer(api, { task: printerTask, startedAt: secondsAgo(5 * 60) })
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
		await page.getByTestId('running-timer-stop').click()
		const dialog = page.getByRole('dialog', { name: 'Log time' })
		await expect(dialog.getByTestId('time-entry-duration').locator('input')).toHaveValue(/^[56]m$/)
		await dialog.getByTestId('time-entry-cancel').click()
		await expect(dialog).toBeHidden()

		await expect(page.getByTestId('running-timer')).toHaveCount(0)
		await page.reload()
		await expect(page.getByTestId('running-timer')).toHaveCount(0)
		expect(await plannedEntries(api, printerTask)).toHaveLength(0)
		await context.close()
	})

	test('discard books nothing', async ({ browser }) => {
		await setRunningTimer(api, { task: printerTask, startedAt: secondsAgo(3 * 60) })
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL(`projects/${made.project}/tasks/${printerTask}`, PLANNINQ_ROOT).toString())
		await page.getByTestId('running-timer-discard').click()

		await expect(page.getByTestId('running-timer')).toHaveCount(0)
		await page.reload()
		await expect(page.getByTestId('start-timer')).toBeVisible()
		await expect(page.getByTestId('running-timer')).toHaveCount(0)
		expect(await plannedEntries(api, printerTask)).toHaveLength(0)
		await context.close()
	})

	test('a long timer is flagged', async ({ browser }) => {
		await setRunningTimer(api, { task: exportTask, startedAt: secondsAgo(14 * 3600 + 60) })
		const { context, page } = await signedInPage(browser, user, PASSWORD)
		await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
		await page.getByTestId('running-timer-stop').click()
		await expect(page.getByTestId('time-entry-long-timer')).toHaveText('This timer ran for 14 hours. Check the duration before you save.')
		await page.getByTestId('time-entry-cancel').click()
		await context.close()
	})
})
