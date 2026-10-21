// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { leafSummary } from '../../src/integrations/leafHeader.js'

/**
 * The summary the projects leaf reports to a "linked" card, and the case copy.
 *
 * @spec openspec/changes/projects-leaf-summary/specs/projects-leaf-summary/spec.md#requirement-the-projects-leaf-reports-a-summary-to-a-linked-card
 */
describe('leafSummary', () => {
	const actions = [{ label: 'New project', run: () => {} }, { label: 'Open in Planninq', run: () => {} }]
	const rows = [{ label: 'Fietspad', href: '/apps/planninq/projects/1' }]
	const base = { loading: false, error: '', total: 0, noun: 'projects', rows: [], actions }

	it('reports a zero count, the noun and the actions when there are no projects', () => {
		expect(leafSummary(base)).toEqual({ count: 0, noun: 'projects', rows: [], actions })
	})

	it('reports a row per listed project when there are projects', () => {
		expect(leafSummary({ ...base, total: 1, rows })).toEqual({ count: 1, noun: 'projects', rows, actions })
	})

	it('reports nothing while loading or after a failed read', () => {
		expect(leafSummary({ ...base, loading: true })).toBeNull()
		expect(leafSummary({ ...base, error: 'Could not load projects' })).toBeNull()
	})
})

describe('projects leaf copy on a case', () => {
	it('says "zaak", not "klant", on a case, in Dutch', () => {
		const nl = JSON.parse(readFileSync(new URL('../../l10n/nl.json', import.meta.url), 'utf8')).translations
		expect(nl['No projects for this case yet']).toBe('Nog geen projecten voor deze zaak')
		expect(nl.projects).toBe('projecten')
		const sfc = readFileSync(new URL('../../src/integrations/CnProjectsWidget.vue', import.meta.url), 'utf8')
		expect(sfc).toContain("isCaseHost(this.host)) {\n\t\t\t\treturn t('planninq', 'No projects for this case yet')")
	})
})
