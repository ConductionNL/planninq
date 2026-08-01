/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared navigation helpers for the planix e2e suite.
 *
 * Why these exist — three navigation bugs the specs all shared, each of which
 * produced a failure (or a skip) that looked like missing UI:
 *
 *  1. `a[href*="/projects/"]` matched NOTHING. Planix renders project rows as
 *     `<li class="project-list-item" role="option">` with a click handler that
 *     calls `router.push()`; there is not a single project anchor in the app.
 *     Every spec that "clicked the first project link" therefore stayed on the
 *     Dashboard and then failed on the board assertion — and
 *     `project-timeline.spec.ts` resolved `null` and `test.skip()`ed itself, so
 *     it has never executed at all.
 *
 *  2. `#/projects/...` hash routes. The router is `createWebHistory`, not a
 *     hash history, so a `#/…` fragment is never a route — the app just renders
 *     the Dashboard.
 *
 *  3. `/index.php/apps/planix/<subroute>`. The router base comes from
 *     `generateUrl('/apps/planix')`, which on this instance is `/apps/planix`
 *     — WITHOUT `index.php`. A deep link carrying `/index.php` does not start
 *     with the base, matches no route, and the catch-all redirects to the
 *     Dashboard. (The bare app root happens to survive this, which is why only
 *     the sub-route specs broke.)
 *
 * The rule these encode: enter at the app root, then drive the UI the way a
 * user does. In-app navigation always produces URLs the router accepts.
 */

import { expect, type Page } from '@playwright/test'
import { BASE_URL } from './base-url'
import { FIXTURE } from './fixtures/seed'

/** The planix SPA entry point. Safe with or without `index.php`. */
export const PLANIX_ROOT = `${BASE_URL}/index.php/apps/planix/`

/**
 * Open the project list and click the SEEDED fixture project, landing on its
 * kanban board.
 *
 * Deliberately not "the first project": the register ships demo projects, so
 * the first row is `Client Portal v2`, whose tasks carry no due dates. Every
 * assertion in these specs is about the spread `fixtures/seed.ts` creates (one
 * task due tomorrow, one overdue, one far-future, one label), so the specs must
 * open the project that actually holds it.
 *
 * @param page the Playwright page
 * @return the project UUID taken from the resulting URL
 */
export async function openFixtureProjectBoard(page: Page): Promise<string> {
	await page.goto(PLANIX_ROOT)

	// Use the app's own navigation entry rather than a hand-built URL.
	await page.locator('#app-navigation-vue a[title="Projects"]').click()

	const row = page.locator('.project-list-item', { hasText: FIXTURE.projectTitle })
	await expect(row).toHaveCount(1)
	await row.click()

	await expect(page).toHaveURL(/\/projects\/[^/?#]+$/)
	const match = page.url().match(/\/projects\/([^/?#]+)/)
	if (match === null) {
		throw new Error(`Expected a /projects/:id URL after clicking a project, got ${page.url()}`)
	}
	return match[1]
}

/**
 * Open the planix user-settings dialog from the app navigation.
 *
 * The old `getByRole('button', { name: /settings/i }).first()` matched
 * Nextcloud's own header button (`aria-label="Settings menu"`), which comes
 * first in the DOM — so the specs opened the user menu and then waited forever
 * for a planix toggle that was never going to render.
 *
 * @param page the Playwright page
 * @return void
 */
export async function openPlanixSettingsDialog(page: Page): Promise<void> {
	const nav = page.locator('#app-navigation-vue')
	const item = nav.locator('a[title="Settings"]')

	// NcAppNavigationSettings is a collapsible footer section: its toggle button
	// also answers to the name "Settings", and the entry itself is hidden while
	// the section is collapsed. Expand first when needed.
	if (!(await item.isVisible().catch(() => false))) {
		await nav.getByRole('button', { name: 'Settings', exact: true }).click()
	}
	await item.click()
}
