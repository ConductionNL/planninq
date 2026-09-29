/**
 * Vitest unit tests for the project lifecycle (projects-lifecycle-policy,
 * section 1): the transition request the store sends for archive and
 * restore, and which of the two buttons a project shows, from the actions
 * OpenRegister offers or, when that list is missing, from the status. The
 * actions are checked against the real project lifecycle block.
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.3
 */
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { actionNames, lifecycleButtons, transitionRequest } from '../../src/utils/projectLifecycle.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
const lifecycle = register.components.schemas.project['x-openregister-lifecycle']

describe('transitionRequest (task 1.3)', () => {
	it('posts archive and restore to the OpenRegister transition endpoint', () => {
		expect(transitionRequest('p-1', 'archive')).toEqual({ path: '/apps/openregister/api/objects/p-1/transition', body: { action: 'archive' } })
		expect(transitionRequest('p-1', 'restore')).toEqual({ path: '/apps/openregister/api/objects/p-1/transition', body: { action: 'restore' } })
	})

	it('names only actions the project schema declares', () => {
		for (const action of ['archive', 'restore']) {
			expect(Object.keys(lifecycle.transitions)).toContain(transitionRequest('p-1', action).body.action)
		}
	})

	it('encodes the id', () => {
		expect(transitionRequest('a/b', 'archive').path).toBe('/apps/openregister/api/objects/a%2Fb/transition')
	})
})

describe('actionNames', () => {
	it('reads the available-actions answer', () => {
		expect(actionNames({ actions: [{ action: 'restore', to: 'active' }, { action: 'archive', to: 'archived', blocked: true }] })).toEqual(['restore'])
		expect(actionNames(null)).toBeNull()
		expect(actionNames({})).toBeNull()
	})
})

describe('lifecycleButtons (scenario: restore from the project settings sidebar)', () => {
	it('follows the actions OpenRegister offers', () => {
		expect(lifecycleButtons({ status: 'archived' }, ['restore'])).toEqual({ archive: false, restore: true })
		expect(lifecycleButtons({ status: 'active' }, ['archive', 'complete', 'cancel'])).toEqual({ archive: true, restore: false })
		expect(lifecycleButtons({ status: 'archived' }, [])).toEqual({ archive: false, restore: false })
	})

	it('falls back to the declared transitions for the status when the list is missing', () => {
		expect(lifecycleButtons({ status: 'archived' }, null)).toEqual({ archive: false, restore: true })
		expect(lifecycleButtons({ status: 'active' }, null)).toEqual({ archive: true, restore: false })
		expect(lifecycleButtons({ status: 'completed' }, null)).toEqual({ archive: true, restore: true })
		expect(lifecycleButtons({ status: 'requested' }, null)).toEqual({ archive: false, restore: false })
		for (const status of ['active', 'archived', 'completed', 'cancelled', 'requested']) {
			const buttons = lifecycleButtons({ status }, null)
			expect(buttons.archive).toBe(lifecycle.transitions.archive.from.includes(status))
			expect(buttons.restore).toBe(lifecycle.transitions.restore.from.includes(status))
		}
	})
})
