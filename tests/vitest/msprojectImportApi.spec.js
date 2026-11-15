/**
 * The MS Project import API client: the preview and the import post the plan
 * file as multipart form data to their endpoints, answer the status and body
 * of a server answer (also a refusal), and rethrow a failure that has no
 * server answer. Only axios and the router are faked.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = vi.hoisted(() => ({ answers: [], calls: [] }))

vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/axios', () => ({
	default: {
		post: async (url, body) => {
			server.calls.push([url, body])
			const next = server.answers.shift()
			if (next instanceof Error) {
				throw next
			}
			return next
		},
	},
}))

const { importPlan, previewPlan } = await import('../../src/api/msprojectImport.js')

/**
 * An axios error carrying a server answer.
 *
 * @param {number} status The HTTP status.
 * @param {*} data The body.
 * @return {Error}
 */
function refusal(status, data) {
	return Object.assign(new Error(`Request failed with status code ${status}`), { response: { status, data } })
}

describe('MS Project import api', () => {
	const file = new Blob(['<Project/>'], { type: 'application/xml' })

	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('posts the plan file to the preview endpoint and answers status and body', async () => {
		server.answers.push({ status: 200, data: { tasks: 3 } })

		expect(await previewPlan('p-1', file)).toEqual({ status: 200, data: { tasks: 3 } })
		const [url, body] = server.calls[0]
		expect(url).toBe('/index.php/apps/planninq/api/projects/p-1/import/msproject/preview')
		expect(body).toBeInstanceOf(FormData)
		expect([...body.keys()]).toEqual(['file'])
		expect(await body.get('file').text()).toBe('<Project/>')
	})

	it('posts the plan file to the import endpoint', async () => {
		server.answers.push({ status: 201, data: { created: 3 } })

		expect(await importPlan('p-2', file)).toEqual({ status: 201, data: { created: 3 } })
		expect(server.calls[0][0]).toBe('/index.php/apps/planninq/api/projects/p-2/import/msproject')
	})

	it('answers an empty body when the server sends none', async () => {
		server.answers.push({ status: 204, data: '' }, refusal(500, null))

		expect(await importPlan('p-1', file)).toEqual({ status: 204, data: {} })
		expect(await importPlan('p-1', file)).toEqual({ status: 500, data: {} })
	})

	it('answers a refusal as its status and body instead of throwing', async () => {
		server.answers.push(refusal(422, { error: 'Not an MS Project file' }))

		expect(await previewPlan('p-1', file)).toEqual({ status: 422, data: { error: 'Not an MS Project file' } })
	})

	it('rethrows a failure without a server answer', async () => {
		const offline = new Error('Network Error')
		server.answers.push(offline)

		await expect(importPlan('p-1', file)).rejects.toBe(offline)
	})
})
