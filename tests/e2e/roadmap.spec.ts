/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E coverage for releases and the project roadmap (backlog-releases-roadmap).
 *
 *   @e2e project-roadmap::the-member-opens-the-roadmap-from-the-project-tabs
 *   @e2e project-roadmap::the-roadmap-shows-releases-and-epics-over-time
 *   @e2e project-roadmap::an-epic-with-no-dates-is-not-dropped
 *   @e2e project-roadmap::the-release-list-works-without-the-chart
 *   @e2e project-roadmap::a-member-makes-an-epic-and-links-a-task-to-it
 *   @e2e project-roadmap::another-projects-epic-is-not-offered
 *   @e2e project-roadmap::the-features-and-roadmap-page-is-unchanged
 *   @e2e releases::a-member-creates-a-release
 *   @e2e releases::a-member-plans-a-task-against-a-release
 *   @e2e releases::only-the-projects-own-releases-are-offered
 *   @e2e releases::shipping-with-everything-done
 *   @e2e releases::shipping-asks-about-unfinished-tasks
 *   @e2e releases::progress-counts-done-and-cancelled-tasks
 *
 * "A non-member cannot see the project's releases" carries an `@e2e exclude`:
 * the suite signs in as the admin only.
 */

import { expect, test } from '@playwright/test'
import { PLANNINQ_ROOT } from './nav.ts'
import { ADMIN_USER, adminApi, createObject, OBJECTS, removeObjects } from './portfolio-api.ts'

const RUN = Date.now().toString(36).slice(-6)

