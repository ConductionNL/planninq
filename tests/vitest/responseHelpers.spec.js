/**
 * Tests for the small response helpers: a tolerant JSON body and a list guard.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { describe, expect, it } from 'vitest'
import { asList } from '../../src/utils/asList.js'
import { responseBody } from '../../src/utils/responseBody.js'

describe('responseBody', () => {
	it('returns the parsed JSON body', async () => {
		const response = new Response(JSON.stringify({ error: 'Not a member' }), { status: 403 })

		expect(await responseBody(response)).toEqual({ error: 'Not a member' })
	})

	it('returns an empty object for a body that is not JSON', async () => {
		expect(await responseBody(new Response('<html>Bad gateway</html>', { status: 502 }))).toEqual({})
		expect(await responseBody(new Response('', { status: 200 }))).toEqual({})
	})
})

describe('asList', () => {
	it('passes an array through as the same array', () => {
		const list = [1, 2]

		expect(asList(list)).toBe(list)
	})

	it('turns anything else into an empty list', () => {
		expect(asList(null)).toEqual([])
		expect(asList(undefined)).toEqual([])
		expect(asList({ results: [1] })).toEqual([])
		expect(asList('a,b')).toEqual([])
	})
})
