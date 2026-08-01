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
 * The planix app and its default due-date-reminder settings are present once
 * the app is installed (fixtures seeded by `tests/e2e/global-setup.ts`). Only
 * the legitimate "planix not installed / admin not reachable" skips remain;
 * the former "settings entry / field not present" guards are now hard
 * `expect(...)` assertions.
 */

import { test, expect } from '@playwright/test'
import { BASE_URL as NC } from './base-url'
import { PLANIX_ROOT, openPlanixSettingsDialog } from './nav'

test.describe('Due-date reminder — user settings dialog', () => {
	test('Toggle due-date reminders off and back on persists', async ({ page }) => {
		const res = await page.goto(PLANIX_ROOT)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		// Open the planix user-settings dialog.
		//
		// The previous `getByRole('button', { name: /settings/i }).first()`
		// matched NEXTCLOUD's own header control (`aria-label="Settings menu"`),
		// which precedes the app navigation in the DOM — so this opened the user
		// menu and then waited out the timeout for a planix toggle that was
		// never going to appear. See tests/e2e/nav.ts.
		await openPlanixSettingsDialog(page)

		const toggle = page.getByText(/Notify me 1 day before a task's due date/i)
		await expect(toggle).toBeVisible()

		const checkbox = page.locator('input[type="checkbox"]').first()
		await expect(checkbox).toBeChecked()

		// Toggle off, reload, assert it persisted off.
		await toggle.click()
		await page.reload()
		await openPlanixSettingsDialog(page)
		await expect(page.locator('input[type="checkbox"]').first()).not.toBeChecked()

		// Toggle back on.
		await page.getByText(/Notify me 1 day before a task's due date/i).click()
		await page.reload()
		await openPlanixSettingsDialog(page)
		await expect(page.locator('input[type="checkbox"]').first()).toBeChecked()
	})
})

test.describe('Due-date reminder — admin lead time', () => {
	test('Lead-time field default 24, save 48 persists, 0 shows a validation error', async ({ page }) => {
		const res = await page.goto(`${NC}/index.php/settings/admin/planix`)
		test.skip(res === null || res.status() >= 400, 'Planix admin settings not reachable')

		const field = page.locator('#due-reminder-lead-hours')
		await expect(field).toHaveCount(1)

		// Default is 24 on a fresh install.
		await expect(field).toHaveValue('24')

		// Scope the Save click to THIS form.
		//
		// The admin panel renders four independent settings forms, each with its
		// own "Save". `getByRole('button', { name: /save/i }).last()` therefore
		// submitted the LAST form on the page (the legacy register-id
		// configuration), never the lead-time form — so the success message this
		// test waits for could not appear, and the "0 is rejected" case never
		// exercised the validator either.
		const leadTimeForm = page.locator('form:has(#due-reminder-lead-hours)')
		const save = leadTimeForm.getByRole('button', { name: /save/i })

		// Save 48 → persists.
		await field.fill('48')
		await save.click()
		await expect(page.getByText(/Reminder lead time saved successfully/i)).toBeVisible()

		// 0 is rejected with an inline validation error and is not persisted.
		await field.fill('0')
		await save.click()
		await expect(page.getByText(/Lead time must be between 1 and 336 hours/i)).toBeVisible()
	})
})
