/**
 * The six status aspects StatusReportDialog and ProjectStatus show, in order,
 * with their translated labels.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const LABELS = [
	['money', 'Money'],
	['organisation', 'Organisation'],
	['time', 'Time'],
	['information', 'Information'],
	['quality', 'Quality'],
	['risk', 'Risk'],
]

/**
 * A stand-in translation that shows the app and the source string.
 *
 * @param {string} app - the app id
 * @param {string} text - the source string
 * @return {string} the marked string
 */
function t(app, text) {
	return `[${app}] ${text}`
}

/**
 * The computed aspects of one component.
 *
 * @param {string} file - the component path under src/
 * @return {Promise<Array<object>>} the aspects
 */
async function aspectsOf(file) {
	const { options } = await loadSfcWithImports(new URL(`../../src/${file}.vue`, import.meta.url))
	return options.computed.aspects.call({ t })
}

describe('status aspects', () => {
	it('StatusReportDialog labels each aspect', async () => {
		expect(await aspectsOf('dialogs/StatusReportDialog')).toEqual(LABELS.map(([id, label]) => ({ id, label: `[planninq] ${label}` })))
	}, 60000)

	it('ProjectStatus labels each aspect and gives its field key', async () => {
		const keys = ['Money', 'Organisation', 'Time', 'Information', 'Quality', 'Risk']
		expect(await aspectsOf('views/ProjectStatus')).toEqual(LABELS.map(([id, label], i) => ({ id, key: keys[i], label: `[planninq] ${label}` })))
	}, 60000)
})
