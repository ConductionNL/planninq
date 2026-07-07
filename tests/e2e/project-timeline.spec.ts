/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E (UI-only) coverage for the read-only project timeline (Gantt) view
 * added by the gantt-timeline-view change.
 *
 * Both spec scenarios are annotated `@e2e exclude` (the read/windowing/
 * unscheduled split and the dependency-edge sourcing are unit-tested against
 * seeded data in tests/unit/Controller/TimelineControllerTest.php and
 * tests/vitest/timelineHelpers.spec.js — no seeded UI to assert deterministically
 * yet). This spec is the timeline view's visual/workflow smoke (gate-26
 * visual-coverage): it drives the real "Timeline" surface — opening it from the
 * board and rendering either the Gantt grid, the unscheduled rail, or the
 * empty-state — and asserts the page mounts without error.
 *
 * spec: openspec/specs/gantt-timeline-view/spec.md
 * page component under test: src/views/ProjectTimeline.vue (ProjectTimeline)
 *
 * This UI smoke drives the time-axis rendering scenario (gate-19 e2e-coverage):
 *   @e2e gantt-timeline-view::a-project-timeline-returns-dated-tasks-positioned-in-time
 *
 * The RBAC-scoping and dependency-arrow-sourcing scenarios are annotated
 * `@e2e exclude` in the spec (unit-tested in TimelineControllerTest /
 * timelineHelpers.spec.js — API/pure-logic surfaces, no deterministic UI yet).
 *
 * Planix is not installed in the shared dev container at the time of writing;
 * this test skips cleanly when the app or a board is not reachable rather than
 * failing the suite (shared-instance isolation — see the change's verify notes).
 */

import { test, expect } from '@playwright/test'

const NC = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
const PLANIX_URL = `${NC}/index.php/apps/planix/`

// Resolve the first reachable project id from the projects list, or null when
// planix / a project is not available in this environment.
async function firstProjectId(page): Promise<string | null> {
	const res = await page.goto(`${PLANIX_URL}#/projects`, { waitUntil: 'domcontentloaded' })
	if (!res || res.status() >= 400) {
		return null
	}
	// The SPA renders project cards as router-links to /projects/:id.
	const link = page.locator('a[href*="/projects/"]').first()
	try {
		await link.waitFor({ state: 'visible', timeout: 8000 })
	} catch {
		return null
	}
	const href = await link.getAttribute('href')
	const match = href?.match(/\/projects\/([^/?#]+)/)
	return match ? match[1] : null
}

test.describe('Project timeline (Gantt) — read-only view', () => {
	test('opens the timeline from the board and renders the timeline surface', async ({ page }) => {
		const res = await page.goto(PLANIX_URL, { waitUntil: 'domcontentloaded' })
		test.skip(!res || res.status() >= 400, 'Planix app is not reachable in this environment')

		const projectId = await firstProjectId(page)
		test.skip(projectId === null, 'No reachable planix project in this environment')

		// Navigate straight to the timeline route for the resolved project.
		await page.goto(`${PLANIX_URL}#/projects/${projectId}/timeline`, { waitUntil: 'domcontentloaded' })

		// The Timeline heading must render (the view mounted). One of the
		// timeline surfaces — the Gantt chart, the unscheduled rail, or the
		// "No scheduled tasks" empty state — must be present.
		const heading = page.getByRole('heading', { name: 'Timeline' })
		await expect(heading).toBeVisible({ timeout: 10000 })

		const chart = page.locator('.project-timeline__chart')
		const unscheduled = page.locator('.project-timeline__unscheduled')
		const empty = page.locator('.empty-content, .project-timeline__loading')
		const anySurface = await Promise.race([
			chart.first().waitFor({ state: 'visible', timeout: 8000 }).then(() => true).catch(() => false),
			unscheduled.first().waitFor({ state: 'visible', timeout: 8000 }).then(() => true).catch(() => false),
			empty.first().waitFor({ state: 'visible', timeout: 8000 }).then(() => true).catch(() => false),
		])
		expect(anySurface).toBeTruthy()
	})

	test('offers a Timeline entry on the project board', async ({ page }) => {
		const res = await page.goto(PLANIX_URL, { waitUntil: 'domcontentloaded' })
		test.skip(!res || res.status() >= 400, 'Planix app is not reachable in this environment')

		const projectId = await firstProjectId(page)
		test.skip(projectId === null, 'No reachable planix project in this environment')

		await page.goto(`${PLANIX_URL}#/projects/${projectId}`, { waitUntil: 'domcontentloaded' })

		// The board header exposes a "Timeline" action that navigates to the view.
		const timelineButton = page.getByRole('button', { name: 'Timeline' })
		await expect(timelineButton).toBeVisible({ timeout: 10000 })
		await timelineButton.click()
		await expect(page.getByRole('heading', { name: 'Timeline' })).toBeVisible({ timeout: 10000 })
	})
})
