/**
 * The admin settings page renders inside the shared CnAdminSettingsShell
 * (adopt-cnapproot-manifest-shell, task 3.1).
 *
 * @spec openspec/changes/adopt-cnapproot-manifest-shell/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { parse } from '@vue/compiler-sfc'
import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const ADMIN_ROOT = new URL('../../src/views/settings/AdminRoot.vue', import.meta.url)
const SHELL = { name: 'CnAdminSettingsShell' }
const SETTINGS = { name: 'Settings' }

/**
 * The root element of the component's template.
 *
 * @return {object} the template AST node
 */
function rootElement() {
	const { descriptor } = parse(readFileSync(ADMIN_ROOT, 'utf8'))
	const elements = descriptor.template.ast.children.filter((node) => node.type === 1)
	expect(elements).toHaveLength(1)
	return elements[0]
}

/**
 * The static attributes and directives of a template element, by name.
 *
 * @param {object} node - the element
 * @return {object} name to value (an expression's source for a directive)
 */
function attributesOf(node) {
	return Object.fromEntries(node.props.map((prop) => (prop.type === 6
		? [prop.name, prop.value?.content]
		: [`v-${prop.name}`, prop.exp?.content])))
}

describe('AdminRoot', () => {
	it('uses the library shell and planninq\'s own Settings form', async () => {
		const initializeStores = vi.fn(async () => undefined)
		const { options } = await loadSfcWithImports(ADMIN_ROOT, {
			modules: {
				'@conduction/nextcloud-vue': { CnAdminSettingsShell: SHELL },
				'./Settings.vue': { default: SETTINGS },
				'../../store/store.js': { initializeStores },
			},
		})

		expect(options.name).toBe('AdminRoot')
		expect(options.components).toEqual({ CnAdminSettingsShell: SHELL, Settings: SETTINGS })
	})

	it('renders the shell for planninq with the form in its default slot once the stores are ready', () => {
		const root = rootElement()
		expect(root.tag).toBe('CnAdminSettingsShell')
		expect(attributesOf(root)).toEqual({ appId: 'planninq', appName: 'Planninq' })

		const children = root.children.filter((node) => node.type === 1)
		expect(children.map((node) => node.tag)).toEqual(['Settings'])
		expect(attributesOf(children[0])).toEqual({ 'v-if': 'storesReady' })
	})

	it('shows the form only after the stores are initialised', async () => {
		let release
		const initializeStores = vi.fn(() => new Promise((resolve) => {
			release = resolve
		}))
		const { options } = await loadSfcWithImports(ADMIN_ROOT, {
			modules: {
				'@conduction/nextcloud-vue': { CnAdminSettingsShell: SHELL },
				'./Settings.vue': { default: SETTINGS },
				'../../store/store.js': { initializeStores },
			},
		})
		const vm = options.data()
		expect(vm.storesReady).toBe(false)

		const created = options.created.call(vm)
		expect(initializeStores).toHaveBeenCalledOnce()
		expect(vm.storesReady).toBe(false)

		release()
		await created
		expect(vm.storesReady).toBe(true)
	})
})
