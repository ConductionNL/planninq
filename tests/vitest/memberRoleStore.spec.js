/**
 * The projects store's setMemberRole and removeMember write only the role
 * lists that change (projects-members-and-roles, task 4.4).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.4
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'olga' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { useProjectsStore } = await import('../../src/store/projects.js')

const PROJECT = { id: 'p1', owner: 'olga', managers: ['mark'], members: ['olga', 'mies'], viewers: ['vera'], memberGroups: ['adviseurs'] }

describe('setMemberRole', () => {
	let store
	let fetchMock

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useProjectsStore()
		store.fetchProject = vi.fn(async () => ({ ...PROJECT }))
		fetchMock = vi.fn(async (url, init) => ({ ok: true, json: async () => ({ ...PROJECT, ...JSON.parse(init.body) }) }))
		vi.stubGlobal('fetch', fetchMock)
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('PATCHes only the two lists a member-to-manager move changes', async () => {
		await store.setMemberRole('p1', 'mies', 'user', 'manager')
		expect(fetchMock).toHaveBeenCalledTimes(1)
		const [url, init] = fetchMock.mock.calls[0]
		expect(url).toBe('/apps/openregister/api/objects/planninq/project/p1')
		expect(init.method).toBe('PATCH')
		expect(JSON.parse(init.body)).toEqual({ managers: ['mark', 'mies'], members: ['olga'] })
	})

	it('moves a group to viewer through the group lists', async () => {
		await store.setMemberRole('p1', 'adviseurs', 'group', 'viewer')
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ memberGroups: [], viewerGroups: ['adviseurs'] })
	})

	it('sends nothing when the role does not change', async () => {
		await store.setMemberRole('p1', 'mark', 'user', 'manager')
		expect(fetchMock).not.toHaveBeenCalled()
	})

	it('removes a viewer from the viewers list, not from members', async () => {
		await store.removeMember('p1', 'vera')
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ viewers: [] })
	})

	it('still refuses to remove the last member', async () => {
		store.fetchProject = vi.fn(async () => ({ id: 'p1', owner: 'olga', members: ['olga'] }))
		await expect(store.removeMember('p1', 'olga')).rejects.toThrow('last member')
		expect(fetchMock).not.toHaveBeenCalled()
	})
})
