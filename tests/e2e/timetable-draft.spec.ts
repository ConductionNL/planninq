/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for draft timetables (timetable-draft-review). Everything runs
 * through the HTTP API: the admin delivers and publishes, and a teacher, a
 * learner and a member of the timetable group read with their own accounts.
 *
 *   @e2e timetable-draft-review::the-named-teacher-sees-their-draft-lesson
 *   @e2e timetable-draft-review::a-learner-does-not-see-a-draft-lesson
 *   @e2e timetable-draft-review::the-timetable-group-does-not-see-a-draft-lesson
 *   @e2e timetable-draft-review::a-draft-delivery-for-a-published-lesson-is-refused
 *   @e2e timetable-draft-review::delivering-the-lesson-as-scheduled-publishes-the-draft
 *   @e2e timetable-draft-review::publishing-a-week-makes-the-lessons-visible
 *   @e2e timetable-draft-review::a-teacher-cannot-publish
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { adminApi, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`
const TEACHER = `docent-${RUN}`
const LEARNER = `leerling-${RUN}`
const PLANNER = `roostermaker-${RUN}`
const GROUP = `3a-${RUN}`
const SOURCE = `roster-e2e-${RUN}`
const API = '/index.php/apps/planninq/api/timetable/sessions'
const WEEK = { from: '2026-10-05T00:00:00+02:00', to: '2026-10-11T23:59:59+02:00' }

/**
 * An API context signed in as one of the test users.
 *
 * @param username The user id.
 */
async function userApi(username: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password: PASSWORD, send: 'always' },
		extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * The externalRefs a user reads for the group's week, drafts asked for.
 *
 * @param username The user id.
 */
async function refsReadBy(username: string): Promise<string[]> {
	const ctx = await userApi(username)
	const res = await ctx.get(API, { params: { groupReference: GROUP, includeDrafts: 'true', ...WEEK } })
	expect(res.ok()).toBe(true)
	return (await res.json()).results.map((session: { externalRef: string }) => session.externalRef)
}

/**
 * One lesson of the group's week.
 *
 * @param ref The occurrence id.
 * @param day The day of October 2026.
 * @param status The delivered status.
 */
function lesson(ref: string, day: number, status: string): object {
	const date = `2026-10-${String(day).padStart(2, '0')}`
	return {
		externalRef: ref,
		subject: 'Wiskunde',
		title: 'Wiskunde 3a',
		startsAt: `${date}T09:00:00+02:00`,
		endsAt: `${date}T09:50:00+02:00`,
		groupReference: GROUP,
		teacherUserId: TEACHER,
		status,
	}
}

test.describe('Draft timetables', () => {
	let api: APIRequestContext

	test.beforeAll(async () => {
		api = await adminApi()
		await api.post('/ocs/v2.php/cloud/groups', { data: { groupid: 'planninq-timetable' } })
		for (const [userid, groups] of [[TEACHER, []], [LEARNER, []], [PLANNER, ['planninq-timetable']]] as Array<[string, string[]]>) {
			expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid, password: PASSWORD, groups } })).ok()).toBe(true)
		}
		const drafts = [1, 2, 3, 4, 5].map((n) => lesson(`d-${n}`, 4 + n, 'draft'))
		const res = await api.post(`${API}/upsert`, { data: { sourceSystem: SOURCE, sessions: drafts } })
		expect((await res.json()).created).toBe(5)
	})

	test.afterAll(async () => {
		const mine = await (await api.get(API, { params: { groupReference: GROUP, includeDrafts: 'true', ...WEEK } })).json()
		await removeObjects(api, mine.results.map((session: { id: string }) => ['timetableSession', session.id]))
		for (const userid of [TEACHER, LEARNER, PLANNER]) {
			await api.delete(`/ocs/v2.php/cloud/users/${userid}`)
		}
	})

	test('the named teacher sees a draft, a learner and the timetable group do not', async () => {
		expect(await refsReadBy(TEACHER)).toEqual(['d-1', 'd-2', 'd-3', 'd-4', 'd-5'])
		expect(await refsReadBy(LEARNER)).toEqual([])
		expect(await refsReadBy(PLANNER)).toEqual([])
	})

	test('a teacher cannot publish', async () => {
		const res = await (await userApi(TEACHER)).post(`${API}/publish`, { data: { sourceSystem: SOURCE, ...WEEK } })
		expect(res.ok()).toBe(false)
		expect(await refsReadBy(PLANNER)).toEqual([])
	})

	test('after publishing everyone who reads the published timetable sees the lessons', async () => {
		const res = await api.post(`${API}/publish`, { data: { sourceSystem: SOURCE, ...WEEK } })
		expect(res.ok()).toBe(true)
		expect((await res.json()).published).toBe(5)
		expect(await refsReadBy(PLANNER)).toEqual(['d-1', 'd-2', 'd-3', 'd-4', 'd-5'])
	})

	test('a published lesson cannot become a draft again, and a scheduled delivery publishes a draft', async () => {
		const refused = await (await api.post(`${API}/upsert`, { data: { sourceSystem: SOURCE, sessions: [lesson('d-1', 5, 'draft')] } })).json()
		expect(refused.rejected.map((row: { errorCode: string }) => row.errorCode)).toEqual(['already-published'])

		await api.post(`${API}/upsert`, { data: { sourceSystem: SOURCE, sessions: [lesson('d-6', 10, 'draft')] } })
		const published = await (await api.post(`${API}/upsert`, { data: { sourceSystem: SOURCE, sessions: [lesson('d-6', 10, 'scheduled')] } })).json()
		expect(published.updated).toBe(1)
		expect(await refsReadBy(PLANNER)).toContain('d-6')
	})
})
