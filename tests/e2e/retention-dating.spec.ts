/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the archive blocks of the project, task and
 * plannedTimeEntry schemas (retention-on-planninq-records task 1.1). The admin
 * picks a selection-list category for all three schemas. When the project is
 * archived, ProjectClosureListener stamps `closedAt`; OpenRegister then dates
 * the project from it and dates the task from its project. Both answer an
 * `archiefactiedatum` under `@self.retention`. A task of a project that is
 * still active gets no date, so it can never reach a destruction list.
 *
 * Related records are dated in OpenRegister's queued
 * RelatedRetentionRecalculationJob. Where occ is reachable (shared-instance.ts:
 * PLANNINQ_E2E_OCC, PLANNINQ_E2E_CONTAINER / NEXTCLOUD_CONTAINER, else
 * `php occ` on CI), the spec runs those jobs instead of waiting for cron. When
 * the instance has no selection list, the spec skips. Written for the nightly
 * run; not yet run against a live instance.
 *
 *   @e2e record-retention::an-archived-project-reaches-a-destruction-list
 *   @e2e record-retention::an-active-project-is-not-listed
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'
import { occPrefix } from './shared-instance.ts'

const SETTINGS = '/index.php/apps/planninq/api/settings/retention'
const JOB_CLASS = 'OCA\\OpenRegister\\BackgroundJob\\RelatedRetentionRecalculationJob'
const RUN = Date.now().toString(36)

/**
 * Run OpenRegister's queued retention recalculation jobs, when occ is reachable.
 */
function runRetentionJobs(): void {
	try {
		const [command, ...prefix] = occPrefix()
		const occ = (args: string[]): string => execFileSync(command, [...prefix, ...args], { encoding: 'utf8', timeout: 120_000 })
		const jobs = JSON.parse(occ(['background-job:list', `--class=${JOB_CLASS}`, '--output=json', '--limit=500']) || '[]') as Array<{ id: number }>
		for (const job of jobs) {
			occ(['background-job:execute', String(job.id), '--force-execute'])
		}
	} catch {
		// No occ here: the synchronous dating on save is what the assertions then see.
	}
}

/**
 * The archive action date OpenRegister holds for one object, or null.
 *
 * @param api The admin context.
 * @param schema The schema slug.
 * @param id The object id.
 */
async function actionDate(api: APIRequestContext, schema: string, id: string): Promise<string | null> {
	const res = await api.get(`${OBJECTS}/${schema}/${id}`)
	expect(res.ok(), `read ${schema}: ${res.status()}`).toBe(true)
	const body = await res.json()
	return body['@self']?.retention?.archiefactiedatum ?? null
}

test.describe('Retention dating of planninq records', () => {
	test.describe.configure({ mode: 'serial', timeout: 180_000 })

	test('an archived project and its task get an archive action date; an active one does not', async () => {
		const api = await adminApi()
		const before = await (await api.get(SETTINGS)).json()
		const made: Array<[string, string]> = []
		try {
			test.skip((before.categories ?? []).length === 0, 'no selection list configured in OpenRegister')
			const category = String(before.categories[0].category)
			const saved = await api.put(SETTINGS, { data: { picks: { project: category, task: category, plannedTimeEntry: category } } })
			expect(saved.ok(), `save retention picks: ${saved.status()}`).toBe(true)

			const archived = await createObject(api, 'project', { title: `Retention archived ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', archived])
			const archivedTask = await createObject(api, 'task', { title: `Retention task ${RUN}`, project: archived, status: 'done' })
			made.push(['task', archivedTask])
			const active = await createObject(api, 'project', { title: `Retention active ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', active])
			const activeTask = await createObject(api, 'task', { title: `Retention active task ${RUN}`, project: active, status: 'open' })
			made.push(['task', activeTask])

			const close = await api.patch(`${OBJECTS}/project/${archived}`, { data: { status: 'archived' } })
			expect(close.ok(), `archive project: ${close.status()}`).toBe(true)
			const closed = await (await api.get(`${OBJECTS}/project/${archived}`)).json()
			expect(closed.closedAt, 'ProjectClosureListener stamps closedAt').toBeTruthy()

			await expect.poll(async () => {
				runRetentionJobs()
				return actionDate(api, 'project', archived)
			}, { timeout: 120_000, intervals: [2_000, 5_000, 10_000] }).not.toBeNull()
			await expect.poll(async () => {
				runRetentionJobs()
				return actionDate(api, 'task', archivedTask)
			}, { timeout: 120_000, intervals: [2_000, 5_000, 10_000] }).not.toBeNull()

			expect(await actionDate(api, 'project', active)).toBeNull()
			expect(await actionDate(api, 'task', activeTask)).toBeNull()
		} finally {
			await removeObjects(api, made)
			await api.put(SETTINGS, { data: { picks: before.picks ?? {} } })
			await api.dispose()
		}
	})
})
