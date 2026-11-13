/**
 * Tests for the case handover status read and the handover itself.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = { answers: [], calls: [] }

function answer(method, url) {
	server.calls.push([method, url])
	const next = server.answers.shift()
	if (next instanceof Error) {
		throw next
	}
	return { data: next }
}

vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: async (url) => answer('get', url),
		post: async (url) => answer('post', url),
	},
}))

const { fetchHandoverStatus, handOverToCase } = await import('../../src/api/caseHandover.js')

describe('case handover api', () => {
	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('reads availability and earlier handovers', async () => {
		const handovers = [{ by: 'rik', case: 'case-1', at: '2026-09-01T10:00:00+00:00' }]
		server.answers.push({ available: true, handovers })

		expect(await fetchHandoverStatus('p-1')).toEqual({ available: true, handovers })
		expect(server.calls).toEqual([['get', '/apps/planninq/api/projects/p-1/case-handover']])
	})

	it('only treats a literal true as available and defaults the handovers', async () => {
		server.answers.push({ available: 'yes' }, null)

		expect(await fetchHandoverStatus('p-1')).toEqual({ available: false, handovers: [] })
		expect(await fetchHandoverStatus('p-1')).toEqual({ available: false, handovers: [] })
	})

	it('posts the handover and returns the server answer unchanged', async () => {
		server.answers.push({ case: 'case-2', files: 3, failures: [] })

		expect(await handOverToCase('p-7')).toEqual({ case: 'case-2', files: 3, failures: [] })
		expect(server.calls).toEqual([['post', '/apps/planninq/api/projects/p-7/case-handover']])
	})

	it('lets a refused handover reject', async () => {
		server.answers.push(new Error('Request failed with status code 409'))

		await expect(handOverToCase('p-7')).rejects.toThrow('409')
	})
})
