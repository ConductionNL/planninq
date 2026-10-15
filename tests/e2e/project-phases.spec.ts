/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for project phases and the concluding document
 * (planning-phase-gate-document).
 *
 *   @e2e project-phases::a-member-opens-the-phases-page-from-the-board
 *   @e2e project-phases::a-member-adds-a-phase
 *   @e2e project-phases::a-member-reorders-phases-with-the-keyboard
 *   @e2e project-phases::a-member-uploads-a-document-to-a-phase
 *   @e2e project-phases::closing-without-a-document-explains-what-is-needed
 *   @e2e project-phases::closing-with-a-concluding-document-completes-the-phase
 *
 * The API scenarios carry `@e2e exclude` notes in the spec: they are Newman
 * requests in tests/integration/planninq.postman_collection.json and
 * PhaseConcludingDocumentGuardTest. The suite runs as the admin.
 */

import { expect, test } from '@playwright/test'
import { openFixtureProjectBoard } from './nav.ts'
import { adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

async function openPhases(page) {
	const projectId = await openFixtureProjectBoard(page)
	await page.getByTestId('project-tab-phases').click()
	await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/phases$`))
	return projectId
}

test.describe('Project phases', () => {
	test('a member opens the phases page from the board, adds a phase and reorders with the keyboard', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const projectId = await openPhases(page)
			await expect(page.getByTestId('project-tab-phases')).toHaveAttribute('aria-current', 'page')

			made.push(['projectPhase', await createObject(api, 'projectPhase', { title: `Ontwerp ${RUN}`, project: projectId, order: 900 })])
			await page.reload()

			await page.getByTestId('phase-add').click()
			await page.getByTestId('phase-title').locator('input').fill(`Realisatie ${RUN}`)
			await page.getByTestId('phase-start').locator('input').fill('2026-10-01')
			await page.getByTestId('phase-end').locator('input').fill('2026-11-30')
			await page.getByTestId('phase-save').click()

			const rows = page.getByTestId('phase-row')
			const realisatie = rows.filter({ hasText: `Realisatie ${RUN}` })
			await expect(realisatie).toContainText('2026-10-01')
			await expect(realisatie.getByTestId('phase-status')).toHaveText('Open')

			await realisatie.getByTestId('phase-move-up').focus()
			await page.keyboard.press('Enter')
			const titles = await rows.getByTestId('phase-open').allTextContents()
			expect(titles.indexOf(`Realisatie ${RUN}`)).toBeLessThan(titles.indexOf(`Ontwerp ${RUN}`))

			const listed = await api.get(`/index.php/apps/openregister/api/objects/planninq/projectPhase?project=${projectId}&_limit=200`)
			const created = ((await listed.json()).results ?? []).find((p) => p.title === `Realisatie ${RUN}`)
			if (created) {
				made.push(['projectPhase', created.id ?? created['@self']?.id])
			}
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('closing without a document explains what is needed, and with one the phase completes', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const projectId = await openFixtureProjectBoard(page)
			const title = `Ontwerp ${RUN}`
			made.push(['projectPhase', await createObject(api, 'projectPhase', { title, project: projectId, status: 'in_progress', order: 901 })])
			await page.getByTestId('project-tab-phases').click()

			const row = page.getByTestId('phase-row').filter({ hasText: title })
			await row.getByTestId('phase-close').click()
			await expect(page.getByTestId('phase-close-no-files')).toBeVisible()
			await page.getByTestId('phase-close-confirm').click()
			await expect(page.getByTestId('phase-close-error')).toHaveText('Upload the concluding document before you close this phase.')

			// A member uploads a document to the phase, here through the close dialog.
			await page.getByTestId('phase-close-upload').setInputFiles({ name: 'Ontwerpbesluit.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%%EOF\n') })
			await expect(page.getByTestId('phase-close-file')).toContainText('Ontwerpbesluit.pdf')
			await page.getByTestId('phase-close-confirm').click()

			await expect(row.getByTestId('phase-status')).toHaveText('Completed')
			await expect(row.getByTestId('phase-document')).toContainText('Ontwerpbesluit.pdf')
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
