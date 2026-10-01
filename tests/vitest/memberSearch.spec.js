/**
 * Member search through Nextcloud's own autocomplete endpoint
 * (projects-members-and-roles, section 3).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({ generateOcsUrl: (path) => `/ocs/v2.php${path}` }))

const { autocompleteUrl, memberEntries, searchMembers, toMemberOptions } = await import('../../src/utils/memberSearch.js')
const { groupName } = await import('../../src/utils/userNames.js')

/** What core's autocomplete answers for "ada" with users and groups asked. */
const ANSWER = {
	ocs: {
		meta: { status: 'ok', statuscode: 200 },
		data: [
			{ id: 'ada', label: 'Ada Jansen', icon: 'icon-user', source: 'users', status: [], subline: '', shareWithDisplayNameUnique: 'ada@example.org' },
			{ id: 'adam', label: 'Adam de Vries', icon: 'icon-user', source: 'users', status: [], subline: '', shareWithDisplayNameUnique: 'adam' },
			{ id: 'adviseurs', label: 'Adviseurs', icon: 'icon-group', source: 'groups', status: [], subline: '', shareWithDisplayNameUnique: 'adviseurs' },
		],
	},
}

describe('memberSearch', () => {
	let fetchMock

	beforeEach(() => {
		fetchMock = vi.fn(async () => ({ ok: true, json: async () => ANSWER }))
		vi.stubGlobal('fetch', fetchMock)
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('asks core autocomplete for users and groups, not the provisioning API', () => {
		const url = new URL(autocompleteUrl('Ada'), 'https://nc.example')
		expect(url.pathname).toBe('/ocs/v2.php/core/autocomplete/get')
		expect(url.searchParams.get('search')).toBe('Ada')
		expect(url.searchParams.getAll('shareTypes[]')).toEqual(['0', '1'])
		expect(url.searchParams.get('limit')).toBe('10')
		expect(url.searchParams.get('format')).toBe('json')
		expect(url.toString()).not.toContain('cloud/users')
	})

	it('turns the answer into options with display names, a group marked as a group', () => {
		const options = toMemberOptions(ANSWER.ocs.data, { groupSubname: 'Everyone in this group' })
		expect(options).toEqual([
			{ key: 'user:ada', id: 'ada', type: 'user', displayName: 'Ada Jansen', subname: 'ada@example.org', isNoUser: false },
			{ key: 'user:adam', id: 'adam', type: 'user', displayName: 'Adam de Vries', subname: '', isNoUser: false },
			{ key: 'group:adviseurs', id: 'adviseurs', type: 'group', displayName: 'Adviseurs', subname: 'Everyone in this group', isNoUser: true },
		])
	})

	it('leaves out people and groups already on the project', () => {
		const options = toMemberOptions(ANSWER.ocs.data, { members: ['adam'], groups: ['adviseurs'] })
		expect(options.map((o) => o.key)).toEqual(['user:ada'])
	})

	it('searches with the OCS header and returns the options', async () => {
		const options = await searchMembers('Ada', { members: ['ada'] })
		expect(fetchMock).toHaveBeenCalledTimes(1)
		const [url, init] = fetchMock.mock.calls[0]
		expect(url).toContain('/ocs/v2.php/core/autocomplete/get?')
		expect(url).toContain('search=Ada')
		expect(init.headers['OCS-APIRequest']).toBe('true')
		expect(options.map((o) => o.displayName)).toEqual(['Adam de Vries', 'Adviseurs'])
	})

	it('throws when the endpoint refuses, so the field can say so', async () => {
		fetchMock.mockResolvedValueOnce({ ok: false, status: 500, statusText: 'Server Error', json: async () => ({}) })
		await expect(searchMembers('Ada')).rejects.toThrow('500')
	})

	it('answers nothing for fewer than two characters without a request', async () => {
		expect(await searchMembers('A')).toEqual([])
		expect(fetchMock).not.toHaveBeenCalled()
	})

	it('resolves a group id to its name through the same endpoint', async () => {
		expect(await groupName('adviseurs')).toBe('Adviseurs')
		const url = fetchMock.mock.calls[0][0]
		expect(url).toContain('shareTypes%5B%5D=1')
	})

	it('lists the members tab by name: people first, then groups', () => {
		const project = { members: ['ada', 'bram'], memberGroups: ['adviseurs'] }
		expect(memberEntries(project, { ada: 'Ada Jansen' }, { adviseurs: 'Adviseurs' })).toEqual([
			{ key: 'user:ada', id: 'ada', type: 'user', role: 'member', name: 'Ada Jansen' },
			{ key: 'user:bram', id: 'bram', type: 'user', role: 'member', name: 'bram' },
			{ key: 'group:adviseurs', id: 'adviseurs', type: 'group', role: 'member', name: 'Adviseurs' },
		])
	})

	it('lists each person and group once, with the highest role they hold', () => {
		const project = { owner: 'olga', ownerGroups: ['bestuur'], managers: ['mark'], members: ['olga', 'mark', 'mies'], viewers: ['vera'], viewerGroups: ['lezers', 'bestuur'] }
		expect(memberEntries(project).map((row) => `${row.key}=${row.role}`)).toEqual([
			'user:olga=owner',
			'user:mark=manager',
			'user:mies=member',
			'user:vera=viewer',
			'group:bestuur=owner',
			'group:lezers=viewer',
		])
	})
})
