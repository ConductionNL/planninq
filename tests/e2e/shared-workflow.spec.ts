/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for shared workflows (projects-templates-shared-workflow,
 * section 2).
 *
 *   @e2e shared-workflow::one-change-reaches-every-project-on-the-workflow
 *   @e2e shared-workflow::labels-stay-app-wide-on-a-workflow
 *   @e2e shared-workflow::story-points-on-a-fibonacci-scale
 *   @e2e shared-workflow::moving-a-project-onto-a-workflow
 *
 * Only the workflows section of the admin settings runs as the admin (the
 * suite's stored admin state). Every project page runs as a regular user with
 * an empty storage state, so the boards show what a member sees.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './base-url.ts'
import { signedInPage } from './fresh-user.ts'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36)
const PASSWORD = `Planninq-e2e-${RUN}!`

const VERGUNNING = [
	{ key: 'intake', title: 'Intake', type: 'active' },
	{ key: 'beoordeling', title: 'Beoordeling', type: 'active' },
	{ key: 'besluit', title: 'Besluit', type: 'done' },
]

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
 * The column titles of a project's board, left to right.
 *
 * @param page The page showing the board.
 */
async function boardColumns(page: Page): Promise<string[]> {
	return page.locator('[data-cy="kanban-board"] section.kanban-column').evaluateAll((lanes) => lanes.map((lane) => lane.getAttribute('data-column') ?? ''))
}

