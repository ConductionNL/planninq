/**
 * The stylelint rules that keep platform-baseline true: a literal hex colour
 * only as a var() fallback, and logical instead of physical left/right
 * properties (platform-baseline task 2.4).
 *
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#REQ-PLB-002
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#REQ-PLB-003
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { fileURLToPath } from 'node:url'
import stylelint from 'stylelint'
import { describe, expect, it } from 'vitest'
import { outsideVarFallbacks } from '../../scripts/stylelint/no-hex-outside-var-fallback.mjs'

const CONFIG = fileURLToPath(new URL('../../stylelint.config.js', import.meta.url))

/**
 * The rule ids and severities the app's stylelint config reports for a stylesheet.
 *
 * @param {string} code The CSS.
 * @return {Promise<Array<{rule: string, severity: string, text: string}>>}
 */
async function problems(code) {
	const { results } = await stylelint.lint({ code, configFile: CONFIG, codeFilename: 'src/x.css' })
	return results[0].warnings
		.filter((w) => ['planninq/no-hex-outside-var-fallback', 'csstools/use-logical'].includes(w.rule))
		.map((w) => ({ rule: w.rule, severity: w.severity, text: w.text }))
}

describe('planninq/no-hex-outside-var-fallback', () => {
	it('reports a literal hex colour and a custom property defined as one', async () => {
		const found = await problems('.a {\n\tcolor: #0082c9;\n\t--accent: #FFF;\n}\n')

		expect(found.map((p) => p.rule)).toEqual(['planninq/no-hex-outside-var-fallback', 'planninq/no-hex-outside-var-fallback'])
		expect(found.every((p) => p.severity === 'error')).toBe(true)
		expect(found[0].text).toContain('"#0082c9"')
		expect(found[1].text).toContain('"#FFF"')
	})

	it('allows a hex colour as a var() fallback, also nested, and as text in a string', async () => {
		const found = await problems('.a {\n\tcolor: var(--color-primary-element, #0082c9);\n\tborder-color: var(--x, var(--y, #000));\n\tcontent: "#abc";\n}\n')

		expect(found).toEqual([])
	})

	it('still reports a hex colour beside a var() call', async () => {
		const found = await problems('.a {\n\tborder: 1px solid var(--color-border, #ddd) #123456;\n}\n')

		expect(found.map((p) => p.text)).toEqual([expect.stringContaining('"#123456"')])
	})

	it('blanks var() calls and strings to spaces, keeping the length', () => {
		const value = 'var(--a, #fff) #000 "#111"'
		const blanked = outsideVarFallbacks(value)

		expect(blanked).toHaveLength(value.length)
		expect(blanked.trim()).toBe('#000')
	})
})

describe('csstools/use-logical', () => {
	it('reports physical left and right properties as errors', async () => {
		const found = await problems('.a {\n\tmargin-left: 4px;\n\tpadding-right: 2px;\n\tleft: 0;\n\ttop: 0;\n}\n')

		expect(found.map((p) => p.text).sort()).toEqual([
			expect.stringContaining('"left"'),
			expect.stringContaining('"margin-left"'),
			expect.stringContaining('"padding-right"'),
		])
		expect(found.every((p) => p.severity === 'error')).toBe(true)
	})

	it('allows the logical forms', async () => {
		const found = await problems('.a {\n\tmargin-inline-start: 4px;\n\tpadding-inline-end: 2px;\n\tinset-inline-start: 0;\n}\n')

		expect(found).toEqual([])
	})
})
