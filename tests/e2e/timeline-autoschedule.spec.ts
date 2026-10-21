/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for auto-scheduling on the timeline (planning-timeline-editing,
 * section 3): the preview, Move all, Only this task, a relates link, and a
 * project with auto-scheduling off.
 *
 *   @e2e gantt-timeline-view::a-slip-shows-the-preview-and-moves-the-chain
 *   @e2e gantt-timeline-view::a-member-moves-only-the-task-they-dragged
 *   @e2e gantt-timeline-view::a-relates-link-moves-nothing
 *   @e2e gantt-timeline-view::without-auto-scheduling-only-the-dragged-task-moves
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

/**
 * A task's stored dates.
 *
 * @param api The admin context.
 * @param id The task id.
 */
async function dates(api: APIRequestContext, id: string): Promise<string> {
	const task = await (await api.get(`${OBJECTS}/task/${id}`)).json()
	return `${String(task.startDate).slice(0, 10)}..${String(task.dueDate).slice(0, 10)}`
}

/**
 * Drag the due end of a task's bar by a number of days.
 *
 * @param page The page.
 * @param taskId The task.
 * @param days Days to move.
 */
async function dragDue(page: Page, taskId: string, days: number): Promise<void> {
	const pxPerDay = (await page.locator('.project-timeline__tick').first().boundingBox())?.width ?? 0
	const handle = page.locator(`[data-testid="timeline-bar"][data-task-id="${taskId}"]`).getByTestId('timeline-bar-due')
	const box = await handle.boundingBox()
	if (!box || !pxPerDay) {
		throw new Error('no bar')
	}
	await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
	await page.mouse.down()
	await page.mouse.move(box.x + box.width / 2 + days * pxPerDay, box.y + box.height / 2, { steps: 8 })
	await page.mouse.up()
}

test.describe('Auto-scheduling on the timeline', () => {
	test('preview, move all, only this task, relates and off', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		const link = async (blocker: string, blocked: string, type = 'blocks') => {
			const res = await api.post('/index.php/apps/planninq/api/dependencies', { data: { blocker, blocked, type } })
			expect(res.ok(), `link: ${res.status()}`).toBe(true)
		}
		try {
			const project = await createObject(api, 'project', { title: `Chain ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], autoSchedule: true, startDate: '2026-09-01', endDate: '2026-12-31' })
			made.push(['project', project])
			const task = async (title: string, startDate: string, dueDate: string, projectId = project) => {
				const id = await createObject(api, 'task', { title: `${title} ${RUN}`, status: 'open', project: projectId, startDate, dueDate })
				made.push(['task', id])
				return id
			}
			const a = await task('A', '2026-10-05', '2026-10-09')
			const b = await task('B', '2026-10-12', '2026-10-14')
			const c = await task('C', '2026-10-15', '2026-10-16')
			const d = await task('D', '2026-10-12', '2026-10-13')
			await link(a, b)
			await link(b, c)
			await link(a, d, 'relates')

			// A slip shows the preview; Move all moves the chain.
			await page.goto(new URL(`projects/${project}/timeline`, PLANNINQ_ROOT).toString())
			await expect(page.locator(`[data-testid="timeline-bar"][data-task-id="${a}"]`)).toBeVisible({ timeout: 30_000 })
			await expect(page.locator('[data-testid="timeline-edge"][stroke-dasharray]')).toHaveCount(1)
			await dragDue(page, a, 4)
			const rows = page.getByTestId('reschedule-row')
			await expect(rows).toHaveCount(2)
			await expect(rows.nth(0)).toContainText(`B ${RUN}`)
			await expect(rows.nth(1)).toContainText(`C ${RUN}`)
			await expect(page.getByTestId('reschedule-preview')).not.toContainText(`D ${RUN}`)
			await page.getByTestId('reschedule-all').click()
			await expect.poll(() => dates(api, c), { timeout: 15_000 }).toBe('2026-10-19..2026-10-20')
			expect(await dates(api, a)).toBe('2026-10-05..2026-10-13')
			expect(await dates(api, b)).toBe('2026-10-14..2026-10-16')
			expect(await dates(api, d)).toBe('2026-10-12..2026-10-13')

			// Only this task: A moves, B and C keep their dates.
			for (const [id, start, due] of [[a, '2026-10-05', '2026-10-09'], [b, '2026-10-12', '2026-10-14'], [c, '2026-10-15', '2026-10-16']]) {
				await api.patch(`${OBJECTS}/task/${id}`, { data: { startDate: start, dueDate: due } })
			}
			await page.reload()
			await expect(page.locator(`[data-testid="timeline-bar"][data-task-id="${a}"]`)).toBeVisible({ timeout: 30_000 })
			await dragDue(page, a, 4)
			await page.getByTestId('reschedule-only').click()
			await expect.poll(() => dates(api, a), { timeout: 15_000 }).toBe('2026-10-05..2026-10-13')
			expect(await dates(api, b)).toBe('2026-10-12..2026-10-14')
			expect(await dates(api, c)).toBe('2026-10-15..2026-10-16')

			// Without auto-scheduling: no preview, only the dragged task moves.
			const off = await createObject(api, 'project', { title: `No chain ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER], startDate: '2026-09-01', endDate: '2026-12-31' })
			made.push(['project', off])
			const e = await task('E', '2026-10-05', '2026-10-09', off)
			const f = await task('F', '2026-10-12', '2026-10-14', off)
			await link(e, f)
			await page.goto(new URL(`projects/${off}/timeline`, PLANNINQ_ROOT).toString())
			await expect(page.locator(`[data-testid="timeline-bar"][data-task-id="${e}"]`)).toBeVisible({ timeout: 30_000 })
			await dragDue(page, e, 4)
			await expect.poll(() => dates(api, e), { timeout: 15_000 }).toBe('2026-10-05..2026-10-13')
			await expect(page.getByTestId('reschedule-preview')).toHaveCount(0)
			expect(await dates(api, f)).toBe('2026-10-12..2026-10-14')
		} finally {
			await removeObjects(api, made)
		}
	})
})
