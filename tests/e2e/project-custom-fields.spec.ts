/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for project custom fields (projects-grouping-hierarchy-fields, section 3).
 *
 *   @e2e project-custom-fields::adding-a-choice-field
 *
 * "A wrong value type is refused by the server" carries an `@e2e exclude`:
 * API-level validation with no screen, asserted by ProjectHierarchyGuardListenerTest.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

test.describe('Project custom fields', () => {
	test('adding a choice field', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			made.push(['projectField', await createObject(api, 'projectField', { key: `beleidsveld${RUN}`, label: 'Beleidsveld', type: 'choice', options: ['Wonen', 'Mobiliteit', 'Economie'], required: true, order: 0, appliesTo: 'project' })])
			const project = await createObject(api, 'project', { title: `Omgevingsvisie ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: /project settings/i }).click()

			const field = page.getByTestId(`project-field-beleidsveld${RUN}`)
			await expect(field).toContainText('Beleidsveld')
			await field.locator('input').click()
			for (const option of ['Wonen', 'Mobiliteit', 'Economie']) {
				await expect(page.getByRole('option', { name: option })).toBeVisible()
			}
			await page.keyboard.press('Escape')

			await page.getByRole('button', { name: /^save$/i }).first().click()
			await expect(page.getByTestId(`project-field-beleidsveld${RUN}-error`)).toHaveText('Beleidsveld is required')
		} finally {
			await removeObjects(api, made)
		}
	})
})
