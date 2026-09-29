/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for portfolios (projects-grouping-hierarchy-fields, section 1).
 *
 *   @e2e project-portfolios::the-project-list-groups-by-portfolio
 *   @e2e project-portfolios::a-project-manager-moves-a-project-into-a-portfolio
 *   @e2e project-portfolios::a-portfolio-with-a-three-level-scale
 *
 * "A client cannot write the derived readers list" carries an `@e2e exclude`
 * in the spec: ProjectHierarchyGuardListenerTest asserts it on the real
 * event classes. "A portfolio manager sees a project they are not on" needs a
 * second, non-admin account the shared instance does not provision; the
 * read rule is asserted by PlanninqRegisterSchemaTest and the stamping by
 * ProjectHierarchyGuardListenerTest, and the live recipe is in the PR.
 *
 * Portfolios are created through the API as the admin, with a run id, and
 * removed at the end; deleting a portfolio takes its projects out of it.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { FIXTURE } from './fixtures/seed.ts'
import { openFixtureProjectBoard, PLANNINQ_ROOT } from './nav.ts'

const RUN = Date.now().toString(36)
const OBJECTS = '/index.php/apps/openregister/api/objects/planninq'

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

async function createPortfolio(api: APIRequestContext, title: string, extra: object = {}): Promise<string> {
	const res = await api.post(`${OBJECTS}/projectPortfolio`, { data: { title, managers: [], order: 0, ...extra } })
	expect(res.ok(), `create portfolio ${title}`).toBe(true)
	const body = await res.json()
	return body.id ?? body['@self']?.id
}

test.describe('Portfolios', () => {
	let api: APIRequestContext
	const created: string[] = []

	test.beforeAll(async () => {
		api = await admin()
	})

	test.afterAll(async () => {
		for (const id of created) {
			await api.delete(`${OBJECTS}/projectPortfolio/${id}`, { failOnStatusCode: false })
		}
		await api.dispose()
	})

	test('a project manager moves a project into a portfolio, and the project list groups by portfolio', async ({ page }) => {
		const title = `Dienstverlening ${RUN}`
		created.push(await createPortfolio(api, title))

		await openFixtureProjectBoard(page)
		await page.getByRole('button', { name: /project settings/i }).click()
		await page.getByTestId('project-portfolio').click()
		await page.getByRole('option', { name: title }).click()
		await page.getByRole('button', { name: /^save/i }).first().click()

		await page.goto(PLANNINQ_ROOT)
		await page.locator('#app-navigation-vue a[title="Projects"]').click()
		const section = page.locator('[data-testid="portfolio-section"]', { hasText: title })
		await expect(section).toContainText(FIXTURE.projectTitle)
		const toggle = section.getByTestId('portfolio-section-toggle')
		await expect(toggle).toHaveAttribute('aria-expanded', 'true')
		await toggle.click()
		await expect(toggle).toHaveAttribute('aria-expanded', 'false')

		await page.locator('#app-navigation-vue a[title="Boards"]').click()
		await expect(page.locator('[data-testid="portfolio-section"]', { hasText: title })).toContainText(FIXTURE.projectTitle)
	})

	test('a portfolio with a three-level scale', async ({ page }) => {
		const scale = { levels: 3, likelihood: ['Low', 'Medium', 'High'], impact: ['Low', 'Medium', 'High'], thresholds: { medium: 3, high: 6 } }
		const title = `Drie niveaus ${RUN}`
		created.push(await createPortfolio(api, title, { riskScale: JSON.stringify(scale) }))

		await openFixtureProjectBoard(page)
		await page.getByRole('button', { name: /project settings/i }).click()
		await page.getByTestId('project-portfolio').click()
		await page.getByRole('option', { name: title }).click()
		await page.getByRole('button', { name: /^save/i }).first().click()

		await page.getByTestId('project-tab-risks').click()
		await expect(page.getByTestId('risk-heat-map').locator('tbody tr')).toHaveCount(3)
	})
})
