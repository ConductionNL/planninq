/**
 * CnProjectsWidget's load: the OpenRegister read it makes for its surface,
 * the rows it keeps from each answer shape, and what it shows after a failed
 * read.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/project-delivery/spec.md#requirement-the-leaf-answers-the-question-its-surface-asked-v1
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { loadSfcWithImports } from './support/sfc.js'

const get = vi.fn()

const { options } = await loadSfcWithImports(new URL('../../src/integrations/CnProjectsWidget.vue', import.meta.url), {
	modules: {
		'@nextcloud/axios': { default: { get: (...args) => get(...args) } },
		'@nextcloud/router': { generateUrl: (path, params) => `${path}|${JSON.stringify(params || {})}` },
		'@nextcloud/l10n': { translate: (app, text) => `[${app}] ${text}` },
	},
})

/**
 * Run load on a stand-in widget and return its settled state.
 *
 * @param {object} props - surface, objectId and host
 * @return {Promise<object>} the state after load
 */
async function runLoad(props) {
	const vm = { ...options.methods, ...props, loading: false, error: 'old', projects: ['old'], total: 9 }
	const seen = []
	const pending = options.methods.load.call(new Proxy(vm, {
		set(target, key, value) {
			seen.push(key)
			target[key] = value
			return true
		},
	}))
	seen.push(`loading=${vm.loading}`)
	await pending
	return { state: { loading: vm.loading, error: vm.error, projects: vm.projects, total: vm.total }, seen }
}

describe('CnProjectsWidget load', () => {
	beforeEach(() => {
		get.mockReset()
	})

	it('reads one project by id on a single-entity surface and keeps the results', async () => {
		get.mockResolvedValueOnce({ data: { results: [{ id: 'p1', title: 'A' }, { id: 'p2' }] } })
		const result = await runLoad({ surface: 'single-entity', objectId: 'p1', host: {} })
		expect(get.mock.calls).toMatchSnapshot()
		expect(result).toMatchSnapshot()
	})

	it('reads a client\'s projects and keeps a plain list answer', async () => {
		get.mockResolvedValueOnce({ data: [{ id: 'p3', client: 'c1' }] })
		const result = await runLoad({ surface: 'list', objectId: 'c1', host: {} })
		expect(get.mock.calls).toMatchSnapshot()
		expect(result).toMatchSnapshot()
	})

	it('keeps no rows from an answer without a list', async () => {
		get.mockResolvedValueOnce({ data: { total: 5 } })
		expect(await runLoad({ surface: 'list', objectId: '', host: {} })).toMatchSnapshot()
	})

	it('says it could not load after a failed read', async () => {
		get.mockRejectedValueOnce(new Error('down'))
		expect(await runLoad({ surface: 'list', objectId: '', host: {} })).toMatchSnapshot()
	})
})