test.describe('Releases and the roadmap', () => {
	test('member opens the roadmap from the project tabs, and sees releases, epics and progress', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Portaal ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const release = await createObject(api, 'projectRelease', { title: `Version 2.0 ${RUN}`, project, releaseDate: '2026-12-01', status: 'planned' })
			made.push(['projectRelease', release])
			const epic = await createObject(api, 'task', { title: `Self-service export ${RUN}`, project, issueType: 'epic' })
			const undated = await createObject(api, 'task', { title: `Later ${RUN}`, project, issueType: 'epic' })
			made.push(['task', epic], ['task', undated])
			for (const [title, status, start, due] of [['A', 'done', '2026-10-05', '2026-10-20'], ['B', 'done', null, '2026-11-20'], ['C', 'cancelled', null, null], ['D', 'open', null, null], ['E', 'open', null, null]]) {
				made.push(['task', await createObject(api, 'task', { title: `${title} ${RUN}`, project, status, startDate: start, dueDate: due, epic, release })])
			}

			await page.goto(new URL(`projects/${project}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('project-tab-timeline').click()
			await page.getByTestId('timeline-view-roadmap').click()
			await expect(page).toHaveURL(new RegExp(`projects/${project}/timeline\\?view=roadmap`))
			await expect(page.getByText(`Portaal ${RUN}`).first()).toBeVisible()
			await page.reload()
			await expect(page.getByTestId('project-roadmap')).toBeVisible()

			await expect(page.locator('[data-testid="roadmap-release-marker"][data-date="2026-12-01"]')).toContainText(`Version 2.0 ${RUN}`)
			const bar = page.getByTestId('roadmap-epic-bar').filter({ hasText: `Self-service export ${RUN}` })
			await expect(bar).toHaveAttribute('data-start', '2026-10-05')
			await expect(bar).toHaveAttribute('data-end', '2026-11-20')
			await expect(page.getByTestId('epic-unscheduled')).toContainText(`Later ${RUN}`)

			const row = page.getByTestId(`release-row-${release}`)
			await expect(row.getByTestId('release-progress')).toHaveText('3 of 5 tasks done')
			await row.getByRole('button', { name: `Edit Version 2.0 ${RUN}` }).focus()
			await expect(row.getByRole('button', { name: `Edit Version 2.0 ${RUN}` })).toBeFocused()
		} finally {
			await removeObjects(api, made.reverse())
		}
	})

	test('member creates a release, and shipping asks about unfinished tasks', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Loket ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project])
			const next = await createObject(api, 'projectRelease', { title: `Version 2.1 ${RUN}`, project, releaseDate: '2027-02-01', status: 'planned' })
			made.push(['projectRelease', next])

			await page.goto(new URL(`projects/${project}/timeline?view=roadmap`, PLANNINQ_ROOT).toString())
			await page.getByTestId('release-new').click()
			await page.getByTestId('release-title').locator('input').fill(`Version 2.0 ${RUN}`)
			await page.getByTestId('release-date').locator('input').fill('2026-12-01')
			await page.getByTestId('release-save').click()
			await expect(page.getByTestId('release-list')).toContainText(`Version 2.0 ${RUN}`)
			const rows = await (await api.get(`${OBJECTS}/projectRelease?project=${project}&title=${encodeURIComponent(`Version 2.0 ${RUN}`)}`)).json()
			const release = (rows.results || [])[0]
			expect(release.status).toBe('planned')
			made.push(['projectRelease', release.id])
			await expect(page.getByTestId(`release-row-${release.id}`).getByTestId('release-progress')).toHaveText('0 of 0 tasks done')

			made.push(['task', await createObject(api, 'task', { title: `Done ${RUN}`, project, status: 'done', release: release.id })])
			const open = await createObject(api, 'task', { title: `Open ${RUN}`, project, status: 'open', release: release.id })
			made.push(['task', open])
			await page.reload()

			await page.getByTestId(`release-row-${release.id}`).getByTestId('release-ship').click()
			await expect(page.getByTestId('release-ship-open-tasks')).toContainText(`Open ${RUN}`)
			await expect(page.getByTestId('release-ship-clear')).toBeVisible()
			await expect(page.getByTestId('release-ship-keep')).toBeVisible()
			await page.getByTestId(`release-ship-move-${next}`).click()
			await page.getByTestId('release-ship-confirm').click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${open}`)).json()).release).toBe(next)
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/projectRelease/${release.id}`)).json()).status).toBe('released')

			// Shipping with everything done: the dialog asks nothing.
			await page.getByTestId(`release-row-${next}`).getByTestId('release-ship').click()
			await expect(page.getByTestId('release-ship-open-tasks')).toContainText(`Open ${RUN}`)
			await page.keyboard.press('Escape')
			await api.patch(`${OBJECTS}/task/${open}`, { data: { status: 'done' } })
			await page.reload()
			await page.getByTestId(`release-row-${next}`).getByTestId('release-ship').click()
			await expect(page.getByTestId('release-ship-open-tasks')).toHaveCount(0)
			await page.getByTestId('release-ship-confirm').click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/projectRelease/${next}`)).json()).releasedAt).toBeTruthy()
		} finally {
			await removeObjects(api, made.reverse())
		}
	})

	test('member plans a task against a release and links it to an epic, and pickers offer only this project', async ({ page }) => {
		const api = await adminApi()
		const made: Array<[string, string]> = []
		try {
			const project = await createObject(api, 'project', { title: `Archief ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			const other = await createObject(api, 'project', { title: `Elders ${RUN}`, status: 'active', owner: ADMIN_USER, members: [ADMIN_USER] })
			made.push(['project', project], ['project', other])
			const release = await createObject(api, 'projectRelease', { title: `Version 2.0 ${RUN}`, project, status: 'planned' })
			const foreign = await createObject(api, 'projectRelease', { title: `Version 9 ${RUN}`, project: other, status: 'planned' })
			made.push(['projectRelease', release], ['projectRelease', foreign])
			const epic = await createObject(api, 'task', { title: `Self-service export ${RUN}`, project })
			const foreignEpic = await createObject(api, 'task', { title: `Elsewhere epic ${RUN}`, project: other, issueType: 'epic' })
			const task = await createObject(api, 'task', { title: `Export to CSV ${RUN}`, project })
			made.push(['task', epic], ['task', foreignEpic], ['task', task])

			await page.goto(new URL(`projects/${project}/tasks/${epic}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-is-epic').click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${epic}`)).json()).issueType).toBe('epic')

			await page.goto(new URL(`projects/${project}/tasks/${task}`, PLANNINQ_ROOT).toString())
			await page.getByTestId('task-release').click()
			await expect(page.getByRole('option', { name: `Version 9 ${RUN}` })).toHaveCount(0)
			await page.getByRole('option', { name: `Version 2.0 ${RUN}` }).click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json()).release).toBe(release)

			await page.getByTestId('task-epic').click()
			await expect(page.getByRole('option', { name: `Elsewhere epic ${RUN}` })).toHaveCount(0)
			await page.getByRole('option', { name: `Self-service export ${RUN}` }).click()
			await expect.poll(async () => (await (await api.get(`${OBJECTS}/task/${task}`)).json()).epic).toBe(epic)
		} finally {
			await removeObjects(api, made.reverse())
		}
	})

	test('features and roadmap page is unchanged', async ({ page }) => {
		await page.goto(new URL('features-roadmap', PLANNINQ_ROOT).toString())
		await expect(page).toHaveURL(/features-roadmap/)
		await expect(page.getByTestId('project-roadmap')).toHaveCount(0)
		await expect(page.getByTestId('release-list')).toHaveCount(0)
	})
})
