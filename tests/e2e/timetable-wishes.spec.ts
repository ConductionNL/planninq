/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the timetable wish list (timetabling-generator, section 3).
 *
 *   @e2e timetable-generator::a-timetabler-marks-a-wish-as-hard
 *
 * The hard wish is added through the dialog on the week grid; the soft wish is
 * created through the API as the admin, so the list shows both. Both carry a
 * run id and are removed at the end.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { PLANNINQ_ROOT } from './nav.ts'

const RUN = Date.now().toString(36)
const OBJECTS = '/index.php/apps/openregister/api/objects/planninq/timetableWish'
const TEACHER = `klaas-${RUN}`

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

test.describe('Timetable wishes', () => {
	let api: APIRequestContext

	test.beforeAll(async () => {
		api = await admin()
	})

	test.afterAll(async () => {
		const res = await api.get(`${OBJECTS}?reference=${TEACHER}`, { failOnStatusCode: false })
		const body = res.ok() ? await res.json() : { results: [] }
		for (const wish of body.results ?? []) {
			await api.delete(`${OBJECTS}/${wish.id ?? wish['@self']?.id}`, { failOnStatusCode: false })
		}
		await api.dispose()
	})

	test('a timetabler marks a wish as hard', async ({ page }) => {
		const soft = await api.post(OBJECTS, {
			data: { appliesTo: 'teacher', reference: TEACHER, kind: 'avoid', periods: ['fri-8'], strength: 'soft', weight: 2 },
		})
		expect(soft.ok(), 'create the soft wish').toBe(true)

		await page.goto(`${PLANNINQ_ROOT}timetable/wishes`)
		await page.getByRole('button', { name: /add|new/i }).first().click()
		await page.getByTestId('wish-reference').locator('input').fill(TEACHER)
		for (const period of [5, 6, 7, 8]) {
			await page.getByTestId(`wish-period-wed-${period}`).locator('input').check()
		}
		await page.getByTestId('wish-strength-hard').locator('input').check()
		await expect(page.getByTestId('wish-weight')).toHaveCount(0)
		await page.getByTestId('wish-save').click()

		const rows = page.getByRole('row').filter({ hasText: TEACHER })
		await expect(rows).toHaveCount(2)
		await expect(rows.filter({ hasText: /hard/i })).toHaveCount(1)
		await expect(rows.filter({ hasText: /soft/i })).toContainText('2')

		const stored = await api.get(`${OBJECTS}?reference=${TEACHER}&strength=hard`)
		const hard = (await stored.json()).results?.[0]
		expect(hard).toMatchObject({ kind: 'unavailable', periods: ['wed-5', 'wed-6', 'wed-7', 'wed-8'], weight: null })
	})
})
