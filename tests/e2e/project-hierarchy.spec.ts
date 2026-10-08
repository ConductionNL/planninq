/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for subprojects (projects-grouping-hierarchy-fields, section 2).
 *
 *   @e2e project-hierarchy::a-programme-shows-its-subprojects
 *   @e2e project-hierarchy::a-cycle-is-refused
 *   @e2e project-hierarchy::the-project-list-shows-subprojects-under-their-parent
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)

/**
 * A programme with two subprojects holding 3 of 10 and 5 of 5 done tasks.
 *
 * @param api The admin context.
 * @param made The objects to remove afterwards.
 */
async function programme(api, made: Array<[string, string]>) {
	const base = { status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] }
	const prog = await createObject(api, 'project', { ...base, title: `Programma Wonen ${RUN}` })
	made.push(['project', prog])
	const children: string[] = []
	for (const [name, done, total] of [['Woningbouw', 3, 10], ['Starterswoningen', 5, 5]] as const) {
		const id = await createObject(api, 'project', { ...base, title: `${name} ${RUN}`, parent: prog })
		made.push(['project', id])
		children.push(id)
		for (let i = 0; i < total; i++) {
			made.push(['task', await createObject(api, 'task', { title: `${name} ${i}`, status: i < done ? 'done' : 'open', project: id })])
		}
	}
	return { prog, children }
}

test.describe('Subprojects', () => {
	test('a programme shows its subprojects', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const { prog } = await programme(api, made)
			await page.goto(new URL(`projects/${prog}/overview`, PLANNINQ_ROOT).toString())

			const rows = page.getByTestId('overview-subproject')
			await expect(rows).toHaveCount(2)
			await expect(page.getByTestId('overview-subprojects')).toContainText('3 of 10')
			await expect(page.getByTestId('overview-subprojects')).toContainText('5 of 5')
			await expect(page.getByTestId('overview-progress')).toContainText('8 of 15 tasks done, including subprojects')
		} finally {
			await removeObjects(api, made)
		}
	})

	test('a cycle is refused', async () => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const { prog, children } = await programme(api, made)
			const res = await api.patch(`${OBJECTS}/project/${prog}`, { data: { parent: children[0] } })
			expect(res.ok()).toBe(false)
			expect(await res.text()).toContain('A project cannot sit under one of its own subprojects.')

			const stored = await (await api.get(`${OBJECTS}/project/${prog}`)).json()
			expect(stored.parent ?? null).toBeNull()
		} finally {
			await removeObjects(api, made)
		}
	})

	test('the project list shows subprojects under their parent', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			await programme(api, made)
			await page.goto(new URL('projects', PLANNINQ_ROOT).toString())

			const parent = page.locator('li', { hasText: `Programma Wonen ${RUN}` })
			const child = page.locator('li[data-depth="1"]', { hasText: `Woningbouw ${RUN}` })
			await expect(child).toBeVisible()

			const toggle = parent.getByTestId('project-subprojects-toggle')
			await expect(toggle).toHaveAttribute('aria-expanded', 'true')
			await toggle.click()
			await expect(toggle).toHaveAttribute('aria-expanded', 'false')
			await expect(child).toHaveCount(0)
		} finally {
			await removeObjects(api, made)
		}
	})
})
