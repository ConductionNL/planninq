/**
 * Golden test for projectRole: every caller shape against every project
 * shape, so both the roles a caller gets and the ones they are refused are
 * pinned. Recorded inline from the code before it was split.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))

const { projectRole } = await import('../../src/utils/projectRole.js')

const PROJECTS = {
	full: {
		owner: 'olga',
		ownerGroups: ['bestuur'],
		managers: ['mark'],
		managerGroups: ['leiding'],
		members: ['olga', 'mies', 'mark'],
		memberGroups: ['adviseurs'],
		viewers: ['vera', 'mies'],
		viewerGroups: ['lezers'],
		portfolioReaders: ['pim', 'vera'],
	},
	lists: { owner: 'olga', managers: 'mark', memberGroups: 'adviseurs', viewers: null, portfolioReaders: ['pim'] },
	bare: {},
	missing: null,
}

const CALLERS = {
	owner: ['olga', []],
	'owner group': ['zoe', ['bestuur']],
	manager: ['mark', []],
	'manager group': ['zoe', ['x', 'leiding']],
	member: ['mies', []],
	'member group': ['zoe', ['adviseurs']],
	viewer: ['vera', []],
	'viewer group': ['zoe', ['lezers']],
	'portfolio reader': ['pim', []],
	stranger: ['zoe', ['nobody']],
	'groups not a list': ['zoe', 'bestuur'],
	'no uid': ['', ['bestuur']],
}

const EXPECTED = {
	full: ['owner', 'owner', 'manager', 'manager', 'member', 'member', 'viewer', 'viewer', 'viewer', 'none', 'none', 'none'],
	lists: ['owner', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'viewer', 'none', 'none', 'none'],
	bare: ['none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none'],
	missing: ['none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none', 'none'],
}

describe('projectRole golden', () => {
	it.each(Object.keys(PROJECTS))('%s project', (name) => {
		const roles = Object.values(CALLERS).map(([uid, groups]) => projectRole(PROJECTS[name], uid, groups))
		expect(roles).toEqual(EXPECTED[name])
	})
})
