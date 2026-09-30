/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for column rules (boards-column-automation).
 *
 *   @e2e column-automation::the-owner-adds-an-assign-rule-to-review
 *   @e2e column-automation::moving-a-card-into-review-assigns-the-mover
 *   @e2e column-automation::moving-a-card-by-keyboard-applies-the-rules
 *   @e2e column-automation::the-dialog-offers-only-project-members
 *   @e2e column-automation::a-rule-for-a-former-member-is-skipped
 *   @e2e column-automation::a-move-through-the-api-runs-the-same-rules
 *   @e2e column-automation::editing-a-task-without-moving-it-runs-nothing
 *
 * The suite signs in as the admin only, so "A member who is not the owner
 * cannot change rules" is proven by ColumnOwnerGuardListenerTest (the PATCH)
 * and the board's isOwner check (no Rules entry); see the spec's exclude note.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)
const ANNA = `anna-${RUN}`

async function taskField(api: APIRequestContext, id: string, field: string): Promise<unknown> {
	const res = await api.get(`${OBJECTS}/task/${id}`)
	expect(res.ok()).toBe(true)
	return (await res.json())[field]
}

async function openRules(page: Page, column: string): Promise<void> {
	const header = page.locator(`section[data-column="${column}"]`).first()
	await header.getByTestId('column-actions').click()
	await page.getByRole('menuitem', { name: 'Rules' }).click()
}

test.describe('Column rules', () => {
	test('the owner adds an assign-the-mover rule to Review; a drag and a keyboard move both assign the mover', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Webshop ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, ANNA] })
			made.push(['project', project])
			const todo = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', todo])
			const review = await createObject(api, 'column', { title: 'Review', project, order: 1, status: 'in_progress' })
			made.push(['column', review])
			const dragged = await createObject(api, 'task', { title: `Export to CSV ${RUN}`, project, column: todo, columnOrder: 0, status: 'open', assignedTo: ANNA })
			made.push(['task', dragged])
			const keyed = await createObject(api, 'task', { title: `Import from XLSX ${RUN}`, project, column: todo, columnOrder: 1, status: 'open', assignedTo: ANNA })
			made.push(['task', keyed])

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await expect(page.getByTestId('task-card').filter({ hasText: `Export to CSV ${RUN}` })).toBeVisible()

			// The owner adds "Assign the person who moved the card" to Review.
			await openRules(page, 'Review')
			await expect(page.getByTestId('column-rules-empty')).toBeVisible()
			await page.getByTestId('column-rule-add').click()
			await expect(page.getByTestId('column-rule-action')).toContainText('Assign the person who moved the card')
			await page.getByTestId('column-rules-save').click()
			const icon = page.locator('section[data-column="Review"]').getByTestId('column-rules-icon')
			await expect(icon).toHaveAttribute('aria-label', '1 rule runs when a card enters this column')

			// A drag into Review: stored and shown as assigned to the mover, and announced.
			await page.getByTestId('task-card').filter({ hasText: `Export to CSV ${RUN}` }).dragTo(page.locator('section[data-column="Review"]'))
			await expect(page.getByTestId('board-announcement')).toContainText('Rules applied: assigned to')
			await expect.poll(() => taskField(api, dragged, 'assignedTo')).toBe(ADMIN_USER)

			// The keyboard equivalent: the card's move menu.
			const card = page.getByTestId('task-card').filter({ hasText: `Import from XLSX ${RUN}` })
			await card.getByRole('button', { name: 'Move task to another column' }).click()
			await page.getByRole('menuitem', { name: 'Review' }).click()
			await expect.poll(() => taskField(api, keyed, 'assignedTo')).toBe(ADMIN_USER)
		} finally {
			await removeObjects(api, made)
		}
	})

	test('the dialog offers only project members and marks a rule for a former member, which the server skips', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Webshop B ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER, ANNA] })
			made.push(['project', project])
			const todo = await createObject(api, 'column', { title: 'To do', project, order: 0, status: 'open' })
			made.push(['column', todo])
			const review = await createObject(api, 'column', { title: 'Review', project, order: 1, automation: [{ action: 'assign', value: `carl-${RUN}` }] })
			made.push(['column', review])
			const task = await createObject(api, 'task', { title: `Export to CSV ${RUN}`, project, column: todo, columnOrder: 0, status: 'open', assignedTo: ANNA })
			made.push(['task', task])

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await openRules(page, 'Review')
			await expect(page.getByTestId('column-rule-problem')).toHaveText('No longer a project member')

			// A new "Assign to" rule offers Anna and the owner, not Carl.
			await page.getByTestId('column-rule-add').click()
			await page.getByTestId('column-rule-action').last().click()
			await page.getByRole('option', { name: 'Assign to' }).click()
			await page.getByTestId('column-rule-value').last().click()
			await expect(page.getByRole('option', { name: ANNA })).toBeVisible()
			await expect(page.getByRole('option', { name: `carl-${RUN}` })).toHaveCount(0)
			await page.keyboard.press('Escape')

			// The server skips the stale rule: the move succeeds and the assignee stays.
			const moved = await api.patch(`${OBJECTS}/task/${task}`, { data: { column: review } })
			expect(moved.ok()).toBe(true)
			expect((await moved.json()).assignedTo).toBe(ANNA)
		} finally {
			await removeObjects(api, made)
		}
	})

	test('a move through the API runs the same rules; a title edit runs nothing', async () => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Webshop C ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const blocked = await createObject(api, 'column', { title: 'Blocked', project, order: 0, automation: [{ action: 'setPriority', value: 'high' }] })
			made.push(['column', blocked])
			const task = await createObject(api, 'task', { title: `Export to CSV ${RUN}`, project, priority: 'normal' })
			made.push(['task', task])

			const moved = await api.patch(`${OBJECTS}/task/${task}`, { data: { column: blocked } })
			expect((await moved.json()).priority).toBe('high')

			const edited = await api.patch(`${OBJECTS}/task/${task}`, { data: { title: `Export to CSV and XLSX ${RUN}`, priority: 'low' } })
			expect((await edited.json()).priority).toBe('low')
		} finally {
			await removeObjects(api, made)
		}
	})
})
