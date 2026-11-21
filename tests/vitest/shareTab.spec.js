/**
 * The Share tab's link handling (projects-public-share task 3.1): the share
 * view of a project, the end-date rules and the listing of a project's live
 * links, including a link someone else made, with only fetch and the
 * Nextcloud globals stood in for.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({ requesttoken: 'tok' }) }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) => '/index.php' + path.replace('{anchor}', params.anchor ?? '{anchor}'),
}))

const share = await import('../../src/utils/projectShare.js')

const NOW = new Date('2026-11-17T10:00:00Z')
const VIEW = { id: 12, uuid: 'v-fiets', name: 'Fietspad', query: { registers: ['planninq'], schemas: ['column', 'task'], filters: { project: 'p-1' } } }
const OTHER_VIEW = { id: 13, uuid: 'v-other', name: 'Other', query: { registers: ['planninq'], schemas: ['column', 'task'], filters: { project: 'p-2' } } }

/**
 * A fetch stand-in that answers each call from a list, and records the calls.
 *
 * @param {Array<[number, object]>} answers status and body per call, in order
 * @return {Function} the mock
 */
function answering(answers) {
	const queue = [...answers]
	const mock = vi.fn(async () => {
		const [status, body] = queue.shift() ?? [500, {}]
		return { ok: status >= 200 && status < 300, status, text: async () => JSON.stringify(body) }
	})
	globalThis.fetch = mock
	return mock
}

afterEach(() => {
	delete globalThis.fetch
})

describe('end dates', () => {
	it('starts a link 30 days ahead and allows one year at most', () => {
		expect(share.defaultEndDate(NOW)).toBe('2026-12-17')
		expect(share.latestEndDate(NOW)).toBe('2027-11-17')
	})

	it('refuses a missing, past or too distant end date', () => {
		expect(share.endDateRefusal('', NOW)).toBe('missing')
		expect(share.endDateRefusal('2026-11-17', NOW)).toBe('past')
		expect(share.endDateRefusal('2027-11-18', NOW)).toBe('too-far')
		expect(share.endDateRefusal('2027-11-17', NOW)).toBeNull()
		expect(share.endDateRefusal('2026-11-18', NOW)).toBeNull()
	})
})

describe('the project view', () => {
	it('selects the project\'s columns and tasks by project', () => {
		expect(share.projectViewQuery('p-1')).toEqual({ registers: ['planninq'], schemas: ['column', 'task'], filters: { project: 'p-1' } })
	})

	it('recognises only a task view filtered on this project', () => {
		expect(share.isProjectView(VIEW, 'p-1')).toBe(true)
		expect(share.isProjectView(OTHER_VIEW, 'p-1')).toBe(false)
		expect(share.isProjectView({ query: { schemas: ['risk'], filters: { project: 'p-1' } } }, 'p-1')).toBe(false)
		expect(share.isProjectView(VIEW, '')).toBe(false)
	})

	it('creates the view when the project has none, then mints a read link on its uuid', async () => {
		const fetch = answering([
			[200, { results: [OTHER_VIEW] }],
			[201, { view: VIEW }],
			[201, { id: 7, anchor: 'Ab12', subjectType: 'view', subjectId: 'v-fiets' }],
		])

		const link = await share.createProjectLink({ id: 'p-1', title: 'Fietspad' }, { endDate: '2026-12-17', password: 'fiets' })

		expect(link.anchor).toBe('Ab12')
		expect(fetch.mock.calls.map(([url, init]) => `${init?.method ?? 'GET'} ${url}`)).toEqual([
			'GET /index.php/apps/openregister/api/views',
			'POST /index.php/apps/openregister/api/views',
			'POST /index.php/apps/openregister/api/access-links',
		])
		expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ name: 'Fietspad', query: share.projectViewQuery('p-1') })
		expect(JSON.parse(fetch.mock.calls[2][1].body)).toEqual({
			subjectType: 'view',
			subjectId: 'v-fiets',
			capabilities: ['read'],
			expiresAt: '2026-12-17T23:59:59Z',
			label: 'Fietspad',
			password: 'fiets',
		})
	})

	it('reuses the existing view and sends no password when none is set', async () => {
		const fetch = answering([
			[200, { results: [VIEW] }],
			[201, { id: 8, anchor: 'Cd34' }],
		])

		await share.createProjectLink({ id: 'p-1', title: 'Fietspad' }, { endDate: '2026-12-17', password: '' })

		expect(fetch).toHaveBeenCalledTimes(2)
		expect(JSON.parse(fetch.mock.calls[1][1].body)).not.toHaveProperty('password')
	})
})

describe('the project\'s live links', () => {
	it('lists every live link on the project\'s view, whoever made it', async () => {
		answering([
			[200, { results: [VIEW, OTHER_VIEW] }],
			[200, {
				results: [
					{ id: 1, subjectType: 'view', subjectId: 'v-fiets', createdBy: 'anna', live: true },
					{ id: 2, subjectType: 'view', subjectId: '12', createdBy: 'bram', live: true },
					{ id: 3, subjectType: 'view', subjectId: 'v-fiets', createdBy: 'anna', live: false },
					{ id: 4, subjectType: 'view', subjectId: 'v-other', createdBy: 'anna', live: true },
					{ id: 5, subjectType: 'object', subjectId: 'v-fiets', createdBy: 'anna', live: true },
				],
			}],
		])

		const links = await share.projectLinks('p-1')

		expect(links.map((link) => [link.id, link.createdBy])).toEqual([[1, 'anna'], [2, 'bram']])
	})

	it('asks for no links when the project has no view', async () => {
		const fetch = answering([[200, { results: [OTHER_VIEW] }]])

		expect(await share.projectLinks('p-1')).toEqual([])
		expect(fetch).toHaveBeenCalledTimes(1)
	})

	it('switches a link off with a DELETE on the link', async () => {
		const fetch = answering([[200, {}]])

		await share.switchOffLink(7)

		expect(fetch.mock.calls[0][0]).toBe('/index.php/apps/openregister/api/access-links/7')
		expect(fetch.mock.calls[0][1].method).toBe('DELETE')
	})

	it('throws on a refused switch-off so the tab keeps the link', async () => {
		answering([[404, {}]])

		await expect(share.switchOffLink(7)).rejects.toMatchObject({ status: 404 })
	})

	it('builds the public board address from the anchor', () => {
		expect(share.publicBoardUrl('Ab12')).toMatch(/\/index\.php\/apps\/planninq\/public\/Ab12$/)
	})
})
