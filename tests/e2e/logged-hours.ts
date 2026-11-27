/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Logged hours for e2e set-up, written the way the app writes them since
 * plannedtimeentry-reads-humaniqs-hours: the hours on humaniq's `TimeEntry`,
 * and a `plannedTimeEntry` beside it that carries the reference, the task and
 * the user (no hours). A spec that seeds a bare `plannedTimeEntry` with a
 * `duration` writes a shape no page reads any more.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { OBJECTS } from './portfolio-api.ts'

/** humaniq's time entries, through OpenRegister. */
export const HUMANIQ_TIME_ENTRIES = '/index.php/apps/openregister/api/objects/humaniq/TimeEntry'

/** How a humaniq entry names a planninq task (`<app>:<schema>`). */
const TASK_TYPE = 'planninq:task'

/**
 * Whether humaniq is enabled on the instance. Without it planninq records no
 * hours at all, so a spec that needs logged time skips.
 *
 * @param api A signed-in admin context.
 */
export async function humaniqEnabled(api: APIRequestContext): Promise<boolean> {
	const res = await api.get('/ocs/v2.php/cloud/apps?filter=enabled&format=json', { failOnStatusCode: false })
	expect(res.ok(), `app list: ${res.status()}`).toBe(true)
	const apps = (await res.json())?.ocs?.data?.apps ?? []
	return Array.isArray(apps) && apps.includes('humaniq')
}

/** What a seeded booking left behind, for clean-up. */
export interface LoggedHours {
	owner: string
	planned: string
}

/**
 * Log `minutes` for `user` on a task of a project. A plannedTimeEntry
 * requires its task, as the app's Log time dialog always books on one.
 *
 * @param api A signed-in admin context.
 * @param entry The booking: the task and its project, the user, the minutes and the day.
 * @param entry.task The task the hours are on.
 * @param entry.project The project the hours are on.
 * @param entry.user The user who logged them.
 * @param entry.minutes The minutes logged.
 * @param entry.date The day, YYYY-MM-DD.
 * @return the humaniq entry and the plannedTimeEntry beside it
 */
export async function logHours(
	api: APIRequestContext,
	entry: { task: string, project: string, user: string, minutes: number, date: string },
): Promise<LoggedHours> {
	const owner: Record<string, unknown> = {
		date: entry.date,
		hours: Math.round((entry.minutes / 60) * 100) / 100,
		userId: entry.user,
		description: '',
		origin: 'manual',
		projectId: entry.project,
		domainObjectType: TASK_TYPE,
		domainObjectRef: entry.task,
	}
	const made = await api.post(HUMANIQ_TIME_ENTRIES, { data: owner })
	expect(made.ok(), `humaniq TimeEntry: ${made.status()}`).toBe(true)
	const madeBody = await made.json()
	const ownerId = String(madeBody.id ?? madeBody['@self']?.id)

	const planned = { timeEntry: ownerId, task: entry.task, user: entry.user, project: entry.project }
	const beside = await api.post(`${OBJECTS}/plannedTimeEntry`, { data: planned })
	expect(beside.ok(), `plannedTimeEntry: ${beside.status()}`).toBe(true)
	const besideBody = await beside.json()
	return { owner: ownerId, planned: String(besideBody.id ?? besideBody['@self']?.id) }
}

/**
 * Remove seeded bookings: the plannedTimeEntry first, then the hours.
 *
 * @param api A signed-in admin context.
 * @param booked What logHours() returned.
 */
export async function removeLoggedHours(api: APIRequestContext, booked: LoggedHours[]): Promise<void> {
	for (const { owner, planned } of booked) {
		await api.delete(`${OBJECTS}/plannedTimeEntry/${planned}`, { failOnStatusCode: false })
		await api.delete(`${HUMANIQ_TIME_ENTRIES}/${owner}`, { failOnStatusCode: false })
	}
}
