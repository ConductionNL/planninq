/**
 * The money columns PortfolioFinance and ProjectFinance show, in order, with
 * their translated labels.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
 */
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const EXPECTED = [
	{ id: 'budget', label: '[planninq] Budget' },
	{ id: 'commitment', label: '[planninq] Commitments' },
	{ id: 'actual', label: '[planninq] Actual cost' },
	{ id: 'forecast', label: '[planninq] Forecast' },
	{ id: 'remaining', label: '[planninq] Remaining' },
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

describe('finance columns', () => {
	for (const view of ['PortfolioFinance', 'ProjectFinance']) {
		it(`${view} shows budget, commitments, actual, forecast and remaining`, async () => {
			const { options } = await loadSfcWithImports(new URL(`../../src/views/${view}.vue`, import.meta.url))
			expect(options.computed.columns.call({ t })).toEqual(EXPECTED)
		}, 60000)
	}
})
