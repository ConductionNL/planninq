/**
 * Tests for starting a project export and the message of a failed start
 * (project-data-export, task 4.2).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-4.2
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = { answers: [], calls: [] }

vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('@nextcloud/axios', () => ({
	default: {
		post: async (url) => {
			server.calls.push(['post', url])
			const next = server.answers.shift()
			if (next instanceof Error) {
				throw next
			}
			return { data: next }
		},
	},
}))

const { exportErrorMessage, startProjectExport } = await import('../../src/api/projectExport.js')

describe('project export api', () => {
	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('posts to the project export endpoint and answers the server\'s body', async () => {
		server.answers.push({ status: 'queued' })

		expect(await startProjectExport('p-1')).toEqual({ status: 'queued' })
		expect(server.calls).toEqual([['post', '/apps/planninq/api/projects/p-1/export']])
	})

	it('passes a refusal on to the caller', async () => {
		const refusal = Object.assign(new Error('403'), { response: { status: 403, data: { error: 'Only an owner or manager of the project can export it.' } } })
		server.answers.push(refusal)

		await expect(startProjectExport('p-1')).rejects.toBe(refusal)
	})

	it('shows the server\'s message, else the fallback', () => {
		expect(exportErrorMessage({ response: { data: { error: 'Only an owner or manager of the project can export it.' } } }, 'fallback'))
			.toBe('Only an owner or manager of the project can export it.')
		expect(exportErrorMessage({ response: { data: { message: 'Problem detail' } } }, 'fallback')).toBe('Problem detail')
		expect(exportErrorMessage({ response: { data: { error: '' } } }, 'fallback')).toBe('fallback')
		expect(exportErrorMessage(new Error('network'), 'fallback')).toBe('fallback')
	})
})
