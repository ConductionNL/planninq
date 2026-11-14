/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for the board-columns capability (boards-configurable-columns):
 * the board runs on the project's own column objects.
 *
 *   @e2e board-columns::a-project-with-a-review-column
 *   @e2e board-columns::add-and-rename-a-column
 *   @e2e board-columns::remove-a-column-with-cards
 *   @e2e board-columns::drop-a-card-on-done
 *   @e2e board-columns::go-over-the-limit
 *   @e2e board-columns::reorder-with-the-keyboard
 *
 * The API-level scenarios (a member cannot change columns, a move through the
 * API is stamped, the admin changed the defaults, an upgraded board) carry a
 * reason-bearing `@e2e exclude` in the spec: PHPUnit covers each on the real
 * listener, service or repair step.
 *
 * The fixture seed (fixtures/seed.ts) creates one project owned by the admin,
 * with its four default columns and tasks placed in To do and In progress.
 */

import { expect, test } from '@playwright/test'
import { openFixtureProjectBoard } from './nav.ts'

async function openBoard(page) {
	await openFixtureProjectBoard(page)
	const board = page.locator('[data-cy="kanban-board"]')
	await expect(board).toHaveCount(1)
	return board
}

function lane(board, title: string) {
	return board.locator(`section.kanban-column[data-column="${title}"]`)
}

test.describe('Board columns', () => {
	// @e2e board-columns::a-project-with-a-review-column
	test('the board shows the project\'s own columns in order', async ({ page }) => {
		const board = await openBoard(page)
		const titles = await board.locator('section.kanban-column h3').allTextContents()
		expect(titles.map((title) => title.trim()).slice(0, 4)).toEqual(['To do', 'In progress', 'Review', 'Done'])
	})

	// @e2e board-columns::add-and-rename-a-column
	// @e2e board-columns::remove-a-column-with-cards
	test('the owner adds, renames and removes a column', async ({ page }) => {
		const board = await openBoard(page)
		await page.getByTestId('add-column').click()
		await page.getByTestId('column-name').locator('input').fill('Waiting for applicant')
		await page.getByTestId('column-save').click()
		await expect(lane(board, 'Waiting for applicant')).toHaveCount(1)

		await lane(board, 'Waiting for applicant').getByTestId('column-actions').click()
		await page.getByRole('menuitem', { name: 'Edit column' }).click()
		await page.getByTestId('column-name').locator('input').fill('Waiting')
		await page.getByTestId('column-save').click()
		const titles = await board.locator('section.kanban-column h3').allTextContents()
		expect(titles.map((title) => title.trim()).at(-1)).toBe('Waiting')

		await lane(board, 'Waiting').getByTestId('column-actions').click()
		await page.getByRole('menuitem', { name: 'Remove column' }).click()
		await page.getByTestId('column-remove-confirm').click()
		await expect(lane(board, 'Waiting')).toHaveCount(0)
	})

	// @e2e board-columns::drop-a-card-on-done
	test('moving a card to Done finishes it, also after a reload', async ({ page }) => {
		const board = await openBoard(page)
		const card = lane(board, 'In progress').getByTestId('task-card').first()
		const title = await card.getAttribute('aria-label')
		await card.getByRole('button', { name: 'Move task to another column' }).click()
		await page.getByRole('menuitem', { name: 'Done' }).click()
		await expect(lane(board, 'Done').locator(`[aria-label="${title}"]`)).toHaveCount(1)

		await page.reload()
		const reloaded = await openBoard(page)
		const done = lane(reloaded, 'Done').locator(`[aria-label="${title}"]`)
		await expect(done).toHaveCount(1)

		// Put the card back, so the due-date badge tests still find it in progress.
		await done.getByRole('button', { name: 'Move task to another column' }).click()
		await page.getByRole('menuitem', { name: 'In progress' }).click()
		await expect(lane(reloaded, 'In progress').locator(`[aria-label="${title}"]`)).toHaveCount(1)
	})

	// @e2e board-columns::go-over-the-limit
	test('a lane over its WIP limit says so and still takes the card', async ({ page }) => {
		const board = await openBoard(page)
		const review = lane(board, 'Review')
		// Review has a WIP limit of 2 in the seed; fill it to three.
		for (let i = 0; i < 3; i++) {
			const card = lane(board, 'To do').getByTestId('task-card').first()
			if (await card.count() === 0) {
				break
			}
			await card.getByRole('button', { name: 'Move task to another column' }).click()
			await page.getByRole('menuitem', { name: 'Review' }).click()
		}
		const count = await review.getByTestId('task-card').count()
		test.skip(count <= 2, 'the seed holds fewer than three movable cards')
		await expect(review.getByTestId('column-count')).toContainText(`${count} / 2`)
		await expect(review.getByTestId('column-count')).toContainText('over limit')

		// Put the cards back in To do for the other tests.
		while (await review.getByTestId('task-card').count() > 0) {
			await review.getByTestId('task-card').first().getByRole('button', { name: 'Move task to another column' }).click()
			await page.getByRole('menuitem', { name: 'To do' }).click()
		}
	})

	// @e2e board-columns::reorder-with-the-keyboard
	test('Move up puts a card above its neighbour, also after a reload', async ({ page }) => {
		const board = await openBoard(page)
		const cards = lane(board, 'To do').getByTestId('task-card')
		test.skip(await cards.count() < 2, 'the seed holds fewer than two cards in To do')
		const second = await cards.nth(1).getAttribute('aria-label')
		await cards.nth(1).getByRole('button', { name: 'Move task to another column' }).click()
		await page.getByRole('menuitem', { name: 'Move up' }).click()
		await expect(cards.first()).toHaveAttribute('aria-label', second as string)

		await page.reload()
		const reloaded = await openBoard(page)
		await expect(lane(reloaded, 'To do').getByTestId('task-card').first()).toHaveAttribute('aria-label', second as string)
	})
})
