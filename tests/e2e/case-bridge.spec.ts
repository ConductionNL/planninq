/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for starting a project from a case link (integration-case-bridge).
 *
 *   @e2e case-bridge::a-link-opens-the-dialog-prefilled
 *
 * The other scenarios need Dossiq next to planninq and carry `@e2e exclude`
 * notes in the spec. The suite runs as the admin.
 */

import { expect, test } from '@playwright/test'
import { adminApi, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const CASE_ID = '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d'

test.describe('Start a project from a case', () => {
	test('a link opens the dialog prefilled and creates the project', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const title = `Markt 12 ${RUN}`
			await page.goto(`/index.php/apps/planninq/projects?new=1&case=${CASE_ID}&title=${encodeURIComponent(title)}`)

			await expect(page.getByTestId('project-creation-linked-case')).toHaveText(`Linked case: ${title}`)
			await expect(page.getByLabel('Project title')).toHaveValue(title)
			await page.getByRole('button', { name: 'Create project' }).click()

			// The project is made, found by its title so it is always cleaned up.
			// It carries no link here: the server drops a case the caller cannot
			// read (ProjectController::withReadableLinks), and this case id names
			// no Dossiq case; the link itself is covered where Dossiq runs.
			const byTitle = async () => {
				const listed = await api.get(`${OBJECTS}/project?title=${encodeURIComponent(title)}&_limit=50`)
				return ((await listed.json()).results ?? []).filter((p) => p.title === title)
			}
			await expect.poll(async () => (await byTitle()).length).toBe(1)
			const [project] = await byTitle()
			made.push(['project', project.id ?? project['@self']?.id])
			expect(project.caseReference ?? null).toBeNull()
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
