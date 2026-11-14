/**
 * The projects store's collection reads, deletes and plain writes: each reads
 * the right schema with the right filter and turns any failure into an empty
 * answer, each delete hits the object's own URL and reports whether it is
 * gone, and the status report, code link and portfolio money calls answer as
 * the views expect. Only the object store, fetch and the Nextcloud helpers
 * are faked.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.2
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const hoursApp = { on: true }
vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ 'X-Test': '1' }), isAppInstalled: (app) => app === 'humaniq' && hoursApp.on }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const fetchCollection = vi.fn()
vi.mock('../../src/store/objectStore.js', () => ({
	useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {}, fetchCollection }),
}))

const { useProjectsStore } = await import('../../src/store/projects.js')

/**
 * A fetch answer.
 *
 * @param {boolean} ok Whether the status is 2xx
 * @param {*} body The JSON body
 * @return {object}
 */
function answer(ok, body = {}) {
	return { ok, json: async () => body }
}

describe('projects store reads, deletes and plain writes', () => {
	let store
	let errors

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		fetchCollection.mockReset()
		globalThis.fetch = vi.fn()
		errors = vi.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.fetch
	})

	const readers = [
		['fetchLogEntries', 'projectLogEntry', ['p1'], { project: 'p1' }],
		['fetchRisks', 'risk', ['p1'], { project: 'p1' }],
		['fetchFinanceLines', 'financeLine', ['p1'], { project: 'p1' }],
		['fetchProjectFields', 'projectField', [], {}],
		['fetchStatusReports', 'projectStatusReport', ['p1'], { project: 'p1' }],
		['fetchPortfolios', 'projectPortfolio', [], {}],
		['fetchBoardFilters', 'boardFilter', ['p1'], { project: 'p1' }],
		['fetchForgeLinks', 'forgeLink', ['t1'], { task: 't1' }],
		['fetchBoardViews', 'boardView', [], {}],
	]

	it.each(readers)('%s reads every %s row with its filter', async (action, schema, args, filters) => {
		fetchCollection.mockResolvedValueOnce([{ id: 'a' }, { id: 'b' }])

		const rows = await store[action](...args)

		expect(rows).toEqual([{ id: 'a' }, { id: 'b' }])
		expect(fetchCollection).toHaveBeenCalledWith(schema, { ...filters, _limit: expect.any(Number), _page: 1 })
	})

	it.each(readers)('%s answers an empty list when the read fails', async (action, schema, args) => {
		fetchCollection.mockRejectedValueOnce(new Error('offline'))

		expect(await store[action](...args)).toEqual([])
		expect(errors).toHaveBeenCalledWith(`${action} error:`, expect.any(Error))
	})

	it('reads the columns in lane order, an unordered column first', async () => {
		fetchCollection.mockResolvedValueOnce([{ id: 'c3', order: 3 }, { id: 'cx' }, { id: 'c1', order: '1' }])

		const columns = await store.fetchColumns('p1')

		expect(columns.map((c) => c.id)).toEqual(['cx', 'c1', 'c3'])
		expect(fetchCollection).toHaveBeenCalledWith('column', expect.objectContaining({ project: 'p1' }))
	})

	it('answers no columns when the read fails', async () => {
		fetchCollection.mockRejectedValueOnce(new Error('offline'))

		expect(await store.fetchColumns('p1')).toEqual([])
	})

	const deletes = [
		['deleteColumn', 'column'],
		['deleteFinanceLine', 'financeLine'],
		['deleteBoardFilter', 'boardFilter'],
		['deleteForgeLink', 'forgeLink'],
		['deleteBoardView', 'boardView'],
	]

	it.each(deletes)('%s deletes the %s by its own URL and reports whether it is gone', async (action, schema) => {
		fetch.mockResolvedValueOnce(answer(true)).mockResolvedValueOnce(answer(false))

		expect(await store[action]('id-1')).toBe(true)
		expect(await store[action]('id-1')).toBe(false)
		expect(fetch).toHaveBeenCalledWith(`/apps/openregister/api/objects/planninq/${schema}/id-1`, { method: 'DELETE', headers: { 'X-Test': '1' } })
	})

	it.each(deletes)('%s reports false when the request fails', async (action) => {
		fetch.mockRejectedValueOnce(new Error('offline'))

		expect(await store[action]('id-1')).toBe(false)
		expect(errors).toHaveBeenCalledWith(`${action} error:`, expect.any(Error))
	})

	it('posts a status report and answers the saved one, or null when refused or failed', async () => {
		const report = { project: 'p1', scope: 'green' }
		fetch.mockResolvedValueOnce(answer(true, { id: 'r1', ...report }))
			.mockResolvedValueOnce(answer(false, { message: 'not the owner' }))
			.mockRejectedValueOnce(new Error('offline'))

		expect(await store.saveStatusReport(report)).toEqual({ id: 'r1', project: 'p1', scope: 'green' })
		expect(fetch).toHaveBeenCalledWith('/apps/openregister/api/objects/planninq/projectStatusReport', {
			method: 'POST',
			headers: { 'X-Test': '1' },
			body: JSON.stringify(report),
		})
		expect(await store.saveStatusReport(report)).toBeNull()
		expect(await store.saveStatusReport(report)).toBeNull()
	})

	it('saves a code link and tells a duplicate apart from any other refusal', async () => {
		const link = { task: 't1', url: 'https://forge.example/x/1' }
		fetch.mockResolvedValueOnce(answer(true))
			.mockResolvedValueOnce(answer(false, { code: 'planninq-forge-link-duplicate' }))
			.mockResolvedValueOnce(answer(false, { code: 'planninq-forge-link-no-task' }))
			.mockResolvedValueOnce({ ok: false, json: async () => { throw new Error('not json') } })
			.mockRejectedValueOnce(new Error('offline'))

		expect(await store.saveForgeLink(link)).toEqual({ ok: true, duplicate: false })
		expect(fetch.mock.calls[0]).toEqual(['/apps/openregister/api/objects/planninq/forgeLink', {
			method: 'POST',
			headers: { 'X-Test': '1' },
			body: JSON.stringify(link),
		}])
		expect(await store.saveForgeLink(link)).toEqual({ ok: false, duplicate: true })
		expect(await store.saveForgeLink(link)).toEqual({ ok: false, duplicate: false })
		expect(await store.saveForgeLink(link)).toEqual({ ok: false, duplicate: false })
		expect(await store.saveForgeLink(link)).toEqual({ ok: false, duplicate: false })
	})

	it('reads a portfolio\'s lines and each visible project\'s time entries', async () => {
		fetchCollection.mockImplementation(async (schema, filters) => {
			if (schema === 'financeLine') {
				return filters.portfolio === 'pf1' ? [{ id: 'l1' }] : []
			}
			if (schema === 'humaniqTimeEntry') {
				return [{ id: `te-${filters.projectId}`, projectId: filters.projectId, hours: 1 }]
			}
			return []
		})

		const money = await store.fetchPortfolioMoney('pf1', [{ id: 'p1' }, { id: 'p2' }])

		expect(money.lines).toEqual([{ id: 'l1' }])
		expect(money.entriesByProject.p1).toEqual([expect.objectContaining({ id: 'te-p1', project: 'p1', duration: 60 })])
		expect(money.entriesByProject.p2).toEqual([expect.objectContaining({ id: 'te-p2', project: 'p2', duration: 60 })])
	})

	it('reads a project\'s hours from humaniq and the rate from planninq', async () => {
		hoursApp.on = true
		fetchCollection.mockImplementation(async (schema, filters) => {
			if (schema === 'humaniqTimeEntry') {
				return [{ id: 'te-1', projectId: 'p1', userId: 'anna', date: '2026-10-07', hours: 1.5, domainObjectType: 'planninq:task', domainObjectRef: 't1' }]
			}
			if (schema === 'plannedTimeEntry' && filters.timeEntry === 'te-1') {
				return [{ id: 'pl-1', timeEntry: 'te-1', hourlyRate: 80 }]
			}
			return []
		})

		const rows = await store.fetchProjectTimeEntries('p1')

		expect(fetchCollection).toHaveBeenCalledWith('humaniqTimeEntry', { projectId: 'p1', _limit: expect.any(Number), _page: 1 })
		expect(rows).toEqual([expect.objectContaining({ id: 'te-1', plannedId: 'pl-1', task: 't1', project: 'p1', user: 'anna', duration: 90, hourlyRate: 80 })])
	})

	it('reads no project hours, and asks nothing, without humaniq', async () => {
		hoursApp.on = false
		try {
			expect(await store.fetchProjectTimeEntries('p1')).toEqual([])
			expect(fetchCollection).not.toHaveBeenCalled()
		} finally {
			hoursApp.on = true
		}
	})

	it('answers no project hours when humaniq cannot be read', async () => {
		fetchCollection.mockRejectedValueOnce(new Error('offline'))

		expect(await store.fetchProjectTimeEntries('p1')).toEqual([])
		expect(errors).toHaveBeenCalledWith('fetchProjectTimeEntries error:', expect.any(Error))
	})

	it('answers no portfolio money when the lines cannot be read', async () => {
		fetchCollection.mockRejectedValue(new Error('offline'))

		expect(await store.fetchPortfolioMoney('pf1', [{ id: 'p1' }])).toEqual({ lines: [], entriesByProject: {} })
	})

	it('reads one project or view with the user\'s rights, null when absent, refused or unreachable', async () => {
		fetch.mockResolvedValueOnce(answer(true, { id: 'p1', name: 'Bakery' }))
			.mockResolvedValueOnce(answer(false))
			.mockRejectedValueOnce(new Error('offline'))
			.mockResolvedValueOnce(answer(true, { id: 'v1' }))

		expect(await store.readProject('p1')).toEqual({ id: 'p1', name: 'Bakery' })
		expect(fetch).toHaveBeenCalledWith('/apps/openregister/api/objects/planninq/project/p1', { headers: { 'X-Test': '1' } })
		expect(await store.readProject('p1')).toBeNull()
		expect(await store.readProject('p1')).toBeNull()
		expect(await store.fetchBoardView('v1')).toEqual({ id: 'v1' })
		expect(fetch).toHaveBeenLastCalledWith('/apps/openregister/api/objects/planninq/boardView/v1', { headers: { 'X-Test': '1' } })
		expect(await store.readProject('')).toBeNull()
		expect(fetch).toHaveBeenCalledTimes(4)
	})
})
