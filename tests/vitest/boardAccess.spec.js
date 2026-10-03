/**
 * What the board shows a person who cannot reach the project, and the note a
 * read-only person gets (live pass P7 and P5, 2 Oct).
 *
 * P7: after leaving the only group that shared a project, OpenRegister answers
 * 404 for it (its read rules hide the object), the store records `not-found`,
 * and the board rendered an empty page instead of the no-access message.
 * P5: a plain viewer was told they read the project "as a manager of its portfolio".
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/initial-state', () => ({ loadState: (app, key, fallback) => fallback }))

const { boardAccessDenied } = await import('../../src/utils/projectRole.js')
const { readOnlyReason } = await import('../../src/utils/portfolioGrouping.js')

const PROJECT = { id: 'ac027966', owner: 'olga', members: ['olga'], memberGroups: ['pq-crew'], viewers: ['vera'], portfolioReaders: ['pim'] }

describe('boardAccessDenied', () => {
	it('denies when OpenRegister answered 404 for a project its rules hide', () => {
		expect(boardAccessDenied({ error: 'not-found', loading: false, activeProject: null }, 'pq-bram', [])).toBe(true)
	})

	it('denies on a 403', () => {
		expect(boardAccessDenied({ error: 'forbidden', loading: false, activeProject: null }, 'pq-bram', [])).toBe(true)
	})

	it('denies a loaded project the person holds no role on, and allows a group member', () => {
		const store = { error: null, loading: false, activeProject: PROJECT }
		expect(boardAccessDenied(store, 'pq-bram', [])).toBe(true)
		expect(boardAccessDenied(store, 'pq-bram', ['pq-crew'])).toBe(false)
	})

	it('does not deny while loading, nor on a not-found from another fetch while the project is shown', () => {
		expect(boardAccessDenied({ error: 'not-found', loading: true, activeProject: null }, 'olga', [])).toBe(false)
		expect(boardAccessDenied({ error: 'not-found', loading: false, activeProject: PROJECT }, 'olga', [])).toBe(false)
	})
})

describe('readOnlyReason', () => {
	it('names a viewer as a viewer, in person or through a group', () => {
		expect(readOnlyReason(PROJECT, { uid: 'vera' })).toBe('viewer')
		expect(readOnlyReason({ ...PROJECT, viewerGroups: ['lezers'] }, { uid: 'loes' }, ['lezers'])).toBe('viewer')
	})

	it('names a portfolio manager who is not on the project as one', () => {
		expect(readOnlyReason(PROJECT, { uid: 'pim' })).toBe('portfolio')
	})

	it('gives no reason to someone who can write, or to an admin', () => {
		expect(readOnlyReason(PROJECT, { uid: 'olga' })).toBe(null)
		expect(readOnlyReason(PROJECT, { uid: 'vera', isAdmin: true })).toBe(null)
	})
})
