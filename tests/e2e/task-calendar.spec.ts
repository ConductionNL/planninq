/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the task calendar (planning-calendar, section 1): the
 * project calendar, My calendar, the week and list views and the keyboard.
 *
 *   @e2e task-calendar::a-member-sees-project-tasks-on-their-due-dates
 *   @e2e task-calendar::a-member-switches-to-week-view
 *   @e2e task-calendar::my-calendar-shows-only-my-tasks-across-projects
 *   @e2e task-calendar::the-calendar-is-operable-with-the-keyboard
 *   @e2e task-calendar::the-list-view-shows-the-same-tasks
 *
 * The suite runs as the admin and opens the calendars on October 2026
 * through ?date=, so the fixed due dates of the scenarios are on screen
 * whatever day the run happens on.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Task calendar', () => {
	test('project calendar, week and list views, keyboard, and my calendar', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = async (title: string) => {
				const id = await createObject(api, 'project', { title: `${title} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], startDate: '2026-01-01', endDate: '2026-12-31' })
				made.push(['project', id])
				return id
			}
			const task = async (projectId: string, title: string, data: object) => {
				const id = await createObject(api, 'task', { title: `${title} ${RUN}`, status: 'open', project: projectId, ...data })
				made.push(['task', id])
				return id
			}
			const a = await project('Calendar A')
			const b = await project('Calendar B')
			const exportTask = await task(a, 'Export to CSV', { dueDate: '2026-10-16', assignedTo: ADMIN_USER })
			await task(a, 'Import from CSV', { dueDate: '2026-10-23', assignedTo: ADMIN_USER })
			await task(b, 'Review budget', { dueDate: '2026-10-20', assignedTo: ADMIN_USER })
			await task(a, 'Plan demo', { dueDate: '2026-10-21', assignedTo: `colleague-${RUN}` })

			// The Calendar tab on the board opens the project calendar.
			await page.goto(new URL(`projects/${a}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('project-tab-calendar').click()
			await expect(page).toHaveURL(new RegExp(`/projects/${a}/calendar$`))

			// On October 2026 each task sits on its due date.
			await page.goto(new URL(`projects/${a}/calendar?date=2026-10-01`, PLANNINQ_ROOT).toString())
			const grid = page.getByTestId('calendar-grid')
			await expect(grid.locator('td[data-date="2026-10-16"]')).toContainText(`Export to CSV ${RUN}`, { timeout: 30_000 })
			await expect(grid.locator('td[data-date="2026-10-23"]')).toContainText(`Import from CSV ${RUN}`)

			// The week of 12 October shows 12 to 18 October.
			await page.goto(new URL(`projects/${a}/calendar?date=2026-10-12`, PLANNINQ_ROOT).toString())
			await page.getByTestId('calendar-mode-week').click()
			const days = await grid.locator('td').evaluateAll((cells) => cells.map((cell) => cell.getAttribute('data-date')))
			expect(days).toEqual(['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18'])
			await expect(grid.locator('td[data-date="2026-10-16"]')).toContainText(`Export to CSV ${RUN}`)

			// The list view shows the month's tasks under their dates, in date order.
			await page.getByTestId('calendar-mode-list').click()
			const listed = await page.getByTestId('calendar-list-task').filter({ hasText: RUN }).allInnerTexts()
			expect(listed.map((text) => text.trim())).toEqual([`Export to CSV ${RUN}`, `Import from CSV ${RUN}`])

			// Keyboard: Next moves the caption on, and the tasks are links reached with Tab.
			await page.getByTestId('calendar-mode-month').click()
			const caption = page.getByTestId('calendar-caption')
			const before = (await caption.innerText()).trim()
			await page.getByTestId('calendar-next').focus()
			await page.keyboard.press('Enter')
			await expect(caption).not.toHaveText(before)
			await page.getByTestId('calendar-previous').focus()
			await page.keyboard.press('Enter')
			await expect(caption).toHaveText(before)
			const link = grid.getByTestId('calendar-task').filter({ hasText: `Export to CSV ${RUN}` })
			await link.focus()
			await expect(link).toBeFocused()
			await page.keyboard.press('Enter')
			await expect(page).toHaveURL(new RegExp(`/tasks/${exportTask}`))

			// My calendar, from My tasks: my tasks from both projects, named, and not a colleague's.
			await page.goto(new URL('my-tasks', PLANNINQ_ROOT).toString())
			await page.getByTestId('my-work-as-calendar').click()
			await expect(page).toHaveURL(/\/my-calendar$/)
			await page.goto(new URL('my-calendar?date=2026-10-01', PLANNINQ_ROOT).toString())
			await expect(grid.locator('td[data-date="2026-10-16"]')).toContainText(`Calendar A ${RUN}`, { timeout: 30_000 })
			await expect(grid.locator('td[data-date="2026-10-20"]')).toContainText(`Review budget ${RUN}`)
			await expect(grid.locator('td[data-date="2026-10-20"]')).toContainText(`Calendar B ${RUN}`)
			await expect(grid.getByText(`Plan demo ${RUN}`)).toHaveCount(0)
		} finally {
			await removeObjects(api, made)
		}
	})
})