test.describe('Shared workflows', () => {
	// @e2e shared-workflow::one-change-reaches-every-project-on-the-workflow
	test('one change reaches every project on the workflow', async ({ page, browser }) => {
		const api = await adminApi()
		const member = `volger-${RUN}`
		const made: Array<[string, string]> = []
		try {
			await createUser(api, member)
			const title = `Vergunningverlening ${RUN}`
			const workflow = await createObject(api, 'workflow', { title, columns: VERGUNNING, estimateScale: 'none', estimateValues: [] })
			made.push(['workflow', workflow])
			const projects: string[] = []
			for (const name of ['Noord', 'Zuid']) {
				const project = await createObject(api, 'project', { title: `${name} ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, member], workflow })
				projects.push(project)
				made.push(['project', project])
			}

			// The admin adds "Bezwaar" after "Besluit" in the workflows section.
			await page.goto(`${BASE_URL}/index.php/settings/admin/planninq`)
			const row = page.getByTestId('workflow-settings').getByTestId('workflow-row').filter({ hasText: title })
			await row.getByRole('button', { name: 'Edit' }).click()
			const dialog = page.getByRole('dialog', { name: 'Edit workflow' })
			await dialog.getByTestId('workflow-add-column').click()
			await dialog.getByTestId('workflow-column').last().getByLabel('Column 4').fill('Bezwaar')
			await dialog.getByTestId('workflow-save').click()
			await expect(dialog).toHaveCount(0)

			for (const project of projects) {
				await expect.poll(async () => (await objectsOf(api, 'column', project)).filter((column) => column.title === 'Bezwaar').length).toBe(1)
				const bezwaar = (await objectsOf(api, 'column', project)).find((column) => column.title === 'Bezwaar')
				expect(bezwaar?.project).toBe(project)
			}

			const { context, page: memberPage } = await signedInPage(browser, member, PASSWORD)
			for (const project of projects) {
				await memberPage.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
				await expect(memberPage.locator('section.kanban-column[data-column="Bezwaar"]')).toBeVisible()
				expect((await boardColumns(memberPage)).at(-1)).toBe('Bezwaar')
			}
			await context.close()
		} finally {
			for (const [, project] of made.filter(([schema]) => schema === 'project')) {
				for (const column of await objectsOf(api, 'column', project)) {
					await api.delete(`${OBJECTS}/column/${column.id}`, { failOnStatusCode: false })
				}
			}
			await removeObjects(api, made.filter(([schema]) => schema === 'project'))
			await removeObjects(api, made.filter(([schema]) => schema === 'workflow'))
			await api.delete(`/ocs/v2.php/cloud/users/${member}`)
		}
	})

	// @e2e shared-workflow::labels-stay-app-wide-on-a-workflow
	test('labels stay app-wide on a workflow', async ({ browser }) => {
		const api = await adminApi()
		const member = `labeler-${RUN}`
		const made: Array<[string, string]> = []
		try {
			await createUser(api, member)
			const labels = [`Spoed ${RUN}`, `Extern ${RUN}`, `Intern ${RUN}`]
			for (const [index, title] of labels.entries()) {
				made.push(['label', await createObject(api, 'label', { title, color: ['#c00000', '#0060a0', '#2e7d32'][index] })])
			}
			const workflow = await createObject(api, 'workflow', { title: `Labels ${RUN}`, columns: VERGUNNING, estimateScale: 'none', estimateValues: [] })
			const project = await createObject(api, 'project', { title: `Labels ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, member], workflow })
			const task = await createObject(api, 'task', { title: `Labeltaak ${RUN}`, project, status: 'open' })
			made.push(['workflow', workflow], ['project', project], ['task', task])

			const { context, page } = await signedInPage(browser, member, PASSWORD)
			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-labels').click()
			for (const title of labels) {
				await expect(page.getByRole('option', { name: title })).toBeVisible()
			}
			await context.close()
		} finally {
			for (const column of made.some(([schema]) => schema === 'project') ? await objectsOf(api, 'column', made.find(([schema]) => schema === 'project')![1]) : []) {
				await api.delete(`${OBJECTS}/column/${column.id}`, { failOnStatusCode: false })
			}
			await removeObjects(api, made)
			await api.delete(`/ocs/v2.php/cloud/users/${member}`)
		}
	})

	// @e2e shared-workflow::story-points-on-a-fibonacci-scale
	test('story points on a Fibonacci scale', async ({ browser }) => {
		const api = await adminApi()
		const member = `schatter-${RUN}`
		const made: Array<[string, string]> = []
		try {
			await createUser(api, member)
			const workflow = await createObject(api, 'workflow', { title: `Fibonacci ${RUN}`, columns: VERGUNNING, estimateScale: 'storyPoints', estimateValues: [1, 2, 3, 5, 8, 13] })
			const project = await createObject(api, 'project', { title: `Punten ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, member], workflow })
			const task = await createObject(api, 'task', { title: `Schattaak ${RUN}`, project, status: 'open' })
			made.push(['workflow', workflow], ['project', project], ['task', task])

			const { context, page } = await signedInPage(browser, member, PASSWORD)
			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('workflow-estimate').click()
			const options = page.getByRole('option')
			await expect(options).toHaveText(['1 pt', '2 pt', '3 pt', '5 pt', '8 pt', '13 pt'])
			await page.getByRole('option', { name: '5 pt', exact: true }).click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json()).storyPoints).toBe(5)

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			const card = page.locator('.task-card').filter({ hasText: `Schattaak ${RUN}` })
			await expect(card.locator('.task-card__estimate-badge')).toHaveText('5 pt')
			await context.close()
		} finally {
			const project = made.find(([schema]) => schema === 'project')?.[1]
			for (const column of project ? await objectsOf(api, 'column', project) : []) {
				await api.delete(`${OBJECTS}/column/${column.id}`, { failOnStatusCode: false })
			}
			await removeObjects(api, made)
			await api.delete(`/ocs/v2.php/cloud/users/${member}`)
		}
	})

	// @e2e shared-workflow::moving-a-project-onto-a-workflow
	test('moving a project onto a workflow', async ({ browser }) => {
		const api = await adminApi()
		const owner = `verhuizer-${RUN}`
		const made: Array<[string, string]> = []
		let project = ''
		try {
			await createUser(api, owner)
			const workflow = await createObject(api, 'workflow', { title: `Vergunningverlening verhuis ${RUN}`, columns: VERGUNNING, estimateScale: 'none', estimateValues: [] })
			made.push(['workflow', workflow])
			project = await createObject(api, 'project', { title: `Verhuis ${RUN}`, status: 'active', owner, members: [owner] })
			made.push(['project', project])

			// The project's own columns: "To Do", "Intake" and "Done", holding 2, 5 and 2 tasks.
			for (const column of await objectsOf(api, 'column', project)) {
				await api.delete(`${OBJECTS}/column/${column.id}`)
			}
			const counts: Record<string, number> = { 'To Do': 2, Intake: 5, Done: 2 }
			for (const [order, title] of Object.keys(counts).entries()) {
				const column = await createObject(api, 'column', { title, project, order, type: title === 'Done' ? 'done' : 'active' })
				for (let index = 0; index < counts[title]; index++) {
					made.push(['task', await createObject(api, 'task', { title: `${title} ${index} ${RUN}`, project, column, status: title === 'Done' ? 'done' : 'open' })])
				}
			}

			const { context, page } = await signedInPage(browser, owner, PASSWORD)
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await page.getByRole('button', { name: 'Project settings' }).click()
			await page.getByTestId('project-workflow').click()
			await page.getByRole('option', { name: `Vergunningverlening verhuis ${RUN}` }).click()
			const dialog = page.getByRole('dialog', { name: `Follow Vergunningverlening verhuis ${RUN}` })
			await expect(dialog.getByTestId('workflow-move-count')).toHaveText('Tasks that move to Intake because their column is not in this workflow: 4')
			await dialog.getByRole('button', { name: 'Confirm' }).click()

			await expect.poll(async () => (await (await api.get(`${OBJECTS}/project/${project}`)).json()).workflow).toBe(workflow)
			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await expect.poll(() => boardColumns(page)).toEqual(['Intake', 'Beoordeling', 'Besluit'])
			const columns = new Set((await objectsOf(api, 'column', project)).map((column) => String(column.id)))
			const tasks = await objectsOf(api, 'task', project)
			expect(tasks).toHaveLength(9)
			expect(tasks.every((task) => columns.has(String(task.column)))).toBe(true)
			await context.close()
		} finally {
			for (const column of project ? await objectsOf(api, 'column', project) : []) {
				await api.delete(`${OBJECTS}/column/${column.id}`, { failOnStatusCode: false })
			}
			await removeObjects(api, made.filter(([schema]) => schema !== 'workflow'))
			await removeObjects(api, made.filter(([schema]) => schema === 'workflow'))
			await api.delete(`/ocs/v2.php/cloud/users/${owner}`)
		}
	})
})
