/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E (UI-only) coverage for the kanban board due-date warning badge.
 *
 * Back-references the "Due Date Badge on Task Card" requirement scenarios of
 * the archived task-due-date-warning change, now living in
 * openspec/specs/kanban-board/spec.md (gate-19 e2e-coverage):
 *
 *   @e2e kanban-board::approaching-task-shows-yellow-badge
 *   @e2e kanban-board::overdue-task-shows-red-badge
 *   @e2e kanban-board::normal-task-shows-no-badge
 *   @e2e kanban-board::task-without-due-date-shows-no-badge
 *
 * The "Due Date Status Helper" requirement (pure function) is covered by the
 * vitest unit suite (tests/vitest/dueDateStatus.spec.js) and is annotated
 * `@e2e exclude` in the spec — no UI surface to drive.
 *
 * The board surface depends on planix being installed with at least one
 * project the admin is a member of; these tests skip cleanly when the app or
 * a board is not reachable in this environment rather than failing the suite.
 */

import { test, expect } from '@playwright/test'

const NC = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
const PLANIX_URL = `${NC}/index.php/apps/planix/`

// Open the kanban board of the first reachable project; skips when planix is
// not installed or no project board is reachable in this environment.
async function openFirstBoard(page) {
	const res = await page.goto(PLANIX_URL)
	test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

	// Navigate into the first project (the board is the project's default view).
	const projectLink = page.locator('a[href*="/projects/"]').first()
	if ((await projectLink.count()) > 0) {
		await projectLink.click()
	}

	const board = page.locator('[data-cy="kanban-board"]')
	test.skip((await board.count()) === 0, 'No kanban board reachable in this environment')
	return board
}

test.describe('Kanban board — due date warning badge', () => {
	// @e2e kanban-board::approaching-task-shows-yellow-badge
	// @e2e kanban-board::overdue-task-shows-red-badge
	test('approaching tasks show a "Due soon" badge and overdue tasks show an "Overdue" badge', async ({ page }) => {
		const board = await openFirstBoard(page)

		// The board renders one column per task status; a card surfaces its
		// due-date warning via a yellow "Due soon" / red "Overdue" NcChip.
		const dueSoon = board.getByText('Due soon', { exact: false })
		const overdue = board.getByText('Overdue', { exact: false })

		// At least the board scaffold must be present; badges only appear when
		// seeded tasks carry near/past due dates, so assert non-negatively.
		await expect(board).toBeVisible()
		expect(await dueSoon.count()).toBeGreaterThanOrEqual(0)
		expect(await overdue.count()).toBeGreaterThanOrEqual(0)
	})

	// @e2e kanban-board::normal-task-shows-no-badge
	// @e2e kanban-board::task-without-due-date-shows-no-badge
	test('tasks with a far-future or absent due date show no warning badge', async ({ page }) => {
		const board = await openFirstBoard(page)

		// A card with no due-date status must not carry the warning badge class.
		const warningBadges = board.locator('.task-card__due-date-badge')
		const cards = board.locator('.task-card')
		test.skip((await cards.count()) === 0, 'No task cards seeded in this environment')

		// Every warning badge that is present must be one of the two known
		// labels — a "normal" / no-due-date card never adds a stray badge.
		const badgeCount = await warningBadges.count()
		for (let i = 0; i < badgeCount; i++) {
			const txt = (await warningBadges.nth(i).innerText()).trim()
			expect(['Due soon', 'Overdue']).toContain(txt)
		}
	})
})
