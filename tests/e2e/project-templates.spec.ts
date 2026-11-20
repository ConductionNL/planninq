/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for project templates and copies (projects-templates-shared-workflow,
 * section 1).
 *
 *   @e2e project-templates::marking-a-project-as-a-template
 *   @e2e project-templates::starting-a-project-from-a-template
 *   @e2e project-templates::copying-a-project-without-its-people
 *
 * "A failed copy leaves nothing behind" carries an `@e2e exclude` in the spec:
 * failure injection needs a mocked ObjectService, so ProjectCopyServiceTest
 * asserts it.
 *
 * Every person in the UI is a regular user with an empty storage state, so no
 * admin session decides what they see. The admin context only seeds fixtures
 * through the API and removes them afterwards.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`

/**
 * The objects of one schema that belong to a project.
 *
 * @param api The admin context.
 * @param schema The schema slug.
 * @param project The project id.
 */
async function objectsOf(api: APIRequestContext, schema: string, project: string): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(`${OBJECTS}/${schema}?project=${project}&_limit=200`)
	expect(res.ok(), `list ${schema}: ${res.status()}`).toBe(true)
	return (await res.json()).results ?? []
}

/**
 * Create a regular Nextcloud user.
 *
 * @param api The admin context.
 * @param userid The user id.
 */
async function createUser(api: APIRequestContext, userid: string): Promise<void> {
	expect((await api.post('/ocs/v2.php/cloud/users', { data: { userid, password: PASSWORD } })).ok()).toBe(true)
}

/**
 * The id of the project the page shows, from /projects/:id.
 *
 * @param url The page URL.
 */
function projectIdOf(url: string): string {
	const match = new URL(url).pathname.match(/\/projects\/([0-9a-f-]{36})/)
	expect(match, `a project page, not ${url}`).not.toBeNull()
	return match![1]
}

test.describe('Project templates', () => {
	// @e2e project-templates::marking-a-project-as-a-template
	test('marking a project as a template', async ({ browser }) => {
		const api = await adminApi()
		const owner = `eigenaar-${RUN}`
		const made: Array<[string, string]> = []
		try {
			await createUser(api, owner)
			const title = `Sjabloon ${RUN}`
			const project = await createObject(api, 'project', { title, status: 'active', owner, members: [owner] })
			made.push(['project', project])

			const { context, page } = await signedInPage(browser, owner, PASSWORD)
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: 'Project settings' }).click()
			await page.getByTestId('project-is-template').getByRole('checkbox').check({ force: true })
			await page.getByRole('button', { name: 'Save', exact: true }).click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/project/${project}`)).json()).isTemplate).toBe(true)

			await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: 'Templates' }).click()
			await expect(page.getByText(title)).toBeVisible()
			await page.getByRole('button', { name: 'Active' }).click()
			await expect(page.getByText(title)).toHaveCount(0)
			await context.close()
		} finally {
			await removeObjects(api, made)
			await api.delete(`/ocs/v2.php/cloud/users/${owner}`)
		}
	})

	// @e2e project-templates::starting-a-project-from-a-template
	test('starting a project from a template', async ({ browser }) => {
		const api = await adminApi()
		const creator = `starter-${RUN}`
		const made: Array<[string, string]> = []
		try {
			await createUser(api, creator)
			const template = await createObject(api, 'project', {
				title: `Aanbesteding ${RUN}`,
				status: 'active',
				isTemplate: true,
				startDate: '2026-03-01',
				owner: ADMIN_USER,
				members: [ADMIN_USER, creator],
			})
			made.push(['project', template])
			for (const [order, title] of ['Voorbereiding', 'Publicatie'].entries()) {
				made.push(['projectPhase', await createObject(api, 'projectPhase', { title, project: template, order })])
			}
			const columns = await objectsOf(api, 'column', template)
			const firstColumn = columns.length ? String(columns[0].id) : undefined
			for (let index = 1; index <= 12; index++) {
				const dueDate = index === 1 ? '2026-03-15' : `2026-03-${String(index + 15).padStart(2, '0')}`
				made.push(['task', await createObject(api, 'task', { title: `Stap ${index} ${RUN}`, project: template, status: index % 3 === 0 ? 'done' : 'open', column: firstColumn, dueDate })])
			}

			const { context, page } = await signedInPage(browser, creator, PASSWORD)
			await page.goto(new URL('projects', PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: 'New project' }).click()
			const dialog = page.getByRole('dialog', { name: 'New project' })
			await dialog.getByTestId('project-creation-template').click()
			await page.getByRole('option', { name: `Aanbesteding ${RUN}` }).click()
			await dialog.getByLabel('Project title').fill(`Aanbesteding wegbeheer ${RUN}`)
			await dialog.getByTestId('project-creation-start-date').locator('input').fill('2026-06-01')
			await dialog.getByRole('button', { name: 'Create project' }).click()

			await page.waitForURL(/\/projects\/[0-9a-f-]{36}/)
			const copy = projectIdOf(page.url())
			made.unshift(['project', copy])

			const copiedColumns = await objectsOf(api, 'column', copy)
			expect(copiedColumns.map((column) => column.title).sort()).toEqual(columns.map((column) => column.title).sort())
			for (const column of columns) {
				await expect(page.locator(`section.kanban-column[data-column="${column.title}"]`)).toBeVisible()
			}

			const tasks = await objectsOf(api, 'task', copy)
			expect(tasks).toHaveLength(12)
			expect(tasks.every((task) => task.status === 'open')).toBe(true)
			expect(tasks.find((task) => task.title === `Stap 1 ${RUN}`)?.dueDate).toBe('2026-06-15')
			expect(await objectsOf(api, 'projectPhase', copy)).toHaveLength(2)
			for (const task of tasks) {
				made.unshift(['task', String(task.id)])
			}
			await context.close()
		} finally {
			await removeObjects(api, made)
			await api.delete(`/ocs/v2.php/cloud/users/${creator}`)
		}
	})

	// @e2e project-templates::copying-a-project-without-its-people
	test('copying a project without its people', async ({ browser }) => {
		const api = await adminApi()
		const manager = `beheerder-${RUN}`
		const others = [`lid-a-${RUN}`, `lid-b-${RUN}`, `lid-c-${RUN}`]
		const made: Array<[string, string]> = []
		try {
			for (const userid of [manager, ...others]) {
				await createUser(api, userid)
			}
			const project = await createObject(api, 'project', {
				title: `Bron ${RUN}`,
				status: 'active',
				owner: ADMIN_USER,
				managers: [manager],
				members: [manager, ...others],
			})
			made.push(['project', project])
			const tasks: string[] = []
			for (let index = 1; index <= 20; index++) {
				const task = await createObject(api, 'task', { title: `Werk ${index} ${RUN}`, project, status: 'open' })
				tasks.push(task)
				made.push(['task', task])
			}
			for (const [blocker, blocked] of [[tasks[0], tasks[1]], [tasks[2], tasks[3]]]) {
				made.push(['dependency', await createObject(api, 'dependency', { blocker, blocked, project })])
			}
			const columns = await objectsOf(api, 'column', project)

			const { context, page } = await signedInPage(browser, manager, PASSWORD)
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: 'Project settings' }).click()
			await page.getByTestId('project-copy').click()
			const dialog = page.getByRole('dialog', { name: 'Copy project' })
			await dialog.getByTestId('project-copy-people').getByRole('checkbox').uncheck({ force: true })
			await dialog.getByTestId('project-copy-title').locator('input').fill(`Kopie ${RUN}`)
			await dialog.getByRole('button', { name: 'Copy project' }).click()

			await page.waitForURL((url) => /\/projects\/[0-9a-f-]{36}/.test(url.pathname) && !url.pathname.includes(project))
			const copy = projectIdOf(page.url())
			made.unshift(['project', copy])

			const copiedTasks = await objectsOf(api, 'task', copy)
			for (const task of copiedTasks) {
				made.unshift(['task', String(task.id)])
			}
			expect(copiedTasks).toHaveLength(20)
			expect(copiedTasks.every((task) => task.status === 'open')).toBe(true)
			expect(await objectsOf(api, 'column', copy)).toHaveLength(columns.length)

			const copiedIds = new Set(copiedTasks.map((task) => String(task.id)))
			const dependencies = await objectsOf(api, 'dependency', copy)
			for (const dependency of dependencies) {
				made.unshift(['dependency', String(dependency.id)])
			}
			expect(dependencies).toHaveLength(2)
			for (const dependency of dependencies) {
				expect(copiedIds.has(String(dependency.blocker))).toBe(true)
				expect(copiedIds.has(String(dependency.blocked))).toBe(true)
			}

			const copied = await (await api.get(`${OBJECTS}/project/${copy}`)).json()
			expect(copied.owner).toBe(manager)
			expect([...(copied.members ?? []), ...(copied.managers ?? []), ...(copied.viewers ?? [])].filter((user) => user !== manager)).toEqual([])
			await context.close()
		} finally {
			await removeObjects(api, made)
			for (const userid of [manager, ...others]) {
				await api.delete(`/ocs/v2.php/cloud/users/${userid}`)
			}
		}
	})
})
