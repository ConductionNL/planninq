/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/app-shell-and-data-store/spec.md
 */
import { describe, expect, it } from 'vitest'
import { shellPermissions } from '../../src/utils/shellPermissions.js'

describe('shellPermissions', () => {
	it('gives an administrator the admin flag', () => {
		expect(shellPermissions({ uid: 'admin', isAdmin: true })).toEqual(['user', 'admin'])
	})

	it('gives a regular user a non-empty list without it, so admin entries are filtered', () => {
		expect(shellPermissions({ uid: 'anna', isAdmin: false })).toEqual(['user'])
	})

	it('treats an unknown user as a regular one', () => {
		expect(shellPermissions(null)).toEqual(['user'])
		expect(shellPermissions(undefined)).toEqual(['user'])
	})
})
