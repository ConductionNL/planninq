/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for restoring archived projects (projects-lifecycle-policy, section 1).
 *
 *   @e2e project-lifecycle::restore-from-the-project-settings-sidebar
 *   @e2e project-lifecycle::restore-from-the-archived-list
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

/**
 * An archived project owned by the admin.
 *
 * @param api The admin context.
 * @param made The objects to remove afterwards.
 */
async function archivedProject(api, made: Array<[string, string]>): Promise<string> {
	const project = await createObject(api, 'project', { title: `Archief ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
	made.push(['project', project])
	const res = await api.post(`/index.php/apps/openregister/api/objects/${project}/transition`, { data: { action: 'archive' } })
	expect(res.ok(), `archive: ${res.status()}`).toBe(true)
	return project
}

/**
 * The stored status of a project.
 *
 * @param api The admin context.
 * @param id The project id.
 */
async function statusOf(api, id: string): Promise<string> {
	return (await (await api.get(`${OBJECTS}/project/${id}`)).json()).status
}

test.describe('Project lifecycle', () => {
	test('restore from the project settings sidebar', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await archivedProject(api, made)
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: 'Project settings' }).click()
			await page.getByRole('tab', { name: 'Danger zone' }).click()
			await page.getByRole('button', { name: 'Restore project' }).click()
			await expect(page.getByRole('button', { name: 'Archive project' })).toBeVisible()
			expect(await statusOf(api, project)).toBe('active')
		} finally {
			await removeObjects(api, made)
		}
	})

	test('restore from the archived list', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await archivedProject(api, made)
			await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
			await page.locator('.project-list__actions').getByText('Archived', { exact: true }).click()
			await page.getByRole('button', { name: `Restore Archief ${RUN}` }).click()
			await expect(page.getByRole('button', { name: `Restore Archief ${RUN}` })).toHaveCount(0)
			expect(await statusOf(api, project)).toBe('active')
		} finally {
			await removeObjects(api, made)
		}
	})
})
