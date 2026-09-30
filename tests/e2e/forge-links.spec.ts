/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Code links on the task page (integration-code-forge-links).
 *
 * Covers:
 *   @e2e code-forge-links::a-task-lists-its-linked-merge-request
 *   @e2e code-forge-links::a-member-pastes-a-merge-request-link
 *   @e2e code-forge-links::a-member-removes-a-manual-link
 *
 * The suite signs in as the admin only. "A member cannot remove an
 * integration link" needs a second, non-admin account; it is asserted by
 * PHPUnit testForgeLinkIsProjectScoped (the update and delete rules) and by
 * vitest forgeUrl.spec.js "a member removes only a link added by hand".
 *
 * @spec openspec/changes/integration-code-forge-links/tasks.md#task-2.2
 */
import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Code links on the task page', () => {
	test('lists an integration link, adds a pasted merge request and removes it again', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Code ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const task = await createObject(api, 'task', { title: `Printer driver ${RUN}`, project, status: 'open' })
			made.push(['task', task])
			const key = String((await (await api.get(`${OBJECTS}/task/${task}`)).json()).key ?? '')
			expect(key).not.toBe('')

			// An integration link, written the way integriq writes it: by key only.
			const res = await api.post(`${OBJECTS}/forgeLink`, {
				data: {
					taskKey: key,
					kind: 'mergeRequest',
					url: 'https://github.com/acme/portal/pull/42',
					title: 'Fix printer driver',
					repository: 'acme/portal',
					externalId: `github:acme/portal#42-${RUN}`,
					state: 'merged',
					source: 'integriq',
				},
			})
			expect(res.ok()).toBe(true)
			const link = await res.json()
			made.push(['forgeLink', String(link.id ?? link['@self']?.id)])

			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			const section = page.getByTestId('task-forge-links')
			const row = section.getByTestId('task-forge-link').filter({ hasText: 'Fix printer driver' })
			await expect(row).toContainText('Merge request')
			await expect(row).toContainText('acme/portal')
			await expect(row).toContainText('Merged')
			await expect(row.getByRole('link', { name: 'Fix printer driver' })).toHaveAttribute('href', 'https://github.com/acme/portal/pull/42')

			// A member pastes a merge request link.
			await section.getByTestId('task-forge-link-url').locator('input').fill('https://gitlab.example.org/acme/portal/-/merge_requests/42')
			await section.getByTestId('task-forge-link-add').click()
			const pasted = section.getByTestId('task-forge-link').filter({ hasText: '!42' })
			await expect(pasted).toContainText('Merge request')
			await expect(pasted).toContainText('acme/portal')
			await expect(pasted).toContainText('Added by hand')

			// And removes it again.
			await pasted.getByTestId('task-forge-link-remove').click()
			await expect(section.getByTestId('task-forge-link').filter({ hasText: '!42' })).toHaveCount(0)
		} finally {
			await removeObjects(api, made.reverse())
		}
	})
})
