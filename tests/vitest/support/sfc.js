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
 * What an import statement binds: each local name and the export it reads.
 *
 * @param {string} statement - one import statement
 * @return {Array<{local: string, imported: string}>} the bindings; imported is 'default' or '*' for those forms
 */
function bindings(statement) {
	const clause = statement.replace(/^import\s+/, '').replace(/\s+from\s+['"][^'"]+['"]\s*;?$/, '')
	const named = clause.match(/\{([^}]*)\}/)?.[1] ?? ''
	const outside = clause.replace(/\{[^}]*\}/, '').split(',').map((part) => part.trim()).filter(Boolean)
	const result = outside.map((part) => (part.startsWith('*')
		? { local: part.split(/\s+as\s+/).pop().trim(), imported: '*' }
		: { local: part, imported: 'default' }))
	for (const part of named.split(',').map((one) => one.trim()).filter(Boolean)) {
		const [imported, local = imported] = part.split(/\s+as\s+/).map((one) => one.trim())
		result.push({ local, imported })
	}
	return result
}

/**
 * Parse a component file into its script parts and fingerprints.
 *
 * @param {URL} url - the .vue file
 * @return {{imports: Array<string>, body: string, template: string, templateHash: string, styleHash: string}} the parts
 */
function readSfc(url) {
	const { descriptor } = parse(readFileSync(url, 'utf8'), { filename: url.pathname })
	const imports = descriptor.script.content.match(/^import\s[\s\S]*?from\s+['"][^'"]+['"]\s*;?$/gm) || []
	const body = imports.reduce((code, statement) => code.replace(statement, ''), descriptor.script.content)
		.replace(/export default/, 'return')
	const template = compileTemplate({ source: descriptor.template.content, filename: url.pathname, id: 'golden' })
	const sha = (text) => createHash('sha256').update(text).digest('hex')
	return {
		imports,
		body,
		template: descriptor.template.content,
		templateHash: sha(template.code),
		styleHash: sha(descriptor.styles.map((style) => `${style.scoped}|${style.content}`).join('\n')),
	}
}

/**
 * Load a component file with every import left undefined.
 *
 * @param {URL} url - the .vue file
 * @return {{options: object, templateHash: string, styleHash: string}} the component
 */
export function loadSfc(url) {
	const { imports, body, templateHash, styleHash } = readSfc(url)
	const names = imports.flatMap((statement) => bindings(statement).map((binding) => binding.local))
	return { options: new Function(...names, body)(), templateHash, styleHash }
}

/**
 * Load the module an import statement names: the stand-in given for that
 * import path, a relative .js file, or a relative .vue file when a component
 * loader is given. Anything else is null.
 *
 * @param {URL} url - the importing file
 * @param {{from: string, given: {vue?: Function, modules?: object}}} source - the import path and the caller's loaders
 * @return {Promise<object|null>} the module namespace
 */
async function importModule(url, source) {
	const { from, given } = source
	if (given.modules && from in given.modules) {
		return given.modules[from]
	}
	if (from.startsWith('.') && from.endsWith('.vue') && given.vue) {
		return { default: await given.vue(new URL(from, url)) }
	}
	return from.startsWith('.') && from.endsWith('.js')
		? await import(new URL(from, url).href).catch(() => null)
		: null
}

/**
 * Load a component file with its relative .js imports resolved. Package
 * imports and modules that cannot load in node stay undefined, unless the
 * caller gives a stand-in module for that import path; .vue imports stay
 * undefined unless the caller gives a component loader.
 *
 * @param {URL} url - the .vue file
 * @param {{vue?: Function, modules?: object}} given - a .vue loader, and stand-in modules by import path
 * @return {Promise<{options: object, template: string, templateHash: string, styleHash: string}>} the component
 */
export async function loadSfcWithImports(url, given = {}) {
	const { imports, body, template, templateHash, styleHash } = readSfc(url)
	const scope = {}
	for (const statement of imports) {
		const from = statement.match(/from\s+['"]([^'"]+)['"]/)[1]
		const module = await importModule(url, { from, given })
		for (const { local, imported } of bindings(statement)) {
			scope[local] = module && (imported === '*' ? module : module[imported])
		}
	}
	return { options: new Function(...Object.keys(scope), body)(...Object.values(scope)), template, templateHash, styleHash }
}
