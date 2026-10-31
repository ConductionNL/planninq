/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the one-way export of tasks to Nextcloud Tasks
 * (planning-calendar, section 2). It reads the admin's own CalDAV home, so it
 * checks what the Tasks app and any CalDAV client would see.
 *
 *   @e2e caldav-export::the-export-is-off-by-default
 *   @e2e caldav-export::a-completed-task-is-completed-in-the-tasks-app
 *   @e2e caldav-export::an-edit-in-the-tasks-app-is-replaced-by-the-next-planninq-change
 *   @e2e caldav-export::the-uid-is-stored-once
 *   @e2e caldav-export::switching-the-export-off-removes-the-list
 *   @e2e exclude caldav-export::switching-the-export-on-fills-the-planninq-list the backfill is a queued background job and the CI run starts no cron; TaskCalendarExportServiceTest::testSwitchOnExportsExistingTasks runs the job against the real service
 *   @e2e exclude caldav-export::reassignment-moves-the-vtodo the shared instance provisions one account; TaskCalendarExportListenerTest::testReassignmentMovesTheVtodo covers it over the real service
 *   @e2e exclude caldav-export::a-failed-export-does-not-block-the-save a CalDAV failure cannot be forced on the CI instance; TaskCalendarExportListenerTest::testExportFailureDoesNotThrow covers it
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const LIST = `/remote.php/dav/calendars/${ADMIN_USER}/planninq/`

/**
 * Switch the admin's export on or off.
 *
 * @param api The admin context.
 * @param on The new state.
 */
async function setExport(api: APIRequestContext, on: boolean): Promise<void> {
	const res = await api.post('/index.php/apps/planninq/api/settings/user', { data: { export_tasks_to_caldav: on } })
	expect(res.ok(), `settings: ${res.status()}`).toBe(true)
}

/**
 * The VTODO of a task in the admin's Planninq list, or the status when absent.
 *
 * @param api The admin context.
 * @param taskId The task id.
 */
async function vtodo(api: APIRequestContext, taskId: string): Promise<string | number> {
	const res = await api.get(`${LIST}planninq-task-${taskId}.ics`, { headers: { Accept: 'text/calendar' } })
	return res.ok() ? (await res.text()).replace(/\r\n /g, '') : res.status()
}

test.describe('Export to Nextcloud Tasks', () => {
	test('off by default, then kept in step one way, then removed', async () => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			await setExport(api, false)
			const project = await createObject(api, 'project', { title: `Export ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], startDate: '2026-01-01', endDate: '2026-12-31' })
			made.push(['project', project])

			// Off by default: a task assigned to the admin makes no list.
			const quiet = await createObject(api, 'task', { title: `Quiet ${RUN}`, status: 'open', project, assignedTo: ADMIN_USER, dueDate: '2026-10-16' })
			made.push(['task', quiet])
			expect((await api.get(LIST, { headers: { Depth: '0' }, failOnStatusCode: false })).status()).toBe(404)

			// Switched on: a new assigned task is written with its due date.
			await setExport(api, true)
			const task = await createObject(api, 'task', { title: `Export to CSV ${RUN}`, status: 'open', project, assignedTo: ADMIN_USER, dueDate: '2026-10-16' })
			made.push(['task', task])
			await expect.poll(() => vtodo(api, task), { timeout: 15_000 }).toContain(`SUMMARY:Export to CSV ${RUN}`)
			expect(await vtodo(api, task)).toContain('DUE;VALUE=DATE:20261016')

			// The UID is stored once on the task, and it is the VTODO's UID.
			const stored = (await (await api.get(`${OBJECTS}/task/${task}`)).json()).calendarEventUid
			expect(stored).toMatch(new RegExp(`^planninq-task-${task}@`))
			expect(await vtodo(api, task)).toContain(`UID:${stored}`)

			// An edit in the Tasks app is replaced by the next planninq change.
			const edited = String(await vtodo(api, task)).replace(`SUMMARY:Export to CSV ${RUN}`, 'SUMMARY:Export')
			const put = await api.put(`${LIST}planninq-task-${task}.ics`, { data: edited, headers: { 'Content-Type': 'text/calendar; charset=utf-8' } })
			expect(put.ok(), `put: ${put.status()}`).toBe(true)
			await api.patch(`${OBJECTS}/task/${task}`, { data: { dueDate: '2026-10-23' } })
			await expect.poll(() => vtodo(api, task), { timeout: 15_000 }).toContain('DUE;VALUE=DATE:20261023')
			expect(await vtodo(api, task)).toContain(`SUMMARY:Export to CSV ${RUN}`)
			expect((await (await api.get(`${OBJECTS}/task/${task}`)).json()).title).toBe(`Export to CSV ${RUN}`)

			// Completed in planninq, completed in the Tasks app.
			await api.patch(`${OBJECTS}/task/${task}`, { data: { status: 'done' } })
			await expect.poll(() => vtodo(api, task), { timeout: 15_000 }).toContain('STATUS:COMPLETED')

			// Switched off: the list is gone.
			await setExport(api, false)
			expect((await api.get(LIST, { headers: { Depth: '0' }, failOnStatusCode: false })).status()).toBe(404)
		} finally {
			await setExport(api, false)
			await removeObjects(api, made)
		}
	})
})
