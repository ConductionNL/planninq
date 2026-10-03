/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for importing a Microsoft Project plan on the project
 * timeline (integration-msproject-import).
 *
 *   @e2e msproject-import::the-owner-previews-a-contractor-plan
 *   @e2e msproject-import::the-owner-imports-the-plan-and-sees-it-on-the-timeline
 *   @e2e msproject-import::an-mpp-file-is-refused-with-a-hint
 *
 * The suite runs as the admin, who is offered the import like the owner.
 * The plan is tests/fixtures/msproject/contractor-plan.xml with every task
 * name suffixed per run, so the run's objects can be found and removed.
 */

import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { openFixtureProjectBoard } from './nav.ts'
import { adminApi, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const NAMES = ['Ruwbouw', 'Fundering', 'Metselwerk', 'Kozijnen voorbereiden', 'Kozijnen', 'Maatvoering', 'Oplevering ruwbouw', 'Afbouw', 'Schilderwerk', 'Asbestsanering']

/**
 * The fixture plan with this run's suffix on every task name.
 *
 * @return {Buffer}
 */
function plan(): Buffer {
	let xml = readFileSync(new URL('../fixtures/msproject/contractor-plan.xml', import.meta.url), 'utf8')
	for (const name of NAMES) {
		xml = xml.replace(`<Name>${name}</Name>`, `<Name>${name} ${RUN}</Name>`)
	}
	return Buffer.from(xml)
}

async function openTimeline(page) {
	const projectId = await openFixtureProjectBoard(page)
	await page.getByTestId('project-tab-timeline').click()
	await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/timeline$`))
	return projectId
}

test.describe('Import from Microsoft Project', () => {
	test('the owner previews a contractor plan, imports it and sees it on the timeline', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const projectId = await openTimeline(page)
			await page.getByTestId('msproject-import-open').click()
			await page.getByTestId('msproject-import-file').setInputFiles({ name: 'Renovatie stadhuis.xml', mimeType: 'application/xml', buffer: plan() })

			const counts = page.getByTestId('msproject-import-counts')
			await expect(counts).toContainText('Phases')
			await expect(counts).toContainText('2')
			await expect(page.getByTestId('msproject-import-losses')).toContainText('Tasks with resources, which are not imported: 2')

			// The preview wrote nothing.
			const before = await api.get(`${OBJECTS}/task?project=${projectId}&title=${encodeURIComponent(`Fundering ${RUN}`)}`)
			expect(((await before.json()).results ?? []).length).toBe(0)

			await page.getByTestId('msproject-import-confirm').click()
			await expect(page.getByTestId('msproject-import-result')).toContainText('The plan is imported')

			for (const schema of ['task', 'projectPhase']) {
				const listed = await api.get(`${OBJECTS}/${schema}?project=${projectId}&_limit=500`)
				for (const row of ((await listed.json()).results ?? [])) {
					if (String(row.title ?? '').endsWith(` ${RUN}`)) {
						made.push([schema, row.id ?? row['@self']?.id])
					}
				}
			}

			await page.getByRole('button', { name: 'Close' }).click()
			await expect(page.locator('.project-timeline__bar', { hasText: `Fundering ${RUN}` })).toBeVisible()
			await expect(page.locator('.project-timeline__bar', { hasText: `Metselwerk ${RUN}` })).toBeVisible()
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})

	test('an mpp file is refused with a hint', async ({ page }) => {
		await openTimeline(page)
		await page.getByTestId('msproject-import-open').click()
		await page.getByTestId('msproject-import-file').setInputFiles({ name: 'planning.mpp', mimeType: 'application/vnd.ms-project', buffer: Buffer.from([0xD0, 0xCF, 0x11, 0xE0]) })
		await expect(page.getByTestId('msproject-import-error')).toHaveText('Save the plan in Microsoft Project with File, Save as, XML format, and import that file.')
	})
})
