/**
 * Golden test for the declared interface of the edit dialogs: per dialog,
 * every prop with its type, required flag and default, and every event it
 * declares, with the options of its mixins merged in the way Vue merges
 * them. Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const FIXTURE = path.join(__dirname, 'fixtures', 'dialogOptions.golden.json')
const DIALOGS = fs.readdirSync(path.join(__dirname, '../../src/dialogs')).filter((file) => file.endsWith('.vue')).sort()

/**
 * The options of a component and its mixins, mixins first.
 *
 * @param {object} options - the component options
 * @return {Array<object>} the option sources
 */
function sources(options) {
	return [...(options.mixins || []).flatMap(sources), options]
}

/**
 * A prop definition as plain data.
 *
 * @param {object|Function|Array} definition - the prop definition
 * @return {object} its type names, required flag and default
 */
function describeProp(definition) {
	const full = typeof definition === 'object' && !Array.isArray(definition) ? definition : { type: definition }
	const types = [].concat(full.type || []).map((type) => type.name)
	const fallback = typeof full.default === 'function' && !types.includes('Function') ? full.default() : full.default
	return { types, required: full.required === true, default: fallback === undefined ? '<none>' : fallback }
}

/**
 * The merged props and emits of one dialog.
 *
 * @param {object} options - the component options
 * @return {object} the props by name and the sorted event names
 */
function record(options) {
	const props = {}
	const emits = new Set()
	for (const source of sources(options)) {
		for (const [name, definition] of Object.entries(source.props || {})) {
			props[name] = describeProp(definition)
		}
		for (const name of [].concat(source.emits || [])) {
			emits.add(name)
		}
	}
	return { props: Object.fromEntries(Object.entries(props).sort()), emits: [...emits].sort() }
}

describe('dialog options golden', () => {
	it('matches the recorded fixture', async () => {
		const actual = {}
		for (const file of DIALOGS) {
			const { options } = await loadSfcWithImports(new URL(`../../src/dialogs/${file}`, import.meta.url))
			actual[file] = record(options)
		}
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	}, 60000)
})
