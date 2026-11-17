/**
 * The component registry CnAppRoot resolves manifest pages against: every
 * `type:"custom"` page of the bundled manifest names a page entry, and the
 * timetable components are slot entries. The view components are replaced by
 * named stand-ins; the registry and the manifest are the real ones. Every
 * kind must be one the installed CnAppRoot validator accepts: an unknown kind
 * throws at mount and skips the menu count hydration after it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md
 */
import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'

vi.mock('../../src/components/TimetableScenarioCompare.vue', () => ({ default: { name: 'TimetableScenarioCompare' } }))
vi.mock('../../src/components/TimetableScenarioSections.vue', () => ({ default: { name: 'TimetableScenarioSections' } }))
vi.mock('../../src/dialogs/TimetableWishDialog.vue', () => ({ default: { name: 'TimetableWishDialog' } }))
vi.mock('../../src/views/Boards.vue', () => ({ default: { name: 'Boards' } }))
vi.mock('../../src/views/MyCalendar.vue', () => ({ default: { name: 'MyCalendar' } }))
vi.mock('../../src/views/MyReports.vue', () => ({ default: { name: 'MyReports' } }))
vi.mock('../../src/views/MyWork.vue', () => ({ default: { name: 'MyWork' } }))
vi.mock('../../src/views/Portfolio.vue', () => ({ default: { name: 'Portfolio' } }))
vi.mock('../../src/views/PortfolioFinance.vue', () => ({ default: { name: 'PortfolioFinance' } }))
vi.mock('../../src/views/PortfolioFlow.vue', () => ({ default: { name: 'PortfolioFlow' } }))
vi.mock('../../src/views/PortfolioStatus.vue', () => ({ default: { name: 'PortfolioStatus' } }))
vi.mock('../../src/views/PortfolioTimeline.vue', () => ({ default: { name: 'PortfolioTimeline' } }))
vi.mock('../../src/views/ProjectBacklog.vue', () => ({ default: { name: 'ProjectBacklog' } }))
vi.mock('../../src/views/ProjectBoard.vue', () => ({ default: { name: 'ProjectBoard' } }))
vi.mock('../../src/views/ProjectCalendar.vue', () => ({ default: { name: 'ProjectCalendar' } }))
vi.mock('../../src/views/ProjectFinance.vue', () => ({ default: { name: 'ProjectFinance' } }))
vi.mock('../../src/views/ProjectFlow.vue', () => ({ default: { name: 'ProjectFlow' } }))
vi.mock('../../src/views/ProjectList.vue', () => ({ default: { name: 'ProjectList' } }))
vi.mock('../../src/views/ProjectLog.vue', () => ({ default: { name: 'ProjectLog' } }))
vi.mock('../../src/views/ProjectOverview.vue', () => ({ default: { name: 'ProjectOverview' } }))
vi.mock('../../src/views/ProjectPhases.vue', () => ({ default: { name: 'ProjectPhases' } }))
vi.mock('../../src/views/ProjectRisks.vue', () => ({ default: { name: 'ProjectRisks' } }))
vi.mock('../../src/views/ProjectStatus.vue', () => ({ default: { name: 'ProjectStatus' } }))
vi.mock('../../src/views/ProjectsView.vue', () => ({ default: { name: 'ProjectsView' } }))
vi.mock('../../src/views/ProjectTimeline.vue', () => ({ default: { name: 'ProjectTimeline' } }))
vi.mock('../../src/views/ProjectWiki.vue', () => ({ default: { name: 'ProjectWiki' } }))
vi.mock('../../src/views/ReportPage.vue', () => ({ default: { name: 'ReportPage' } }))
vi.mock('../../src/views/TaskDetail.vue', () => ({ default: { name: 'TaskDetail' } }))
vi.mock('../../src/views/Timesheet.vue', () => ({ default: { name: 'Timesheet' } }))

const { default: registry } = await import('../../src/registry.js')
const manifest = JSON.parse(readFileSync(new URL('../../src/manifest.json', import.meta.url), 'utf8'))

const SLOTS = ['TimetableScenarioCompare', 'TimetableScenarioSections', 'TimetableWishDialog']

/**
 * The registry kinds the installed CnAppRoot accepts, read from its source.
 *
 * @return {Array<string>}
 */
function acceptedKinds() {
	const source = readFileSync(new URL('../../node_modules/@conduction/nextcloud-vue/src/components/CnAppRoot/CnAppRoot.vue', import.meta.url), 'utf8')
	const block = source.match(/const REGISTRY_KIND_REQUIRED_FIELDS = \{([\s\S]*?)\n\}/)[1]
	return [...block.matchAll(/^\t'?([a-z-]+)'?:/gm)].map((match) => match[1])
}

describe('component registry', () => {
	it('resolves every custom manifest page to a page entry of the same name', () => {
		const custom = manifest.pages.filter((page) => page.type === 'custom')
		expect(custom.length).toBeGreaterThan(0)
		for (const page of custom) {
			expect(registry[page.component], page.id).toEqual({ kind: 'page', component: { name: page.component } })
		}
	})

	it('tags the timetable components as slots and everything else as pages', () => {
		for (const [name, entry] of Object.entries(registry)) {
			expect(entry.component).toEqual({ name })
			expect(entry.kind, name).toBe(SLOTS.includes(name) ? 'section' : 'page')
		}
		expect(Object.keys(registry)).toHaveLength(29)
	})

	it('uses only kinds the CnAppRoot validator accepts', () => {
		const kinds = acceptedKinds()
		expect(kinds).toContain('page')
		for (const [name, entry] of Object.entries(registry)) {
			expect(kinds, name).toContain(entry.kind)
		}
	})
})
