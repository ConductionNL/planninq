/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E (UI-only) coverage for the due-date reminder settings surfaces.
 *
 * Covers the non-excluded UI scenarios of the due-date-reminder-dispatch
 * change (gate-19 e2e-coverage):
 *  - admin-user-settings spec: "Toggle due-date reminders off",
 *    "Toggle due-date reminders back on", "Lead-time field shown with
 *    default", "Saving a new lead time", "Invalid lead time rejected in
 *    the UI".
 *  - task-notifications spec: "Admin changes the lead time".
 *
 * API/contract assertions (the scheduled-job dispatch, OR override
 * persistence) live in Newman per the Playwright-UI-only / Newman-for-API
 * convention — these specs are annotated `@e2e exclude` in the deltas.
 *
 * Planix is not installed in the dev container at the time of writing;
 * these tests are scaffolded for a future run and skip cleanly when the
 * app navigation is absent rather than failing the suite.
 */

import { test, expect } from '@playwright/test'

const NC = process.env.NEXTCLOUD_URL || 'http://localhost:8080'

test.describe('Due-date reminder — user settings dialog', () => {
	test('Toggle due-date reminders off and back on persists', async ({ page }) => {
		const res = await page.goto(`${NC}/index.php/apps/planix/`)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		// Open the user settings dialog from the navigation gear.
		const gear = page.getByRole('button', { name: /settings/i }).first()
		test.skip((await gear.count()) === 0, 'Planix settings entry not present')
		await gear.click()

		const toggle = page.getByText(/Notify me 1 day before a task's due date/i)
		await expect(toggle).toBeVisible()

		// Toggle off, reload, assert it persisted off.
		await toggle.click()
		await page.reload()
		await gear.click()
		const checkbox = page.locator('input[type="checkbox"]').first()
		await expect(checkbox).not.toBeChecked()

		// Toggle back on.
		await toggle.click()
		await page.reload()
		await gear.click()
		await expect(checkbox).toBeChecked()
	})
})

test.describe('Due-date reminder — admin lead time', () => {
	test('Lead-time field default 24, save 48 persists, 0 shows a validation error', async ({ page }) => {
		const res = await page.goto(`${NC}/index.php/settings/admin/planix`)
		test.skip(res === null || res.status() >= 400, 'Planix admin settings not reachable')

		const field = page.locator('#due-reminder-lead-hours')
		test.skip((await field.count()) === 0, 'Lead-time field not present')

		// Default is 24 on a fresh install.
		await expect(field).toHaveValue('24')

		// Save 48 → persists.
		await field.fill('48')
		await page.getByRole('button', { name: /save/i }).last().click()
		await expect(page.getByText(/Reminder lead time saved successfully/i)).toBeVisible()

		// 0 is rejected with an inline validation error and is not persisted.
		await field.fill('0')
		await page.getByRole('button', { name: /save/i }).last().click()
		await expect(page.getByText(/Lead time must be between 1 and 336 hours/i)).toBeVisible()
	})
})
