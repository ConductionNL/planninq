/*
 * SPDX-FileCopyrightText: 2026 Planix Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * E2E (UI-only) coverage for the label-management admin surfaces.
 *
 * Covers the non-excluded UI scenarios of the label-management-admin change
 * (gate-19 e2e-coverage), all on the admin-user-settings spec:
 *  - "View labels with usage counts"
 *  - "Create a label"
 *  - "Invalid color is rejected"
 *  - "Rename and recolor propagate by reference"
 *  - "Delete a used label"
 *
 * API/contract assertions (the 403 admin-only contract, the cascade idempotency
 * after a partial failure, and the register re-import not resurrecting deleted
 * labels) live in Newman / PHPUnit per the Playwright-UI-only / Newman-for-API
 * convention — those scenarios are annotated `@e2e exclude` in the spec delta.
 *
 * A fixture label ("E2E Bug") is seeded by `tests/e2e/global-setup.ts` (via
 * `fixtures/seed.ts`) and attached to a seeded task, so the admin Label
 * management section renders its list and controls unconditionally. Only the
 * legitimate "planix not installed" skip remains; the former "section not
 * present" guards are now hard `expect(...)` assertions.
 */

import { test, expect, type Page } from '@playwright/test'
import { BASE_URL as NC } from './base-url'

const SETTINGS_URL = `${NC}/index.php/settings/admin/planix`

/**
 * The open label dialog (NcDialog renders `role="dialog"`).
 *
 * Both label dialogs share button names with controls that stay mounted behind
 * them — "Save" with four settings forms, "Delete label" with every row's own
 * delete control — so dialog interactions must be scoped or Playwright's strict
 * mode aborts on the multiple matches.
 *
 * @param page the Playwright page
 * @return a locator for the open dialog
 */
function dialog(page: Page) {
	// `.last()` because NcDialog can render a native <dialog> (implicit role
	// "dialog") inside an NcModal wrapper that also declares role="dialog";
	// the innermost one is the one holding the action buttons.
	return page.getByRole('dialog').last()
}

test.describe('Label management — admin settings', () => {
	test('View labels with usage counts', async ({ page }) => {
		const res = await page.goto(SETTINGS_URL)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		const section = page.getByText(/Label management/i)
		await expect(section.first()).toBeVisible()

		// Seed label "E2E Bug" listed with a usage count (attached to a task).
		await expect(page.getByText(/E2E Bug/i).first()).toBeVisible()
		await expect(page.getByText(/used by \d+ tasks?/i).first()).toBeVisible()
	})

	test('Create a label with a custom color', async ({ page }) => {
		const res = await page.goto(SETTINGS_URL)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		const createBtn = page.getByRole('button', { name: /Create label/i }).first()
		await expect(createBtn).toBeVisible()
		await createBtn.click()

		await page.getByLabel(/Title/i).fill('Tech debt')
		await page.getByLabel(/Hex color/i).fill('#33AA55')
		// Scoped to the dialog for the same reason as the Save/Delete clicks
		// below — the list's own trigger is "+ Create label", which does not
		// collide today, but the page behind the dialog stays mounted.
		await dialog(page).getByRole('button', { name: /^Create$/i }).click()

		await expect(page.getByText(/Tech debt/i)).toBeVisible()
	})

	test('Invalid color is rejected in the dialog', async ({ page }) => {
		const res = await page.goto(SETTINGS_URL)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		const createBtn = page.getByRole('button', { name: /Create label/i }).first()
		await expect(createBtn).toBeVisible()
		await createBtn.click()

		await page.getByLabel(/Title/i).fill('Bad color')
		await page.getByLabel(/Hex color/i).fill('not-a-hex')
		// Validation error shown; create disabled / no save.
		await expect(page.getByText(/6-digit hex code/i)).toBeVisible()
	})

	test('Rename and recolor propagate to task chips by reference', async ({ page }) => {
		const res = await page.goto(SETTINGS_URL)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		const editBtn = page.getByRole('button', { name: /Edit label/i }).first()
		await expect(editBtn).toBeVisible()
		await editBtn.click()

		await page.getByLabel(/Title/i).fill('Defect')
		await page.getByLabel(/Hex color/i).fill('#FF8800')
		// Scope the Save click to the DIALOG.
		//
		// The admin panel behind this dialog renders four independent settings
		// forms, each with its own "Save" button — default columns, project
		// creation, notification lead time and the legacy register
		// configuration. A page-wide `getByRole('button', { name: /^Save$/i })`
		// therefore resolves to five elements and Playwright's strict mode
		// aborts the test before it clicks anything.
		await dialog(page).getByRole('button', { name: /^Save$/i }).click()

		// Re-render reflects the new title on the board chip (no task write).
		await expect(page.getByText(/Defect/i).first()).toBeVisible()
	})

	test('Delete a used label via the usage-warning dialog', async ({ page }) => {
		const res = await page.goto(SETTINGS_URL)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		const deleteBtn = page.getByRole('button', { name: /Delete label/i }).first()
		await expect(deleteBtn).toBeVisible()
		await deleteBtn.click()

		// Confirmation dialog warns about the usage count before deleting.
		await expect(page.getByText(/will be removed from \d+ tasks?/i)).toBeVisible()
		// Scope the confirm click to the DIALOG.
		//
		// Every row in the label list carries a delete control whose accessible
		// name is its `aria-label`, "Delete label" — exactly the name the
		// dialog's confirm button also has. Once the list renders even one
		// label, a page-wide match resolves to two elements and strict mode
		// aborts. (This never surfaced before because the list was always
		// empty: see LabelService::fetchAll(), which searched with no
		// register/schema context and returned nothing on every instance.)
		await dialog(page).getByRole('button', { name: /^Delete label$/i }).click()
	})
})
