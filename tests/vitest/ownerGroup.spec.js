/**
 * "Owned by group" on the Members tab (projects-members-and-roles, task 5.1):
 * only the owner, the owning group and admins set it, the search offers
 * groups only, and the store writes `ownerGroups` alone.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
 */
import Ajv from 'ajv'
import { readFileSync } from 'node:fs'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({ buildHeaders: () => ({}) }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'olga' }) }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))
vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path, generateOcsUrl: (path) => '/ocs/v2.php' + path }))
vi.mock('../../src/store/objectStore.js', () => ({ useObjectStore: () => ({ registerObjectType: () => {}, objectTypeRegistry: {} }) }))

const { useProjectsStore } = await import('../../src/store/projects.js')
const { canSetOwnerGroup, ownerGroupOf, projectRole } = await import('../../src/utils/projectRole.js')
const { autocompleteUrl, SHARE_TYPE_GROUP } = await import('../../src/utils/memberSearch.js')

const PROJECT = { id: 'p1', owner: 'olga', members: ['olga', 'mies'], managers: ['mark'], memberGroups: ['adviseurs'], ownerGroups: [] }

describe('who sets the owning group', () => {
	it('the owner and anyone in the owning group, not a manager or member', () => {
		const owned = { ...PROJECT, ownerGroups: ['infra'] }
		expect(canSetOwnerGroup(projectRole(owned, 'olga', []))).toBe(true)
		expect(canSetOwnerGroup(projectRole(owned, 'ina', ['infra']))).toBe(true)
		expect(canSetOwnerGroup(projectRole(owned, 'mark', []))).toBe(false)
		expect(canSetOwnerGroup(projectRole(owned, 'mies', []))).toBe(false)
		expect(canSetOwnerGroup(projectRole(owned, 'ada', ['adviseurs']))).toBe(false)
	})

	it('reads the owning group, or null', () => {
		expect(ownerGroupOf({ ownerGroups: ['infra'] })).toBe('infra')
		expect(ownerGroupOf({ ownerGroups: [] })).toBe(null)
		expect(ownerGroupOf({})).toBe(null)
	})
})

describe('the owning group search', () => {
	it('asks the core autocomplete for groups only', () => {
		const url = new URL(autocompleteUrl('inf', 10, [SHARE_TYPE_GROUP]), 'http://localhost')
		expect(url.pathname).toBe('/ocs/v2.php/core/autocomplete/get')
		expect(url.searchParams.getAll('shareTypes[]')).toEqual(['1'])
		expect(url.searchParams.get('search')).toBe('inf')
	})

	it('still asks for people and groups by default', () => {
		const url = new URL(autocompleteUrl('inf'), 'http://localhost')
		expect(url.searchParams.getAll('shareTypes[]')).toEqual(['0', '1'])
	})
})

describe('setOwnerGroup', () => {
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

	it('PATCHes ownerGroups alone, as a list of one', async () => {
		const saved = await store.setOwnerGroup('p1', 'infra')
		const [url, init] = fetchMock.mock.calls[0]
		expect(url).toBe('/apps/openregister/api/objects/planninq/project/p1')
		expect(init.method).toBe('PATCH')
		expect(JSON.parse(init.body)).toEqual({ ownerGroups: ['infra'] })
		expect(saved.ownerGroups).toEqual(['infra'])
	})

	it('clears the owning group with an empty list', async () => {
		store.fetchProject = vi.fn(async () => ({ ...PROJECT, ownerGroups: ['infra'] }))
		await store.setOwnerGroup('p1', null)
		expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({ ownerGroups: [] })
	})

	it('sends nothing when the group already owns the project', async () => {
		store.fetchProject = vi.fn(async () => ({ ...PROJECT, ownerGroups: ['infra'] }))
		await store.setOwnerGroup('p1', 'infra')
		expect(fetchMock).not.toHaveBeenCalled()
	})
})

describe('the PATCH body against the real project schema', () => {
	const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
	const { authorization, ...ownerGroups } = register.components.schemas.project.properties.ownerGroups
	for (const key of Object.keys(ownerGroups)) {
		if (key.startsWith('x-')) {
			delete ownerGroups[key]
		}
	}
	const validate = new Ajv({ strict: false }).compile({ type: 'object', properties: { ownerGroups } })

	it('accepts the body for one group and for none', async () => {
		setActivePinia(createPinia())
		const store = useProjectsStore()
		const bodies = []
		vi.stubGlobal('fetch', vi.fn(async (url, init) => {
			bodies.push(JSON.parse(init.body))
			return { ok: true, json: async () => ({}) }
		}))
		store.fetchProject = vi.fn(async () => ({ ...PROJECT, ownerGroups: ['old'] }))
		await store.setOwnerGroup('p1', 'infra')
		await store.setOwnerGroup('p1', null)
		vi.unstubAllGlobals()
		expect(bodies).toHaveLength(2)
		for (const body of bodies) {
			expect(validate(body), JSON.stringify(validate.errors)).toBe(true)
		}
	})

	it('control: the schema refuses two owning groups', () => {
		expect(validate({ ownerGroups: ['infra', 'beheer'] })).toBe(false)
	})
})
