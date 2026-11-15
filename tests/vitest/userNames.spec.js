/**
 * Display names on the Members tab (live pass P6, 2 Oct).
 *
 * Nextcloud's autocomplete never returns the caller, so the caller's own row
 * on the Members tab read "pq-owner" while everyone else had a name.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({ generateOcsUrl: (path) => '/ocs/v2.php' + path }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'pq-owner', displayName: 'Olga Owner' }) }))

// Nextcloud's autocomplete as it answers the caller: everyone but themself.
const PEOPLE = [{ id: 'pq-member', label: 'Max Member' }]
globalThis.fetch = vi.fn(async (url) => {
	const search = new URL(url, 'http://nc').searchParams.get('search')
	return { ok: true, json: async () => ({ ocs: { data: PEOPLE.filter((p) => p.id.includes(search)) } }) }
})

const { displayName, displayNames } = await import('../../src/utils/userNames.js')

afterEach(() => vi.clearAllMocks())

describe('displayName', () => {
	it('names the caller by their own display name, which autocomplete never returns', async () => {
		expect(await displayName('pq-owner')).toBe('Olga Owner')
		expect(await displayNames(['pq-owner', 'pq-member'])).toEqual({ 'pq-owner': 'Olga Owner', 'pq-member': 'Max Member' })
	})

	it('still falls back to the id for someone autocomplete does not know', async () => {
		expect(await displayName('pq-gone')).toBe('pq-gone')
	})
})
