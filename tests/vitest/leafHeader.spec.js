// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

import { describe, expect, it } from 'vitest'
import { leafHeader } from '../../src/integrations/leafHeader.js'

/**
 * The header the projects leaf reports to a host that draws its card.
 *
 * @spec openspec/specs/projects-leaf-library-card/spec.md#requirement-the-projects-leaf-takes-the-library-card
 */
describe('leafHeader', () => {
	const actions = [{ label: 'Open in Planninq', run: () => {} }, { label: 'New project', run: () => {} }]
	const base = { loading: false, error: '', total: 0, emptyLabel: 'No projects for this client yet', note: '', actions }

	it('reports a zero count, the actions and the empty state for no projects', () => {
		expect(leafHeader(base)).toEqual({ actions, note: '', count: 0, empty: { name: 'No projects for this client yet' } })
	})

	it('reports the count and the budget note, and no empty state, for projects', () => {
		expect(leafHeader({ ...base, total: 3, note: '€ 1.200' })).toEqual({ actions, note: '€ 1.200', count: 3 })
	})

	it('reports no count and no empty state while loading', () => {
		const header = leafHeader({ ...base, loading: true })
		expect(header.count).toBeUndefined()
		expect(header.empty).toBeUndefined()
	})

	it('reports no count and no empty state after a failed read', () => {
		const header = leafHeader({ ...base, error: 'Could not load projects' })
		expect(header.count).toBeUndefined()
		expect(header.empty).toBeUndefined()
	})
})
