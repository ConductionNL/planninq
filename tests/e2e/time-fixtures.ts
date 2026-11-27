/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared set-up for the timer, work type and time report specs
 * (time-timer-and-work-type): a regular user the spec creates, their own
 * project and tasks, their running timer, and whether humaniq (which holds the
 * logged hours) is on the instance.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { OBJECTS } from './portfolio-api.ts'

export const JSON_HEADERS = { 'Content-Type': 'application/json' }
export const TIME_ENTRIES = '/index.php/apps/openregister/api/objects/humaniq/TimeEntry'
export const EMPLOYEES = '/index.php/apps/openregister/api/objects/humaniq/Employee'

/**
 * An API context signed in as `username`, never carrying the admin's session.
 *
 * @param username The user id.
 * @param password The user's password.
 */
export async function userApi(username: string, password: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password, send: 'always' },
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
export async function humaniqEnabled(admin: APIRequestContext): Promise<boolean> {
	const res = await admin.get('/ocs/v2.php/cloud/apps?filter=enabled&format=json', { failOnStatusCode: false })
	expect(res.ok(), `app list: ${res.status()}`).toBe(true)
	const apps = (await res.json())?.ocs?.data?.apps ?? []
	return Array.isArray(apps) && apps.includes('humaniq')
}

/**
 * The id of an object a POST answered.
 *
 * @param res The response.
 */
export async function idOf(res): Promise<string> {
	const body = await res.json()
	return String(body.id ?? body['@self']?.id ?? '')
}

/**
 * Create a regular user who owns one project with the given tasks, each
 * assigned to them.
 *
 * @param admin The admin context.
 * @param user The user id to create.
 * @param password Their password.
 * @param projectTitle The project's title.
 * @param titles The task titles.
 * @return the project id and the task ids, in the order of `titles`
 */
export async function userWithTasks(admin: APIRequestContext, user: string, password: string, projectTitle: string, titles: string[]): Promise<{ project: string, tasks: string[], employee?: string }> {
	expect((await admin.post('/ocs/v2.php/cloud/users', { data: { userid: user, password } })).ok()).toBe(true)
	// humaniq books hours on an employee and refuses an account none is linked to.
	const employee = (await humaniqEnabled(admin)) ? await employeeFor(admin, user) : undefined
	const api = await userApi(user, password)
	try {
		const created = await api.post('/index.php/apps/planninq/api/projects', { data: { title: projectTitle }, headers: JSON_HEADERS })
		expect(created.status()).toBe(201)
		const project = String((await created.json()).id)
		const tasks: string[] = []
		for (const title of titles) {
			const t = await api.post(`${OBJECTS}/task`, { data: { title, status: 'open', project, assignedTo: user }, headers: JSON_HEADERS })
			expect(t.ok(), `task: ${t.status()}`).toBe(true)
			tasks.push(await idOf(t))
		}
		return { project, tasks, ...(employee ? { employee } : {}) }
	} finally {
		await api.dispose()
	}
}

/**
 * Link a humaniq employee to `user`, as HR does before anyone books hours.
 *
 * @param admin The admin context.
 * @param user The user id.
 * @return the employee id
 */
export async function employeeFor(admin: APIRequestContext, user: string): Promise<string> {
	const res = await admin.post(EMPLOYEES, {
		data: { firstName: 'E2E', lastName: user, startDate: '2026-01-01', nextcloudUserId: user },
		headers: JSON_HEADERS,
	})
	expect(res.ok(), `employee: ${res.status()}`).toBe(true)
	return idOf(res)
}

/**
 * Set or clear the user's running timer, as the timer store does.
 *
 * @param api The user's context.
 * @param timer `{ task, startedAt }`, or null to clear it.
 */
export async function setRunningTimer(api: APIRequestContext, timer: { task: string, startedAt: string } | null): Promise<void> {
	const res = await api.post('/index.php/apps/planninq/api/settings/user', { data: { running_timer: timer }, headers: JSON_HEADERS })
	expect(res.ok(), `running timer: ${res.status()}`).toBe(true)
}

/**
 * An ISO date-time this many seconds ago.
 *
 * @param seconds How long ago.
 */
export function secondsAgo(seconds: number): string {
	return new Date(Date.now() - seconds * 1000).toISOString()
}

/**
 * The plannedTimeEntry rows on a task.
 *
 * @param api A signed-in context.
 * @param task The task id.
 */
export async function plannedEntries(api: APIRequestContext, task: string): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(`${OBJECTS}/plannedTimeEntry?task=${task}&_limit=50`)
	expect(res.ok(), `plannedTimeEntry: ${res.status()}`).toBe(true)
	return (await res.json()).results ?? []
}

/**
 * Remove a user's project, tasks and the user, ignoring what is already gone.
 *
 * @param admin The admin context.
 * @param user The user id.
 * @param made The project and tasks the user owns.
 * @param made.project The project id.
 * @param made.tasks The task ids.
 */
export async function removeUserWork(admin: APIRequestContext, user: string, made: { project?: string, tasks?: string[], employee?: string }): Promise<void> {
	if (made.employee) {
		await admin.delete(`${EMPLOYEES}/${made.employee}`, { failOnStatusCode: false })
	}
	for (const task of made.tasks ?? []) {
		await admin.delete(`${OBJECTS}/task/${task}`, { failOnStatusCode: false })
	}
	if (made.project) {
		await admin.delete(`${OBJECTS}/project/${made.project}`, { failOnStatusCode: false })
	}
	await admin.delete(`/ocs/v2.php/cloud/users/${user}`, { failOnStatusCode: false })
}
