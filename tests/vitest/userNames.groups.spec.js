/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Group names and the fallbacks of user names: an empty id, a refused or
 * failing autocomplete, and a group that only matches when it comes from the
 * groups source.
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.2
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({ generateOcsUrl: (path) => '/ocs/v2.php' + path }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => null }))

const { displayName, groupName, groupNames } = await import('../../src/utils/userNames.js')

/**
 * An autocomplete answer.
 *
 * @param {Array<object>} data The matches
 * @return {object} A fetch response
 */
const answer = (data) => ({ ok: true, json: async () => ({ ocs: { data } }) })

beforeEach(() => {
	globalThis.fetch = vi.fn()
})

describe('groupName', () => {
	it('asks autocomplete for groups and takes the label of the groups-source match', async () => {
		globalThis.fetch.mockResolvedValue(answer([
			{ id: 'staff', label: 'Staff user', source: 'users' },
			{ id: 'staff', label: 'All staff', source: 'groups' },
		]))

		expect(await groupName('staff')).toBe('All staff')
		expect(globalThis.fetch).toHaveBeenCalledTimes(1)
		const [url, options] = globalThis.fetch.mock.calls[0]
		const params = new URL(url, 'http://nc').searchParams
		expect(url.startsWith('/ocs/v2.php/core/autocomplete/get?')).toBe(true)
		expect(params.get('search')).toBe('staff')
		expect(params.get('shareTypes[]')).toBe('1')
		expect(options.headers).toEqual({ 'OCS-APIRequest': 'true', Accept: 'application/json' })
	})

	it('caches a group, so a second lookup does not fetch again', async () => {
		globalThis.fetch.mockResolvedValue(answer([{ id: 'board', label: 'Board', source: 'groups' }]))

		expect(await groupName('board')).toBe('Board')
		expect(await groupName('board')).toBe('Board')
		expect(globalThis.fetch).toHaveBeenCalledTimes(1)
	})

	it('falls back to the id for a refused answer, a user-only match and a failing request', async () => {
		globalThis.fetch.mockResolvedValueOnce({ ok: false, json: async () => ({}) })
		expect(await groupName('refused')).toBe('refused')

		globalThis.fetch.mockResolvedValueOnce(answer([{ id: 'people', label: 'People', source: 'users' }]))
		expect(await groupName('people')).toBe('people')

		globalThis.fetch.mockRejectedValueOnce(new Error('offline'))
		expect(await groupName('offline')).toBe('offline')
	})

	it('answers an empty name for an empty id without asking', async () => {
		expect(await groupName('')).toBe('')
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('groupNames', () => {
	it('keys every name by its group id', async () => {
		globalThis.fetch.mockImplementation(async (url) => {
			const search = new URL(url, 'http://nc').searchParams.get('search')
			return answer(search === 'ops' ? [{ id: 'ops', label: 'Operations', source: 'groups' }] : [])
		})

		expect(await groupNames(['ops', 'nobody'])).toEqual({ ops: 'Operations', nobody: 'nobody' })
	})

	it('answers an empty map for no groups', async () => {
		expect(await groupNames()).toEqual({})
		expect(await groupNames(null)).toEqual({})
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})
})

describe('displayName fallbacks', () => {
	it('answers an empty name for an empty id without asking', async () => {
		expect(await displayName('')).toBe('')
		expect(globalThis.fetch).not.toHaveBeenCalled()
	})

	it('falls back to the id when autocomplete fails', async () => {
		globalThis.fetch.mockRejectedValueOnce(new Error('offline'))
		expect(await displayName('u-offline')).toBe('u-offline')
	})
})
