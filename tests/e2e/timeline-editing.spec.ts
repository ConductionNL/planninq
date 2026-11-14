/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for editing dates on the timeline (planning-timeline-editing,
 * section 2): dragging and resizing bars, the keyboard, the dates dialog, a
 * refused write, and a start dropped on a holiday.
 *
 *   @e2e gantt-timeline-view::a-member-drags-a-bar-to-move-a-task
 *   @e2e gantt-timeline-view::a-member-resizes-the-due-date
 *   @e2e gantt-timeline-view::a-failed-write-puts-the-bar-back
 *   @e2e gantt-timeline-view::a-member-moves-a-task-with-the-keyboard
 *   @e2e gantt-timeline-view::a-member-sets-dates-in-the-dialog
 *   @e2e working-calendar::a-dropped-start-on-a-holiday-moves-to-the-next-working-day
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const SETTINGS = '/index.php/apps/planninq/api/settings'

/**
 * A task's stored dates.
 *
 * @param api The admin context.
 * @param id The task id.
 */
async function dates(api: APIRequestContext, id: string): Promise<string> {
	const task = await (await api.get(`${OBJECTS}/task/${id}`)).json()
	return `${String(task.startDate).slice(0, 10)}..${String(task.dueDate).slice(0, 10)}`
}

/**
 * Drag from a point of a locator by a number of days.
 *
 * @param page The page.
 * @param handle Where the drag starts.
 * @param days Days to move, at the day zoom.
 * @param pxPerDay Pixels per day.
 */
async function dragBy(page: Page, handle: ReturnType<Page['locator']>, days: number, pxPerDay: number): Promise<void> {
	const box = await handle.boundingBox()
	if (!box) {
		throw new Error('no box')
	}
	const x = box.x + box.width / 2
	const y = box.y + box.height / 2
	await page.mouse.move(x, y)
	await page.mouse.down()
	await page.mouse.move(x + (days * pxPerDay) / 2, y, { steps: 5 })
	await page.mouse.move(x + days * pxPerDay, y, { steps: 5 })
	await page.mouse.up()
}

test.describe('Editing dates on the timeline', () => {
	test('drag, resize, keyboard, dialog, a refused write and a holiday', async ({ page }) => {
		const api = await adminApi()
		const before = await (await api.get(SETTINGS)).json()
		const made: Array<[string, string]> = []
		try {
			await api.post(SETTINGS, { data: { working_weekdays: '[1,2,3,4,5]', non_working_days: '[{"date":"2026-12-28","name":"Office closed"}]' } })
			const project = await createObject(api, 'project', { title: `Edit ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], startDate: '2026-09-01', endDate: '2027-01-31' })
			made.push(['project', project])
			const task = await createObject(api, 'task', { title: `Export to CSV ${RUN}`, status: 'open', project, startDate: '2026-10-05', dueDate: '2026-10-09' })
			made.push(['task', task])
			const late = await createObject(api, 'task', { title: `Year end ${RUN}`, status: 'open', project, startDate: '2026-12-21', dueDate: '2026-12-22' })
			made.push(['task', late])

			await page.goto(new URL(`projects/${project}/timeline`, PLANNINQ_ROOT).toString())
			const bar = page.locator(`[data-testid="timeline-bar"][data-task-id="${task}"]`)
			await expect(bar).toBeVisible({ timeout: 30_000 })
			const ticks = page.locator('.project-timeline__tick')
			const pxPerDay = (await ticks.first().boundingBox())?.width ?? 0
			expect(pxPerDay).toBeGreaterThan(0)

			// Drag the bar one week to the right.
			await dragBy(page, bar.locator('.project-timeline__bar-label'), 7, pxPerDay)
			await expect.poll(() => dates(api, task), { timeout: 15_000 }).toBe('2026-10-12..2026-10-16')

			// Back, then drag the right end to Tuesday 13 October.
			await api.patch(`${OBJECTS}/task/${task}`, { data: { startDate: '2026-10-05', dueDate: '2026-10-09' } })
			await page.reload()
			await expect(bar).toBeVisible({ timeout: 30_000 })
			await dragBy(page, bar.getByTestId('timeline-bar-due'), 4, pxPerDay)
			await expect.poll(() => dates(api, task), { timeout: 15_000 }).toBe('2026-10-05..2026-10-13')

			// The keyboard: Right moves the task one working day and it is announced.
			await api.patch(`${OBJECTS}/task/${task}`, { data: { startDate: '2026-10-05', dueDate: '2026-10-09' } })
			await page.reload()
			await bar.focus()
			await page.keyboard.press('ArrowRight')
			await expect.poll(() => dates(api, task), { timeout: 15_000 }).toBe('2026-10-06..2026-10-12')
			await expect(page.getByTestId('timeline-announcement')).toContainText(`Export to CSV ${RUN}`)

			// Enter opens the dialog; the due date is set and focus returns to the bar.
			await bar.focus()
			await page.keyboard.press('Enter')
			await page.getByTestId('task-dates-due').fill('2026-10-20')
			await page.getByTestId('task-dates-save').click()
			await expect.poll(() => dates(api, task), { timeout: 15_000 }).toBe('2026-10-06..2026-10-20')
			await expect(bar).toBeFocused()

			// A refused write puts the bar back and says so.
			await page.route(`**/api/objects/planninq/task/${task}`, (route) => route.request().method() === 'PATCH' ? route.fulfill({ status: 403, body: '{}' }) : route.continue())
			const left = (await bar.boundingBox())?.x
			await bar.focus()
			await page.keyboard.press('ArrowRight')
			await expect(page.getByTestId('timeline-save-error')).toBeVisible()
			expect((await bar.boundingBox())?.x).toBe(left)
			expect(await dates(api, task)).toBe('2026-10-06..2026-10-20')
			await page.unroute(`**/api/objects/planninq/task/${task}`)

			// A two-working-day task dropped on the listed 28 December starts on the 29th.
			const lateBar = page.locator(`[data-testid="timeline-bar"][data-task-id="${late}"]`)
			await lateBar.scrollIntoViewIfNeeded()
			await dragBy(page, lateBar.locator('.project-timeline__bar-label'), 7, pxPerDay)
			await expect.poll(() => dates(api, late), { timeout: 15_000 }).toBe('2026-12-29..2026-12-30')
		} finally {
			await api.post(SETTINGS, { data: { non_working_days: before.non_working_days ?? '[]', working_weekdays: before.working_weekdays ?? '[1,2,3,4,5]' } })
			await removeObjects(api, made)
		}
	})
})
