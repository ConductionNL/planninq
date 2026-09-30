/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for a generator run (timetabling-generator, section 5).
 *
 *   @e2e timetable-generator::generate-a-scenario-that-respects-a-hard-wish
 *
 * The run itself is a queued job, so the test starts it through the endpoint,
 * runs the background jobs with `occ background-job:worker` when the harness
 * allows it (NC_OCC), and reads the stored scenario. The page is checked for the
 * status and the two lists.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { execSync } from 'node:child_process'
import { BASE_URL } from './base-url.ts'
import { PLANNINQ_ROOT } from './nav.ts'

const RUN = Date.now().toString(36)
const OBJECTS = '/index.php/apps/openregister/api/objects/planninq'
const API = '/index.php/apps/planninq/api'

async function admin(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: {
			username: process.env.NC_ADMIN_USER ?? process.env.ADMIN_USER ?? 'admin',
			password: process.env.NC_ADMIN_PASS ?? process.env.ADMIN_PASSWORD ?? 'admin',
			send: 'always',
		},
		extraHTTPHeaders: { 'Content-Type': 'application/json', 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

test.describe('Timetable generator', () => {
	let api: APIRequestContext
	const created: string[] = []

	test.beforeAll(async () => {
		api = await admin()
	})

	test.afterAll(async () => {
		for (const path of created) {
			await api.delete(path, { failOnStatusCode: false })
		}
		await api.dispose()
	})

	test('generate a scenario that respects a hard wish', async ({ page }) => {
		const upload = await api.post(`${API}/timetable/input/upload`, {
			data: {
				rooms: 'reference,capacity,type\nB12,30,classroom\nB13,30,classroom',
				activities: `group,subject,teacher,lessons per week,lesson length,room type\n3A,English,klaas-${RUN},3,1,classroom\n3B,Maths,noor-${RUN},2,1,classroom`,
			},
		})
		expect(upload.ok(), 'upload the sheets').toBe(true)

		const wish = await api.post(`${OBJECTS}/timetableWish`, {
			data: { appliesTo: 'teacher', reference: `klaas-${RUN}`, kind: 'unavailable', periods: ['wed-5', 'wed-6', 'wed-7', 'wed-8'], strength: 'hard' },
		})
		const wishBody = await wish.json()
		created.push(`${OBJECTS}/timetableWish/${wishBody.id ?? wishBody['@self']?.id}`)

		const scenario = await api.post(`${OBJECTS}/timetableScenario`, {
			data: { title: `Try ${RUN}`, source: 'generated', weekOf: '2026-10-05', windowFrom: '2026-10-05', windowTo: '2026-10-30', status: 'queued' },
		})
		const scenarioBody = await scenario.json()
		const id = scenarioBody.id ?? scenarioBody['@self']?.id
		created.push(`${OBJECTS}/timetableScenario/${id}`)

		const generate = await api.post(`${API}/timetable/scenarios/${id}/generate`)
		expect(generate.status()).toBe(202)

		if (process.env.NC_OCC) {
			execSync(`${process.env.NC_OCC} background-job:worker -t 120 'OCA\\Planninq\\BackgroundJob\\GenerateTimetableScenario'`, { stdio: 'ignore' })
			const stored = await (await api.get(`${OBJECTS}/timetableScenario/${id}`)).json()
			expect(['running', 'done']).toContain(stored.status)
			expect(stored.metrics.hardWishesBroken).toBe(0)
			for (const row of stored.placements.filter((p: { lesson: string }) => p.lesson.startsWith('3A:English'))) {
				expect(['wed-5', 'wed-6', 'wed-7', 'wed-8']).not.toContain(row.period)
			}
		}

		await page.goto(`${PLANNINQ_ROOT}timetable/scenarios/${id}`)
		await expect(page.getByTestId('scenario-sections')).toBeVisible()
		await expect(page.getByTestId('scenario-status')).not.toBeEmpty()
	})
})
