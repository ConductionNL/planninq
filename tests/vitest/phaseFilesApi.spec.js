/**
 * Tests for the phase file list and upload against OpenRegister's file endpoints.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = { answers: [], calls: [] }

function answer(method, url, body) {
	server.calls.push([method, url, body])
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
		post: async (url, body) => answer('post', url, body),
	},
}))

const { listPhaseFiles, uploadPhaseFile } = await import('../../src/api/phaseFiles.js')

describe('phase files api', () => {
	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('lists a plain array of files as id and title, falling back to the name and then the id', async () => {
		server.answers.push([{ id: 1, title: 'Plan.pdf', name: 'x' }, { id: 2, name: 'Notes.txt' }, { id: 3 }])

		const files = await listPhaseFiles('ph-1')

		expect(files).toEqual([
			{ id: 1, title: 'Plan.pdf' },
			{ id: 2, title: 'Notes.txt' },
			{ id: 3, title: '3' },
		])
		expect(server.calls).toEqual([['get', '/apps/openregister/api/objects/planninq/projectPhase/ph-1/files', undefined]])
	})

	it('reads a paged answer and treats anything else as no files', async () => {
		server.answers.push({ results: [{ id: 7, title: 'Budget.xlsx' }] }, { results: 'none' }, null)

		expect(await listPhaseFiles('ph-1')).toEqual([{ id: 7, title: 'Budget.xlsx' }])
		expect(await listPhaseFiles('ph-1')).toEqual([])
		expect(await listPhaseFiles('ph-1')).toEqual([])
	})

	it('uploads the file as files[] and returns the refreshed list', async () => {
		const file = new Blob(['hello'], { type: 'text/plain' })
		server.answers.push({}, [{ id: 9, title: 'hello.txt' }])

		const files = await uploadPhaseFile('ph-2', file)

		expect(files).toEqual([{ id: 9, title: 'hello.txt' }])
		expect(server.calls[0][0]).toBe('post')
		expect(server.calls[0][1]).toBe('/apps/openregister/api/objects/planninq/projectPhase/ph-2/filesMultipart')
		expect(server.calls[0][2]).toBeInstanceOf(FormData)
		expect(server.calls[0][2].getAll('files[]')).toHaveLength(1)
		expect(server.calls[1]).toEqual(['get', '/apps/openregister/api/objects/planninq/projectPhase/ph-2/files', undefined])
	})

	it('does not list again when the upload fails', async () => {
		server.answers.push(new Error('Request failed with status code 413'))

		await expect(uploadPhaseFile('ph-2', new Blob(['x']))).rejects.toThrow('413')
		expect(server.calls).toHaveLength(1)
	})
})
