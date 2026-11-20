/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for importing a project from another tool on the admin page
 * (projects-import-from-other-tools 1.2).
 *
 *   @e2e project-import::import-a-trello-board
 */

import { expect, test } from '@playwright/test'
import { resolve } from 'node:path'
import { BASE_URL } from './base-url.ts'
import { adminApi, OBJECTS, removeObjects } from './portfolio-api.ts'

const FIXTURE = resolve(__dirname, '../fixtures/board-import/Vergunningen.json')

test.describe('Board import', () => {
	test.describe.configure({ timeout: 120_000 })

	test('an admin imports a Trello board', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			await page.goto(`${BASE_URL}/index.php/settings/admin/planninq`)
			const section = page.getByTestId('board-import-settings')
			await expect(section).toBeVisible({ timeout: 30_000 })
			await section.getByTestId('board-import-tool').click()
			await page.getByRole('option', { name: /Trello/ }).click()
			await expect(section.getByTestId('board-import-tool')).toContainText('Trello')
			await section.getByTestId('board-import-file').setInputFiles(FIXTURE)
			await section.getByTestId('board-import-run').click()
			const result = section.getByTestId('board-import-result')
			await expect(result).toContainText('Imported 3 columns', { timeout: 60_000 })

			const href = await result.getByRole('link', { name: 'Open the project' }).getAttribute('href')
			const project = String(href).split('/projects/')[1]
			expect(project).toBeTruthy()
			made.push(['project', project])

			const stored = await (await api.get(`${OBJECTS}/project/${project}`)).json()
			expect(stored.title).toBe('Vergunningen')
			const columns = (await (await api.get(`${OBJECTS}/column?project=${project}&_limit=50`)).json()).results ?? []
			for (const column of columns) {
				made.push(['column', String(column.id)])
			}
			expect(columns.map((column) => column.title).sort()).toEqual(['Besloten', 'Binnengekomen', 'In behandeling'])
			const tasks = (await (await api.get(`${OBJECTS}/task?project=${project}&_limit=50`)).json()).results ?? []
			for (const task of tasks) {
				made.push(['task', String(task.id)])
			}
			const dakkapel = tasks.find((task) => task.title === 'Dakkapel Kerkstraat 4')
			expect(dakkapel, 'the card became a task').toBeTruthy()
			expect(String(dakkapel.dueDate).slice(0, 10)).toBe('2026-12-01')
			expect(columns.find((column) => column.id === dakkapel.column)?.title).toBeTruthy()
		} finally {
			await removeObjects(api, made)
			await api.dispose()
		}
	})
})
