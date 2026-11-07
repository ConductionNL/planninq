/**
 * Golden test for scripts/check-schema-l10n.js.
 *
 * Runs the script as a CLI on a copy inside a temporary repo layout, against
 * synthetic schemas, and compares
 * stdout, stderr, the exit code and the written baseline with the fixture.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const SCRIPT = path.join(ROOT, 'scripts', 'check-schema-l10n.js')
const FIXTURE = path.join(__dirname, 'fixtures', 'checkSchemaL10n.golden.json')

const SCHEMAS = {
	'register.json': {
		title: 'Register',
		components: {
			schemas: {
				task: {
					title: 'Task',
					properties: {
						name: { title: 'Name', description: 'The task name' },
						status: {
							title: 'Status',
							'x-enum-labels': { open: 'Open', done: 'Done', blank: ' ', number: 3 },
						},
						tags: { title: 'Tags', items: { 'x-enum-labels': { a: 'Tag A', b: 'Open' } } },
						broken: null,
						scalar: 'not an object',
						noLabels: { title: '', description: 7, items: null, 'x-enum-labels': null },
						arrayLabels: { 'x-enum-labels': ['First label', 'Second label'] },
					},
				},
				list: [
					{ title: 'In array', properties: { inner: { title: 'Inner title' } } },
					{ title: 'No properties here' },
					null,
				],
				arrayProps: { title: 'Array properties', properties: [{ title: 'Ignored' }] },
				nullProps: { title: 'Null properties', properties: null },
			},
		},
	},
	'register.d/nested.json': {
		title: 'Nested schema',
		properties: {
			name: { title: 'Name', description: 'Second sighting' },
			deep: { title: 'Deep', properties: { leaf: { title: 'Leaf', 'x-enum-labels': { x: 'Leaf X' } } } },
		},
	},
	'register.d/also.json': [{ title: 'Top array', properties: { only: { description: 'Only description' } } }],
	'register.d/notes.txt': 'not json at all',
	'broken.json': '{ not valid json',
}

/**
 * Lay out a temporary repo holding a copy of the script and the given files.
 *
 * @param {object} layout - relative path -> JSON value or raw text
 * @return {string} the temporary repo root
 */
function makeRepo(layout) {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'schema-l10n-'))
	fs.mkdirSync(path.join(dir, 'scripts'))
	fs.copyFileSync(SCRIPT, path.join(dir, 'scripts', 'check-schema-l10n.js'))
	for (const [rel, value] of Object.entries(layout)) {
		const file = path.join(dir, rel)
		fs.mkdirSync(path.dirname(file), { recursive: true })
		fs.writeFileSync(file, typeof value === 'string' ? value : JSON.stringify(value))
	}
	return dir
}

/**
 * Run the script copy in a repo and describe what it did.
 *
 * @param {string} dir - repo root
 * @param {string[]} args - CLI arguments
 * @return {object} status, stdout, stderr and the baseline file afterwards
 */
function run(dir, args) {
	const result = spawnSync(process.execPath, [path.join(dir, 'scripts', 'check-schema-l10n.js'), ...args], { encoding: 'utf8' })
	const baseline = path.join(dir, 'l10n', '.schema-l10n-baseline.json')
	return {
		status: result.status,
		stdout: result.stdout,
		stderr: result.stderr,
		baseline: fs.existsSync(baseline) ? fs.readFileSync(baseline, 'utf8') : null,
	}
}

/**
 * Prefix every schema path with lib/Settings/.
 *
 * @param {object} files - path under lib/Settings -> content
 * @return {object} repo-relative path -> content
 */
function underSettings(files) {
	return Object.fromEntries(Object.entries(files).map(([rel, value]) => [`lib/Settings/${rel}`, value]))
}

const CATALOGUE = { 'l10n/en.json': { translations: { Name: 'Name', Open: 'Open', 'Leaf X': 'Leaf X' } } }

/**
 * Every recorded case.
 *
 * @return {object} case name -> result
 */
function recordAll() {
	const settings = underSettings(SCHEMAS)
	const cases = {}
	cases.list = run(makeRepo({ ...settings, ...CATALOGUE, 'l10n/.schema-l10n-baseline.json': { uncovered: 20 } }), ['--list'])
	cases.atBaseline = run(makeRepo({ ...settings, ...CATALOGUE, 'l10n/.schema-l10n-baseline.json': { uncovered: 16 } }), [])
	cases.belowBaseline = run(makeRepo({ ...settings, ...CATALOGUE, 'l10n/.schema-l10n-baseline.json': { uncovered: 40 } }), [])
	cases.aboveBaseline = run(makeRepo({ ...settings, ...CATALOGUE, 'l10n/.schema-l10n-baseline.json': { uncovered: 2 } }), [])
	cases.noBaseline = run(makeRepo({ ...settings, ...CATALOGUE }), [])
	cases.noCatalogue = run(makeRepo({ ...settings, 'l10n/.schema-l10n-baseline.json': { uncovered: 1 } }), [])
	cases.update = run(makeRepo({ ...settings, ...CATALOGUE }), ['--update'])
	cases.noSettingsDir = run(makeRepo({ 'l10n/.schema-l10n-baseline.json': { uncovered: 0 } }), [])
	cases.noTranslationsKey = run(makeRepo({ ...settings, 'l10n/en.json': { other: {} }, 'l10n/.schema-l10n-baseline.json': { uncovered: 18 } }), [])
	cases.listAndUpdate = run(makeRepo({ ...settings, ...CATALOGUE }), ['--list', '--update'])
	return cases
}

describe('check-schema-l10n golden', () => {
	it('matches the recording', () => {
		const actual = JSON.stringify(recordAll(), null, 2) + '\n'
		if (process.env.PLANNINQ_GOLDEN_RECORD === '1') {
			fs.mkdirSync(path.dirname(FIXTURE), { recursive: true })
			fs.writeFileSync(FIXTURE, actual)
		}
		expect(actual).toBe(fs.readFileSync(FIXTURE, 'utf8'))
	})
})
