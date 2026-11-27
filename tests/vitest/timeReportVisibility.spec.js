/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Time spent report's humaniq widgets show when humaniq is enabled and
 * hide when it is not, as nextcloud-vue's dashboard evaluates them.
 *
 * CnDashboardPage hands only a composition (`all`/`any`) to
 * evaluateVisibleWhen(), which knows `appInstalled`; any other condition goes
 * to readVisibleWhenValue(), which does not, so a bare `{ appInstalled }` hid
 * the widgets on every instance.
 *
 * @spec openspec/changes/plannedtimeentry-reads-humaniqs-hours/specs/time-hours-owner/spec.md#requirement-time-widgets-hide-when-humaniq-is-absent
 */
import { afterEach, describe, expect, it, vi } from 'vitest'
import manifest from '../../src/manifest.json'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'anna' }) }))
vi.mock('@nextcloud/capabilities', () => ({ getCapabilities: () => ({}) }))

const { __resetAppInstalledCacheForTests } = await import('@conduction/nextcloud-vue/src/utils/appInstalled.js')
const { evaluateVisibleWhen, isComposition } = await import('@conduction/nextcloud-vue/src/utils/visibleWhen.js')

const page = manifest.pages.find((p) => p.id === 'TimeSpentReport')
const humaniqWidgets = page.config.widgets.filter((w) => w.requiredApp === 'humaniq')

/**
 * Evaluate a widget's condition with these apps enabled for the person.
 *
 * @param {object} widget The widget definition.
 * @param {string[]} apps The enabled app ids.
 * @return {Promise<boolean>}
 */
async function shownWith(widget, apps) {
	__resetAppInstalledCacheForTests()
	globalThis.OC = { appswebroots: Object.fromEntries(apps.map((app) => [app, `/apps/${app}`])) }
	return evaluateVisibleWhen(widget.visibleWhen)
}

describe('Time spent report widgets that read humaniq', () => {
	afterEach(() => {
		delete globalThis.OC
		__resetAppInstalledCacheForTests()
	})

	it('are the five that read humaniq', () => {
		expect(humaniqWidgets.map((w) => w.id)).toEqual(['time-entries', 'time-minutes', 'time-by-user', 'time-by-project', 'time-recent'])
	})

	it('state their condition as a composition, the form the dashboard evaluates', () => {
		for (const widget of humaniqWidgets) {
			expect(isComposition(widget.visibleWhen), widget.id).toBe(true)
		}
	})

	it('show when humaniq is enabled', async () => {
		for (const widget of humaniqWidgets) {
			expect(await shownWith(widget, ['planninq', 'humaniq']), widget.id).toBe(true)
		}
	})

	it('hide when humaniq is not enabled', async () => {
		for (const widget of humaniqWidgets) {
			expect(await shownWith(widget, ['planninq']), widget.id).toBe(false)
		}
	})
})
