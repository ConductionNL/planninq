/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for a generator run (timetabling-generator, section 5) and the
 * scenario comparison (section 7).
 *
 *   @e2e timetable-generator::generate-a-scenario-that-respects-a-hard-wish
 *   @e2e timetable-generator::compare-a-generated-and-an-imported-scenario
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
	test('compare a generated and an imported scenario', async ({ page }) => {
		const base = { weekOf: '2026-10-05', windowFrom: '2026-10-05', windowTo: '2026-10-30', status: 'done' }
		const scenarios = [
			{ ...base, title: `Generated ${RUN}`, source: 'generated', metrics: { placed: 3, unplaced: 0, clashes: 0, hardWishesBroken: 0, softWishesBroken: 0, softPenalty: 0, teacherGaps: 1, teacherGapsWorst: 1, lessonsPerDayWorst: 2, roomUse: 0.1 }, placements: [{ lesson: '3A:English:1', period: 'mon-1', room: 'B12' }, { lesson: '3A:English:2', period: 'tue-1', room: 'B12' }] },
			{ ...base, title: `Imported ${RUN}`, source: 'imported', metrics: { placed: 3, unplaced: 0, clashes: 0, hardWishesBroken: 1, softWishesBroken: 0, softPenalty: 0, teacherGaps: 3, teacherGapsWorst: 2, lessonsPerDayWorst: 2, roomUse: 0.1 }, placements: [{ lesson: '3A:English:1', period: 'mon-1', room: 'B12' }, { lesson: '3A:English:2', period: 'wed-6', room: 'B12' }] },
		]
		for (const data of scenarios) {
			const response = await api.post(`${OBJECTS}/timetableScenario`, { data })
			const body = await response.json()
			created.push(`${OBJECTS}/timetableScenario/${body.id ?? body['@self']?.id}`)
		}

		await page.goto(`${PLANNINQ_ROOT}timetable/scenarios`)
		const select = page.getByTestId('scenario-compare-select')
		for (const data of scenarios) {
			await select.click()
			await page.keyboard.type(data.title)
			await page.keyboard.press('Enter')
		}

		await expect(page.getByTestId('scenario-compare-metrics')).toBeVisible()
		await expect(page.getByTestId('compare-hardWishesBroken-0')).toHaveClass(/scenario-compare__best/)
		await expect(page.getByTestId('compare-hardWishesBroken-1')).not.toHaveClass(/scenario-compare__best/)
		await expect(page.getByTestId('scenario-compare-diff')).toContainText('3A:English:2')
	})
})
