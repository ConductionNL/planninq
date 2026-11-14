/**
 * Every literal string the frontend translates has an entry in the English
 * catalogue, so the parity gate (tests/l10n/check-l10n-parity.js) sees it and
 * every locale must carry it. Without this, a string used in src/ but missing
 * from l10n/en.json passes every gate and shows in English in every language.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/fix-l10n-parity-gate/tasks.md#task-5.1
 */
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

const ROOT = join(__dirname, '..', '..')

/**
 * Every .vue and .js file under a directory.
 *
 * @param {string} dir The directory.
 * @return {Array<string>}
 */
function sources(dir) {
	return readdirSync(dir).flatMap((name) => {
		const path = join(dir, name)
		if (statSync(path).isDirectory()) {
			return sources(path)
		}
		return /\.(vue|js)$/.test(name) ? [path] : []
	})
}

/**
 * The literal first argument of every t('planninq', ...) call, with JS escapes decoded.
 *
 * @param {string} text The source text.
 * @return {Array<string>}
 */
function literals(text) {
	const found = []
	const pattern = /\bt\(\s*'planninq'\s*,\s*(?:'((?:[^'\\\n]|\\.)*)'|"((?:[^"\\\n]|\\.)*)")/g
	for (const match of text.matchAll(pattern)) {
		const raw = match[1] ?? match[2]
		found.push(JSON.parse(`"${raw.replace(/\\'/g, '\'').replace(/"/g, '\\"')}"`))
	}
	return found
}

describe('l10n source coverage', () => {
	it('finds the strings it scans for (control)', () => {
		expect(literals("t('planninq', 'Due: {date}') this.t('planninq', 'Search\\u2026')")).toEqual(['Due: {date}', 'Search…'])
	})

	it('has an English catalogue entry for every translated literal in src/', () => {
		const english = JSON.parse(readFileSync(join(ROOT, 'l10n', 'en.json'), 'utf8')).translations
		const missing = new Set()
		for (const file of sources(join(ROOT, 'src'))) {
			for (const key of literals(readFileSync(file, 'utf8'))) {
				if (!(key in english)) {
					missing.add(`${file.slice(ROOT.length + 1)}: ${key}`)
				}
			}
		}
		expect([...missing]).toEqual([])
	})
})
