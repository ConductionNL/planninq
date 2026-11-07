/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A browser page signed in as a user the spec has just created.
 *
 * global-setup.ts releases only the admin account from the overlays a first
 * visit raises: Nextcloud's firstrunwizard and Conduction's support note, setup
 * wizard and walkthrough. A user a spec creates meets all of them on its first
 * page, every click lands on the overlay mask, and the spec times out on a
 * locator that is visible and cannot be clicked (live pass P2, 2 Oct: the three
 * membership specs never passed on an instance with firstrunwizard enabled).
 */

import type { Browser } from '@playwright/test'

import { request } from '@playwright/test'
import { BASE_URL } from './base-url.ts'

/**
 * Turn Nextcloud's first-run wizard off for `username`, as global-setup does
 * for the admin. Best-effort: the app is optional and absent on some instances.
 *
 * @param username The user id.
 * @param password The user's password.
 * @return void
 */
async function dismissFirstRunWizard(username: string, password: string): Promise<void> {
	const ctx = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password, send: 'always' },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	try {
		await ctx.delete('/index.php/apps/firstrunwizard/wizard', { failOnStatusCode: false })
	} finally {
		await ctx.dispose()
	}
}

/**
 * Mark Conduction's first-visit overlays as seen in this browser context.
 * Runs before any page script, on every page of the context.
 *
 * @return void
 */
function seedOverlayFlags(): void {
	try {
		window.localStorage.setItem('cn-support-dialog-shown:planninq', '1')
		window.localStorage.setItem('cn-walkthrough-seen:planninq', '999.0.0')
		for (let v = 0; v <= 50; v++) {
			window.localStorage.setItem(`cn-setup-wizard-dismissed:planninq:${v}`, '1')
			window.localStorage.setItem(`cn-walkthrough-seen:planninq:${v}`, '999.0.0')
		}
	} catch {
		// A browser with site data blocked has nothing to seed; the overlays show.
	}
}

/**
 * A browser page signed in as `username`, in a context of its own, with no
 * first-visit overlay in the way.
 *
 * @param browser The browser.
 * @param username The user id.
 * @param password The user's password.
 */
export async function signedInPage(browser: Browser, username: string, password: string) {
	await dismissFirstRunWizard(username, password)
	const context = await browser.newContext({ baseURL: BASE_URL, storageState: { cookies: [], origins: [] } })
	await context.addInitScript(seedOverlayFlags)
	const page = await context.newPage()
	await page.goto('/index.php/login')
	await page.locator('input[name="user"]').fill(username)
	await page.locator('input[name="password"]').fill(password)
	await page.locator('button[type="submit"]').first().click()
	await page.waitForSelector('#header, header.header', { timeout: 20_000 })
	return { context, page }
}
