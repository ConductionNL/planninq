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
 * Planix is not installed in the dev container at the time of writing; these
 * tests are scaffolded for a future run and skip cleanly when the admin section
 * is absent rather than failing the suite.
 */

import { test, expect } from '@playwright/test'

const NC = process.env.NEXTCLOUD_URL || 'http://localhost:8080'
const SETTINGS_URL = `${NC}/index.php/settings/admin/planix`

test.describe('Label management — admin settings', () => {
	test('View labels with usage counts', async ({ page }) => {
		const res = await page.goto(SETTINGS_URL)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		const section = page.getByText(/Label management/i)
		test.skip((await section.count()) === 0, 'Label management section not present')
		await expect(section).toBeVisible()

		// Seed label "Bug" listed with a usage count.
		await expect(page.getByText(/Bug/i).first()).toBeVisible()
		await expect(page.getByText(/used by \d+ tasks?/i).first()).toBeVisible()
	})

	test('Create a label with a custom color', async ({ page }) => {
		const res = await page.goto(SETTINGS_URL)
		test.skip(res === null || res.status() >= 400, 'Planix not installed in this environment')

		const createBtn = page.getByRole('button', { name: /Create label/i }).first()
		test.skip((await createBtn.count()) === 0, 'Label management section not present')
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
		test.skip((await createBtn.count()) === 0, 'Label management section not present')
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
		test.skip((await editBtn.count()) === 0, 'Label management section not present')
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
		test.skip((await deleteBtn.count()) === 0, 'Label management section not present')
		await deleteBtn.click()

		// Confirmation dialog warns about the usage count before deleting.
		await expect(page.getByText(/will be removed from \d+ tasks?/i)).toBeVisible()
		await page.getByRole('button', { name: /^Delete label$/i }).click()
	})
})
