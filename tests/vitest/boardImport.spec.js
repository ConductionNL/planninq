/**
 * Tests for the board import api (projects-import-from-other-tools task 1.2).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = { answers: [], calls: [] }

function answer(method, url, body) {
	server.calls.push([method, url, body])
	const next = server.answers.shift()
	if (next instanceof Error) {
		throw next
	}
	return next
}

vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: async (url) => answer('get', url),
		post: async (url, body) => answer('post', url, body),
	},
}))

const { acceptFor, fetchBoardImports, importBoard } = await import('../../src/api/boardImport.js')

describe('board import api', () => {
	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('lists the imports the server offers', async () => {
		const tools = [{ id: 'trello', label: 'Trello board (JSON export)', format: 'json' }]
		server.answers.push({ data: { tools } })
		expect(await fetchBoardImports()).toEqual(tools)
		expect(server.calls[0].slice(0, 2)).toEqual(['get', '/apps/planninq/api/import/boards'])

		server.answers.push({ data: {} })
		expect(await fetchBoardImports()).toEqual([])
	})

	it('posts the file as form data to the chosen import', async () => {
		const file = new File(['{"name":"Vergunningen"}'], 'Vergunningen.json', { type: 'application/json' })
		server.answers.push({ status: 201, data: { project: 'p-1', columns: 3, tasks: 4, losses: [] } })

		expect(await importBoard('trello', file)).toEqual({ status: 201, data: { project: 'p-1', columns: 3, tasks: 4, losses: [] } })
		const [method, url, body] = server.calls[0]
		expect([method, url]).toEqual(['post', '/apps/planninq/api/import/boards/trello'])
		expect(body).toBeInstanceOf(FormData)
		expect(body.get('file').name).toBe('Vergunningen.json')
	})

	it('returns a refusal instead of throwing, and rethrows a network failure', async () => {
		const refusal = Object.assign(new Error('422'), { response: { status: 422, data: { error: 'The file is not valid JSON.' } } })
		server.answers.push(refusal)
		expect(await importBoard('trello', new File(['x'], 'x.json'))).toEqual({ status: 422, data: { error: 'The file is not valid JSON.' } })

		server.answers.push(new Error('offline'))
		await expect(importBoard('jira-csv', new File(['x'], 'x.csv'))).rejects.toThrow('offline')
	})

	it('limits the file picker to the import format', () => {
		expect(acceptFor('json')).toBe('.json,application/json')
		expect(acceptFor('csv')).toBe('.csv,text/csv')
		expect(acceptFor(undefined)).toBe('')
	})
})
