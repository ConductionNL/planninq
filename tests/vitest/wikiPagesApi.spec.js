/**
 * The project wiki API client: listing a project's pages, creating and
 * updating a page (with the version it was read at), deleting, locking and
 * unlocking it, its history newest first, restoring a version and the lock
 * holder edge cases. Only axios and the router are faked.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const server = vi.hoisted(() => ({ answers: [], calls: [] }))

vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/axios', () => {
	const verb = (method) => async (url, bodyOrConfig) => {
		server.calls.push([method, url, bodyOrConfig])
		const next = server.answers.shift()
		if (next instanceof Error) {
			throw next
		}
		return { data: next }
	}
	return { default: { get: verb('get'), post: verb('post'), patch: verb('patch'), delete: verb('delete') } }
})

const api = await import('../../src/api/wikiPages.js')

const BASE = '/index.php/apps/openregister/api/objects/planninq/wikiPage'

describe('wiki pages api', () => {
	beforeEach(() => {
		server.answers = []
		server.calls = []
	})

	it('lists a project\'s pages from a bare list or a results envelope', async () => {
		server.answers.push([{ id: 'w1' }], { results: [{ id: 'w2' }], total: 1 }, { total: 0 }, null)

		expect(await api.fetchWikiPages('p-1')).toEqual([{ id: 'w1' }])
		expect(server.calls[0]).toEqual(['get', BASE, { params: { project: 'p-1', _limit: 1000 } }])
		expect(await api.fetchWikiPages('p-1')).toEqual([{ id: 'w2' }])
		expect(await api.fetchWikiPages('p-1')).toEqual([])
		expect(await api.fetchWikiPages('p-1')).toEqual([])
	})

	it('creates a page without an id by POSTing its fields', async () => {
		server.answers.push({ id: 'w9', title: 'Start' })

		expect(await api.saveWikiPage({ title: 'Start', project: 'p-1' }, '2026-11-01')).toEqual({ id: 'w9', title: 'Start' })
		expect(server.calls).toEqual([['post', BASE, { title: 'Start', project: 'p-1' }]])
	})

	it('updates a page with an id by PATCHing its fields, with the version it was read at', async () => {
		server.answers.push({ id: 'w1' }, { id: 'w1' })

		await api.saveWikiPage({ id: 'w1', title: 'Start' }, '2026-11-01T10:00:00+00:00')
		expect(server.calls[0]).toEqual(['patch', `${BASE}/w1`, { title: 'Start', _expectedUpdated: '2026-11-01T10:00:00+00:00' }])

		await api.saveWikiPage({ id: 'w1', title: 'Start' })
		expect(server.calls[1]).toEqual(['patch', `${BASE}/w1`, { title: 'Start' }])
	})

	it('lets a stale write\'s refusal reach the caller', async () => {
		const conflict = Object.assign(new Error('409'), { response: { status: 409 } })
		server.answers.push(conflict)

		await expect(api.saveWikiPage({ id: 'w1', title: 'x' }, 'v1')).rejects.toBe(conflict)
	})

	it('deletes, locks and unlocks a page by its encoded id', async () => {
		server.answers.push(undefined, { id: 'a/b', locked: { user: 'ada' } }, undefined)

		expect(await api.deleteWikiPage('a/b')).toBeUndefined()
		expect(await api.lockWikiPage('a/b')).toEqual({ id: 'a/b', locked: { user: 'ada' } })
		expect(await api.unlockWikiPage('a/b')).toBeUndefined()
		expect(server.calls).toEqual([
			['delete', `${BASE}/a%2Fb`, undefined],
			['post', `${BASE}/a%2Fb/lock`, {}],
			['post', `${BASE}/a%2Fb/unlock`, {}],
		])
	})

	it('lists a page\'s history newest first, undated entries last', async () => {
		server.answers.push({ results: [
			{ id: 1, created: '2026-11-01T09:00:00Z' },
			{ id: 2 },
			{ id: 3, created: '2026-11-03T09:00:00Z' },
		] }, 'odd')

		expect((await api.fetchWikiPageHistory('w1')).map((entry) => entry.id)).toEqual([3, 1, 2])
		expect(server.calls[0]).toEqual(['get', `${BASE}/w1/audit-trails`, undefined])
		expect(await api.fetchWikiPageHistory('w1')).toEqual([])
	})

	it('restores a page to an audit trail entry', async () => {
		server.answers.push({ id: 'w1', title: 'Old' })

		expect(await api.restoreWikiPage('w1', 42)).toEqual({ id: 'w1', title: 'Old' })
		expect(server.calls[0]).toEqual(['post', `${BASE}/w1/revert`, { auditTrailId: 42 }])
	})

	it('names no lock holder for a missing, odd, nameless or expired lock', () => {
		const now = new Date('2026-11-14T12:00:00Z')

		expect(api.lockHolder(null, now)).toBeNull()
		expect(api.lockHolder({ locked: 'yes' }, now)).toBeNull()
		expect(api.lockHolder({ locked: { user: '' } }, now)).toBeNull()
		expect(api.lockHolder({ locked: { user: 'ada', expiration: '2026-11-14T12:00:00Z' } }, now)).toBeNull()
		expect(api.lockHolder({ locked: { user: 'ada', expiration: 'not a date' } }, now)).toEqual({ user: 'ada', displayName: 'ada' })
		expect(api.lockHolder({ locked: { userId: 'bob', userName: 'Bob B' } }, now)).toEqual({ user: 'bob', displayName: 'Bob B' })
	})
})
