/**
 * Read a single-file component without mounting it: its options object, and
 * fingerprints of its compiled template and its styles.
 *
 * The script's imports are left unresolved (each imported name is undefined),
 * so only options that do not call an import at evaluation time can be read.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { compileTemplate, parse } from '@vue/compiler-sfc'
import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'

/**
 * The names an import statement binds.
 *
 * @param {string} statement - one import statement
 * @return {Array<string>} the local names
 */
function importedNames(statement) {
	const clause = statement.replace(/^import\s+/, '').replace(/\s+from\s+['"][^'"]+['"]\s*;?$/, '')
	return clause
		.replace(/[{}]/g, ',')
		.split(',')
		.map((part) => part.trim().split(/\s+as\s+/).pop().replace(/^\*\s*/, '').trim())
		.filter((name) => /^[A-Za-z_$][\w$]*$/.test(name))
}

/**
 * Load a component file.
 *
 * @param {URL} url - the .vue file
 * @return {{options: object, templateHash: string, styleHash: string}} the component
 */
export function loadSfc(url) {
	const source = readFileSync(url, 'utf8')
	const { descriptor } = parse(source, { filename: url.pathname })
	const imports = descriptor.script.content.match(/^import\s[\s\S]*?from\s+['"][^'"]+['"]\s*;?$/gm) || []
	const names = imports.flatMap(importedNames)
	const body = imports.reduce((code, statement) => code.replace(statement, ''), descriptor.script.content)
		.replace(/export default/, 'return')
	const options = new Function(...names, body)()
	const template = compileTemplate({ source: descriptor.template.content, filename: url.pathname, id: 'golden' })
	const sha = (text) => createHash('sha256').update(text).digest('hex')
	return {
		options,
		templateHash: sha(template.code),
		styleHash: sha(descriptor.styles.map((style) => `${style.scoped}|${style.content}`).join('\n')),
	}
}
