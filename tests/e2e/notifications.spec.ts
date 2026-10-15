/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for assignment notifications (collaboration-notifications).
 *
 *   @e2e assignment-notification::the-assignee-gets-a-notification-with-a-link-to-the-task
 *   @e2e assignment-notification::a-task-created-for-someone-notifies-them
 *   @e2e assignment-notification::clearing-the-assignee-notifies-nobody
 *   @e2e assignment-notification::switching-assignment-notifications-off-stops-them
 *   @e2e email-notifications::the-email-switch-is-disabled-without-an-email-address
 *
 * The suite signs in as the admin only, so the admin is the assignee.
 * OpenRegister delivers the notification (the rules are declared on the task
 * schema), possibly through a queued job, so each check waits for it.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const NOTIFICATIONS = '/ocs/v2.php/apps/notifications/api/v2/notifications?format=json'

/**
 * The subjects of the admin's current notifications.
 *
 * @param api The admin context.
 */
async function subjects(api: APIRequestContext): Promise<string[]> {
	const res = await api.get(NOTIFICATIONS)
	if (!res.ok()) {
		return []
	}
	const body = await res.json()
	return (body?.ocs?.data ?? []).map((row: { subject?: string }) => String(row.subject ?? ''))
}

/**
 * Set the admin's assignment switch.
 *
 * @param api The admin context.
 * @param on Whether assignment notifications are on.
 */
async function setSwitch(api: APIRequestContext, on: boolean): Promise<void> {
	const res = await api.post('/index.php/apps/planninq/api/settings/user', { data: { notify_assigned: on } })
	expect(res.ok()).toBe(true)
}

test.describe('Assignment notifications', () => {
	test('assigning, creating with and clearing an assignee, with the switch on and off', async () => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			await setSwitch(api, true)
			const project = await createObject(api, 'project', { title: `Notify ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])

			// Assigning an unassigned task notifies the assignee.
			const exportTask = await createObject(api, 'task', { title: `Export to CSV ${RUN}`, project, status: 'open' })
			made.push(['task', exportTask])
			await api.patch(`${OBJECTS}/task/${exportTask}`, { data: { assignedTo: ADMIN_USER } })
			await expect.poll(async () => (await subjects(api)).some((s) => s.includes(`Export to CSV ${RUN}`)), { timeout: 60_000 }).toBe(true)

			// A task created for someone notifies them.
			const demo = await createObject(api, 'task', { title: `Plan demo ${RUN}`, project, status: 'open', assignedTo: ADMIN_USER })
			made.push(['task', demo])
			await expect.poll(async () => (await subjects(api)).some((s) => s.includes(`Plan demo ${RUN}`)), { timeout: 60_000 }).toBe(true)

			// Clearing the assignee notifies nobody new.
			const before = (await subjects(api)).length
			await api.patch(`${OBJECTS}/task/${exportTask}`, { data: { assignedTo: null } })
			await new Promise((resolve) => setTimeout(resolve, 5_000))
			expect((await subjects(api)).length).toBe(before)

			// With the switch off, an assignment notifies nobody.
			await setSwitch(api, false)
			const quiet = await createObject(api, 'task', { title: `Quiet ${RUN}`, project, status: 'open', assignedTo: ADMIN_USER })
			made.push(['task', quiet])
			await new Promise((resolve) => setTimeout(resolve, 5_000))
			expect((await subjects(api)).some((s) => s.includes(`Quiet ${RUN}`))).toBe(false)
		} finally {
			await setSwitch(api, true)
			await removeObjects(api, made)
		}
	})

	test('the email switch is disabled, with a hint, while the account has no email address', async ({ page }) => {
		const api = await adminApi()
		const account = await (await api.get(`/ocs/v2.php/cloud/users/${ADMIN_USER}?format=json`)).json()
		const email = String(account?.ocs?.data?.email ?? '')
		try {
			await api.put(`/ocs/v2.php/cloud/users/${ADMIN_USER}`, { data: { key: 'email', value: '' } })
			await page.goto(new URL('.', PLANNINQ_ROOT).toString())
			await page.getByTestId('cn-nav-personal-settings').click()
			await expect(page.getByTestId('notify-by-email-hint')).toHaveText('Add an email address in your Nextcloud personal settings to get mail.')
			await expect(page.getByTestId('notify-by-email').locator('input')).toBeDisabled()
		} finally {
			await api.put(`/ocs/v2.php/cloud/users/${ADMIN_USER}`, { data: { key: 'email', value: email } })
		}
	})
})
