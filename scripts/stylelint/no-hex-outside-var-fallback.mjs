/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Stylelint rule `planninq/no-hex-outside-var-fallback`: a literal hex
 * colour is allowed only as the fallback of a `var()`, so every colour the
 * app paints follows the Nextcloud theme (and thematiq) instead of a value
 * fixed in the source. `color: var(--color-primary-element, #0082c9)` passes;
 * `color: #0082c9` and `--accent: #0082c9` are reported.
 *
 * Built in instead of stylelint's own `color-no-hex`, which also reports the
 * hex inside a `var()` fallback and so cannot express the allowed case.
 *
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#REQ-PLB-002
 */
import stylelint from 'stylelint'

const {
	createPlugin,
	utils: { report, ruleMessages, validateOptions },
} = stylelint

export const ruleName = 'planninq/no-hex-outside-var-fallback'

export const messages = ruleMessages(ruleName, {
	rejected: (hex) => `Unexpected hex colour "${hex}": use a theme variable, with a literal colour only as its var() fallback`,
})

const HEX = /#[0-9a-fA-F]{3,8}\b/g

/**
 * A declaration value with every `var(...)` call, nested ones included,
 * blanked out to spaces, so whatever a hex colour is left in it is outside
 * a var() fallback. Quoted strings are blanked too: a `#` there is text.
 *
 * @param {string} value The declaration value.
 * @return {string} The value with var() calls and strings replaced by spaces, same length.
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#REQ-PLB-002
 */
export function outsideVarFallbacks(value) {
	const out = value.split('')
	let depth = 0
	let quote = null
	for (let i = 0; i < value.length; i++) {
		const ch = value[i]
		if (quote !== null) {
			out[i] = ' '
			if (ch === quote && value[i - 1] !== '\\') {
				quote = null
			}
			continue
		}

		if (ch === '"' || ch === '\'') {
			quote = ch
			out[i] = ' '
			continue
		}

		if (depth === 0 && value.slice(i, i + 4).toLowerCase() === 'var(') {
			depth = 1
			for (let k = i; k < i + 4; k++) {
				out[k] = ' '
			}
			i += 3
			continue
		}

		if (depth > 0) {
			if (ch === '(') {
				depth++
			} else if (ch === ')') {
				depth--
			}
			out[i] = ' '
		}
	}

	return out.join('')
}

/**
 * The rule.
 *
 * @param {boolean} primary `true` to enable.
 * @return {(root: object, result: object) => void} The PostCSS walker.
 * @spec openspec/changes/platform-baseline/specs/platform-baseline/spec.md#REQ-PLB-002
 */
function ruleFunction(primary) {
	return (root, result) => {
		if (!validateOptions(result, ruleName, { actual: primary, possible: [true] })) {
			return
		}

		walk(root, result)
	}
}

/**
 * Report every hex colour outside a var() fallback in a stylesheet.
 *
 * @param {object} root The PostCSS root.
 * @param {object} result The stylelint result.
 * @return {void}
 */
function walk(root, result) {
	root.walkDecls((decl) => {
		const value = outsideVarFallbacks(decl.value)
		for (const match of value.matchAll(HEX)) {
			report({
				ruleName,
				result,
				node: decl,
				message: messages.rejected(match[0]),
				word: match[0],
			})
		}
	})
}

ruleFunction.ruleName = ruleName
ruleFunction.messages = messages

export default createPlugin(ruleName, ruleFunction)
