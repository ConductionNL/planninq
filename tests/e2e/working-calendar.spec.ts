/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the working calendar (planning-timeline-editing, section 1):
 * the admin's working days and holidays, what a member reads back, and the
 * shaded, named day on the timeline.
 *
 *   @e2e working-calendar::an-admin-adds-a-holiday
 *   @e2e working-calendar::an-admin-fills-in-the-dutch-holidays-for-a-year
 *   @e2e working-calendar::a-holiday-is-shaded-and-named-on-the-timeline
 *   @e2e exclude working-calendar::a-malformed-value-is-refused WorkingCalendarServiceTest::testNonWorkingDaysRejectsMalformedValue posts six malformed values to the real service and checks the stored value and the log
 */

import { expect, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const SETTINGS = '/index.php/apps/planninq/api/settings'

test.describe('Working days and holidays', () => {
	test('admin keeps the holidays, members read them, the timeline shades and names them', async ({ page }) => {
		const api = await adminApi()
		const before = await (await api.get(SETTINGS)).json()
		const made: Array<[string, string]> = []
		try {
			await api.post(SETTINGS, { data: { non_working_days: '[]', working_weekdays: '[1,2,3,4,5]' } })

			// The admin adds 25 December 2026 as Christmas Day and saves.
			await page.goto(new URL('/index.php/settings/admin/planninq', BASE_URL).toString())
			const section = page.getByTestId('working-calendar-settings')
			await expect(section).toBeVisible({ timeout: 30_000 })
			await section.getByTestId('working-calendar-new-date').fill('2026-12-25')
			await section.getByTestId('working-calendar-new-name').fill('Christmas Day')
			await section.getByTestId('working-calendar-add').click()
			await section.getByTestId('working-calendar-save').click()
			await expect.poll(async () => (await (await api.get(SETTINGS)).json()).non_working_days, { timeout: 15_000 })
				.toBe('[{"date":"2026-12-25","name":"Christmas Day"}]')

			// The Dutch holidays of 2027 are added, and one can be removed again before saving.
			await section.getByTestId('working-calendar-year').fill('2027')
			await section.getByTestId('working-calendar-dutch').click()
			const days = section.getByTestId('working-calendar-day')
			await expect(days).toHaveCount(11)
			await expect(section.getByTestId('working-calendar-days')).toContainText('Easter Monday')
			await expect(section.getByTestId('working-calendar-days')).toContainText('King\'s Day')
			await days.filter({ hasText: 'Boxing Day' }).getByRole('button').click()
			await expect(days).toHaveCount(10)
			await section.getByTestId('working-calendar-save').click()
			await expect.poll(async () => JSON.parse((await (await api.get(SETTINGS)).json()).non_working_days).map((day: { date: string }) => day.date), { timeout: 15_000 })
				.toEqual(expect.arrayContaining(['2026-12-25', '2027-03-29', '2027-04-27']))

			// The timeline across Christmas shades the day and names it.
			const project = await createObject(api, 'project', { title: `Holidays ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], startDate: '2026-12-01', endDate: '2027-01-31' })
			made.push(['project', project])
			const task = await createObject(api, 'task', { title: `Year end ${RUN}`, status: 'open', project, startDate: '2026-12-21', dueDate: '2026-12-31' })
			made.push(['task', task])
			await page.goto(new URL(`projects/${project}/timeline`, PLANNINQ_ROOT).toString())
			const christmas = page.locator('.project-timeline__tick[data-date="2026-12-25"]')
			await expect(christmas).toHaveAttribute('aria-label', 'Christmas Day', { timeout: 30_000 })
			await expect(christmas).toHaveAttribute('data-non-working', 'true')
			await expect(page.locator('.project-timeline__tick[data-date="2026-12-24"]')).not.toHaveAttribute('data-non-working', 'true')
		} finally {
			await api.post(SETTINGS, { data: { non_working_days: before.non_working_days ?? '[]', working_weekdays: before.working_weekdays ?? '[1,2,3,4,5]' } })
			await removeObjects(api, made)
		}
	})
})
