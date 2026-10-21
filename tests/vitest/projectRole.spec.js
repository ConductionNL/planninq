/**
 * Roles on a project in the interface (projects-members-and-roles, section 4).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import Ajv from 'ajv'
import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'

let state = ['adviseurs']
vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => (app === 'planninq' && key === 'groups' ? state : fallback) }))

const { canManageMembers, canWrite, currentGroupIds, projectRole, rolePatch } = await import('../../src/utils/projectRole.js')
const { canSeeProject, isReadOnlyFor } = await import('../../src/utils/portfolioGrouping.js')

const PROJECT = {
	owner: 'olga',
	ownerGroups: ['bestuur'],
	managers: ['mark'],
	managerGroups: ['leiding'],
	members: ['olga', 'mies'],
	memberGroups: ['adviseurs'],
	viewers: ['vera'],
	viewerGroups: ['lezers'],
	portfolioReaders: ['pim'],
}

describe('projectRole', () => {
	it('names the owner, by user and by the owning group', () => {
		expect(projectRole(PROJECT, 'olga')).toBe('owner')
		expect(projectRole(PROJECT, 'bert', ['bestuur'])).toBe('owner')
	})

	it('names a manager, a member and a viewer, by user and by group', () => {
		expect(projectRole(PROJECT, 'mark')).toBe('manager')
		expect(projectRole(PROJECT, 'lies', ['leiding'])).toBe('manager')
		expect(projectRole(PROJECT, 'mies')).toBe('member')
		expect(projectRole(PROJECT, 'anna', ['adviseurs'])).toBe('member')
		expect(projectRole(PROJECT, 'vera')).toBe('viewer')
		expect(projectRole(PROJECT, 'loes', ['lezers'])).toBe('viewer')
	})

	it('reads a portfolio manager as a viewer and a stranger as none', () => {
		expect(projectRole(PROJECT, 'pim')).toBe('viewer')
		expect(projectRole(PROJECT, 'zoe', ['elders'])).toBe('none')
		expect(projectRole(null, 'olga')).toBe('none')
		expect(projectRole(PROJECT, '')).toBe('none')
	})

	it('lets the highest role win', () => {
		expect(projectRole({ ...PROJECT, viewers: ['mark'] }, 'mark')).toBe('manager')
		expect(projectRole(PROJECT, 'vera', ['adviseurs'])).toBe('member')
		expect(projectRole(PROJECT, 'mies', ['leiding', 'lezers'])).toBe('manager')
	})

	it('says who writes and who manages members', () => {
		expect(['owner', 'manager', 'member', 'viewer', 'none'].map(canWrite)).toEqual([true, true, true, false, false])
		expect(['owner', 'manager', 'member', 'viewer', 'none'].map(canManageMembers)).toEqual([true, true, false, false, false])
	})

	it('reads the caller\'s groups from the initial state', () => {
		expect(currentGroupIds()).toEqual(['adviseurs'])
		state = 'not a list'
		expect(currentGroupIds()).toEqual([])
		state = ['adviseurs']
	})
})

describe('the store and the board use the role (task 4.2)', () => {
	it('keeps a project shared with the caller\'s group in the list', () => {
		const shared = { owner: 'olga', members: ['olga'], memberGroups: ['adviseurs'] }
		expect(canSeeProject(shared, 'anna')).toBe(false)
		expect(canSeeProject(shared, 'anna', ['adviseurs'])).toBe(true)
		expect(canSeeProject({ owner: 'olga', members: ['olga'], viewers: ['vera'] }, 'vera')).toBe(true)
	})

	it('opens the board read-only for a viewer, directly or through a group', () => {
		expect(isReadOnlyFor(PROJECT, { uid: 'vera' })).toBe(true)
		expect(isReadOnlyFor(PROJECT, { uid: 'loes' }, ['lezers'])).toBe(true)
		expect(isReadOnlyFor(PROJECT, { uid: 'anna' }, ['adviseurs'])).toBe(false)
		expect(isReadOnlyFor(PROJECT, { uid: 'vera', isAdmin: true })).toBe(false)
	})
})

describe('rolePatch (task 4.4)', () => {
	const schema = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
		.components.schemas.project
	const ajv = new Ajv({ strict: false, allErrors: true })
	const properties = Object.fromEntries(['managers', 'members', 'viewers', 'managerGroups', 'memberGroups', 'viewerGroups'].map((field) => [field, schema.properties[field]]))
	const validatePatch = ajv.compile({ type: 'object', properties, additionalProperties: false })

	it('moves a member to manager by writing only the two lists that change', () => {
		const patch = rolePatch(PROJECT, 'mies', 'user', 'manager')
		expect(patch).toEqual({ managers: ['mark', 'mies'], members: ['olga'] })
		expect(validatePatch(patch), JSON.stringify(validatePatch.errors)).toBe(true)
	})

	it('gives a group the viewer role', () => {
		expect(rolePatch(PROJECT, 'adviseurs', 'group', 'viewer')).toEqual({ memberGroups: [], viewerGroups: ['lezers', 'adviseurs'] })
	})

	it('removes a person from every list when the role is null', () => {
		expect(rolePatch(PROJECT, 'vera', 'user', null)).toEqual({ viewers: [] })
	})

	it('writes nothing when the role does not change and refuses an unknown role', () => {
		expect(rolePatch(PROJECT, 'mark', 'user', 'manager')).toEqual({})
		expect(() => rolePatch(PROJECT, 'mark', 'user', 'owner')).toThrow('Unknown role')
	})
})
