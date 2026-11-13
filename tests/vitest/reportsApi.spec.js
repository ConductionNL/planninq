/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pins the task report API: listing reads results or a bare list, saving
 * patches an existing report without its id and posts a new one, and
 * running asks one grouped aggregation per project and adds the buckets up.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const axios = { get: vi.fn(), post: vi.fn(), patch: vi.fn() }
vi.mock('@nextcloud/axios', () => ({ default: axios }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (url, params = {}) => `/index.php${url.replace(/\{(\w+)\}/g, (_m, k) => params[k])}`,
}))

const { fetchReport, fetchReports, runReport, saveReport } = await import('../../src/api/reports.js')

const REPORTS = '/index.php/apps/openregister/api/objects/planninq/taskReport'

beforeEach(() => {
	axios.get.mockReset()
	axios.post.mockReset()
	axios.patch.mockReset()
})

describe('fetchReports', () => {
	it('reads up to 500 reports from a results envelope', async () => {
		axios.get.mockResolvedValue({ data: { results: [{ id: 'r-1' }] } })
		expect(await fetchReports()).toEqual([{ id: 'r-1' }])
		expect(axios.get).toHaveBeenCalledWith(REPORTS, { params: { _limit: 500 } })
	})

	it('accepts a bare list and reads anything else as none', async () => {
		axios.get.mockResolvedValueOnce({ data: [{ id: 'r-2' }] })
		expect(await fetchReports()).toEqual([{ id: 'r-2' }])
		axios.get.mockResolvedValueOnce({ data: { total: 0 } })
		expect(await fetchReports()).toEqual([])
		axios.get.mockResolvedValueOnce({})
		expect(await fetchReports()).toEqual([])
	})
})

describe('fetchReport', () => {
	it('reads one report, or an empty object', async () => {
		axios.get.mockResolvedValueOnce({ data: { id: 'r-1', title: 'Velocity' } })
		expect(await fetchReport('r-1')).toEqual({ id: 'r-1', title: 'Velocity' })
		expect(axios.get).toHaveBeenCalledWith(`${REPORTS}/r-1`)
		axios.get.mockResolvedValueOnce({})
		expect(await fetchReport('r-2')).toEqual({})
	})
})

describe('saveReport', () => {
	it('patches an existing report without sending its id', async () => {
		axios.patch.mockResolvedValue({ data: { id: 'r-1', title: 'New' } })
		expect(await saveReport({ id: 'r-1', title: 'New', metric: 'count' })).toEqual({ id: 'r-1', title: 'New' })
		expect(axios.patch).toHaveBeenCalledWith(`${REPORTS}/r-1`, { title: 'New', metric: 'count' })
		expect(axios.post).not.toHaveBeenCalled()
	})

	it('posts a new report', async () => {
		axios.post.mockResolvedValue({})
		expect(await saveReport({ title: 'Fresh' })).toEqual({})
		expect(axios.post).toHaveBeenCalledWith(REPORTS, { title: 'Fresh' })
		expect(axios.patch).not.toHaveBeenCalled()
	})
})

describe('runReport', () => {
	it('asks one grouped aggregation per project and adds the buckets up, largest first', async () => {
		axios.get.mockImplementation(async (url, { params }) => ({
			data: {
				groups: params['filter[project]'] === 'p-1'
					? [{ key: 'open', value: 3 }, { key: 'done', value: 1 }]
					: [{ key: 'done', value: 5 }],
			},
		}))

		const buckets = await runReport({ groupBy: 'status', metric: 'sum', sumField: 'storyPoints', filters: {} }, ['p-1', 'p-2'])

		expect(buckets).toEqual([{ key: 'done', value: 6 }, { key: 'open', value: 3 }])
		expect(axios.get).toHaveBeenCalledTimes(2)
		const [url, { params }] = axios.get.mock.calls[0]
		expect(url).toBe('/index.php/apps/openregister/api/objects/aggregations/planninq/task/grouped')
		expect(params).toEqual({ groupBy: 'status', metric: 'sum', field: 'storyPoints', 'filter[project]': 'p-1' })
	})

	it('counts an answer without groups as nothing, and no projects as no buckets', async () => {
		axios.get.mockResolvedValue({ data: {} })
		expect(await runReport({ groupBy: 'status' }, ['p-1'])).toEqual([])
		axios.get.mockClear()
		expect(await runReport({ groupBy: 'status' }, [])).toEqual([])
		expect(axios.get).not.toHaveBeenCalled()
	})
})
