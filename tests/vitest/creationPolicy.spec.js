/**
 * Vitest unit tests for the project creation policy (projects-lifecycle-policy,
 * section 2): the list reads the server's answer, and the admin page reads and
 * writes the group list.
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { canCreateFrom, creationGroupIds, creationGroupsSetting } from '../../src/utils/creationPolicy.js'

describe('canCreateFrom (scenario: only the chosen groups may create)', () => {
	it('follows the server flag, which knows the groups', () => {
		expect(canCreateFrom({ allow_project_creation: 'groups', canCreateProject: true }, false)).toBe(true)
		expect(canCreateFrom({ allow_project_creation: 'groups', canCreateProject: false }, false)).toBe(false)
		expect(canCreateFrom({ allow_project_creation: 'all', canCreateProject: false }, true)).toBe(false)
	})

	it('falls back to the policy when an older server sends no flag', () => {
		expect(canCreateFrom({ allow_project_creation: 'admins' }, false)).toBe(false)
		expect(canCreateFrom({ allow_project_creation: 'admins' }, true)).toBe(true)
		expect(canCreateFrom({ allow_project_creation: 'groups' }, false)).toBe(false)
		expect(canCreateFrom({}, false)).toBe(true)
	})
})

describe('creation groups setting', () => {
	it('reads the stored JSON list, ignoring rubbish', () => {
		expect(creationGroupIds('["projectleiders","staf"]')).toEqual(['projectleiders', 'staf'])
		expect(creationGroupIds('not json')).toEqual([])
		expect(creationGroupIds(undefined)).toEqual([])
		expect(creationGroupIds('[1,"staf",""]')).toEqual(['staf'])
	})

	it('writes the picked groups as a JSON list of ids', () => {
		expect(creationGroupsSetting([{ id: 'projectleiders', label: 'Projectleiders' }, 'staf'])).toBe('["projectleiders","staf"]')
		expect(creationGroupsSetting(null)).toBe('[]')
	})
})
