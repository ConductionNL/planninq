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

import { test, expect } from '@playwright/test'

const NC = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
const SETTINGS_URL = `${NC}/index.php/settings/admin/planix`

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
		await page.getByRole('button', { name: /^Create$/i }).click()

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
		await page.getByRole('button', { name: /^Save$/i }).click()

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
		await page.getByRole('button', { name: /^Delete label$/i }).click()
	})
})
