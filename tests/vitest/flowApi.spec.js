/**
 * Tests for the flow report reads: the URL per scope and the optional window.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = { answers: [], calls: [] }

vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => '/index.php' + p }))
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: async (url, config) => {
			server.calls.push([url, config])
			const answer = server.answers.shift()
			if (answer instanceof Error) {
				throw answer
			}
			return { data: answer }
		},
	},
}))

const { fetchPortfolioFlow, fetchProjectFlow } = await import('../../src/api/flow.js')

describe('flow api', () => {
	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('reads a project flow without a window', async () => {
		server.answers.push({ days: [{ date: '2026-10-01' }] })

		const result = await fetchProjectFlow('p-1')

		expect(result).toEqual({ days: [{ date: '2026-10-01' }] })
		expect(server.calls).toEqual([['/index.php/apps/planninq/api/projects/p-1/flow', { params: {} }]])
	})

	it('passes only the window bounds that are set', async () => {
		server.answers.push({}, {}, {})

		await fetchProjectFlow('p-1', '2026-10-01', '2026-10-31')
		await fetchProjectFlow('p-1', '2026-10-01')
		await fetchPortfolioFlow('pf-9', null, '2026-10-31')

		expect(server.calls.map(([, config]) => config.params)).toEqual([
			{ from: '2026-10-01', to: '2026-10-31' },
			{ from: '2026-10-01' },
			{ to: '2026-10-31' },
		])
		expect(server.calls[2][0]).toBe('/index.php/apps/planninq/api/portfolios/pf-9/flow')
	})

	it('answers an empty body with an empty object', async () => {
		server.answers.push(null, '')

		expect(await fetchProjectFlow('p-1')).toEqual({})
		expect(await fetchPortfolioFlow('pf-9')).toEqual({})
	})

	it('lets a failed read reject', async () => {
		server.answers.push(new Error('Request failed with status code 403'))

		await expect(fetchPortfolioFlow('pf-9')).rejects.toThrow('403')
	})
})
