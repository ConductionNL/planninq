/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the backlog capability (backlog-list).
 *
 *   @e2e backlog::open-the-backlog
 *   @e2e backlog::create-into-the-backlog
 *   @e2e backlog::rank-with-the-keyboard
 *   @e2e backlog::plan-a-task
 *   @e2e backlog::take-a-card-off-the-board
 *
 * "Sort by due date" carries an `@e2e exclude` in the spec: the backlog has
 * no due-date field to set from the UI yet, and tests/vitest/backlog.spec.js
 * covers the comparator.
 *
 * Titles carry a run id so repeated nightly runs do not collide.
 */

import { expect, test } from '@playwright/test'
import { openFixtureProjectBoard } from './nav.ts'

async function openBacklog(page) {
	const projectId = await openFixtureProjectBoard(page)
	await page.getByRole('button', { name: 'View backlog' }).click()
	await expect(page).toHaveURL(new RegExp(`/projects/${projectId}/backlog`))
	return projectId
}

async function addTask(page, title: string) {
	await page.getByTestId('backlog-new-task').locator('input').fill(title)
	await page.getByTestId('backlog-add').click()
	await expect(page.locator(`[data-testid="backlog-row"][data-title="${title}"]`)).toHaveCount(1)
}

async function rowTitles(page) {
	return page.getByTestId('backlog-row').evaluateAll((rows) => rows.map((row) => row.getAttribute('data-title')))
}

test.describe('Backlog', () => {
	// @e2e backlog::open-the-backlog
	// @e2e backlog::create-into-the-backlog
	test('a new task lands last in the backlog and not on the board', async ({ page }) => {
		const title = `Check the zoning plan ${Date.now()}`
		await openBacklog(page)
		await addTask(page, title)
		expect((await rowTitles(page)).at(-1)).toBe(title)

		await page.goBack()
		await expect(page.locator('[data-cy="kanban-board"]')).toHaveCount(1)
		await expect(page.locator(`[data-testid="task-card"][aria-label="${title}"]`)).toHaveCount(0)
	})

	// @e2e backlog::rank-with-the-keyboard
	test('Move up twice on C gives C, A, B, also after a reload', async ({ page }) => {
		const run = Date.now()
		const [a, b, c] = ['A', 'B', 'C'].map((name) => `${name} ${run}`)
		await openBacklog(page)
		for (const title of [a, b, c]) {
			await addTask(page, title)
		}
		for (let i = 0; i < 2; i++) {
			await page.locator(`[data-testid="backlog-row"][data-title="${c}"]`).getByRole('button', { name: 'Task actions' }).click()
			await page.getByRole('menuitem', { name: 'Move up' }).click()
		}
		const ours = (titles) => titles.filter((title) => [a, b, c].includes(title))
		expect(ours(await rowTitles(page))).toEqual([c, a, b])

		await page.reload()
		await expect(page.getByTestId('backlog-list')).toBeVisible()
		expect(ours(await rowTitles(page))).toEqual([c, a, b])
	})

	// @e2e backlog::plan-a-task
	// @e2e backlog::take-a-card-off-the-board
	test('a task moves to the board and back to the backlog', async ({ page }) => {
		const title = `Plan me ${Date.now()}`
		await openBacklog(page)
		await addTask(page, title)
		await page.locator(`[data-testid="backlog-row"][data-title="${title}"]`).getByRole('button', { name: 'Task actions' }).click()
		await page.getByRole('menuitem', { name: 'In progress' }).click()
		await expect(page.locator(`[data-testid="backlog-row"][data-title="${title}"]`)).toHaveCount(0)

		await page.goBack()
		const card = page.locator('section.kanban-column[data-column="In progress"]').locator(`[data-testid="task-card"][aria-label="${title}"]`)
		await expect(card).toHaveCount(1)

		await card.getByRole('button', { name: 'Move task to another column' }).click()
		await page.getByRole('menuitem', { name: 'Move to backlog' }).click()
		await expect(page.locator(`[data-testid="task-card"][aria-label="${title}"]`)).toHaveCount(0)

		await page.getByRole('button', { name: 'View backlog' }).click()
		expect((await rowTitles(page)).at(-1)).toBe(title)
	})
})
