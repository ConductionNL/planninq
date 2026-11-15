/**
 * Tests for the OpenRegister projects-leaf registration: the descriptor it
 * enqueues and the way it bootstraps the integration registry on a host global.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ translate: (app, text) => text }))
vi.mock('../../src/integrations/CnProjectsWidget.vue', () => ({ default: { name: 'CnProjectsWidget' } }))
vi.mock('vue', () => ({ createApp: () => ({ config: { globalProperties: {} }, mount() {}, unmount() {} }) }))

const { PROJECTS_INTEGRATION_ID, projectsLeafDescriptor, registerProjectsLeaf } = await import('../../src/integrations/registerProjectsLeaf.js')

describe('projectsLeafDescriptor', () => {
	it('describes the planninq projects leaf for OpenRegister', () => {
		expect(projectsLeafDescriptor).toMatchObject({
			id: PROJECTS_INTEGRATION_ID,
			label: 'Projects',
			requiredApp: 'planninq',
			referenceType: 'planninq-projects',
			renderMode: 'mount',
			surfaces: ['user-dashboard', 'app-dashboard', 'detail-page', 'single-entity'],
		})
		expect(typeof projectsLeafDescriptor.mount).toBe('function')
		expect(typeof projectsLeafDescriptor.unmount).toBe('function')
	})
})

describe('registerProjectsLeaf', () => {
	it('creates the integration registry and enqueues the descriptor', () => {
		const target = {}

		registerProjectsLeaf(target)

		expect(target.OCA.OpenRegister.integrations._queue).toEqual([projectsLeafDescriptor])
	})

	it('keeps an existing registry and appends through its register()', () => {
		const register = vi.fn()
		const integrations = { register, existing: true }
		const target = { OCA: { OpenRegister: { integrations } } }

		registerProjectsLeaf(target)

		expect(target.OCA.OpenRegister.integrations).toBe(integrations)
		expect(register).toHaveBeenCalledWith(projectsLeafDescriptor)
	})

	it('preserves a pre-existing OCA namespace', () => {
		const target = { OCA: { Viewer: 'keep' } }

		registerProjectsLeaf(target)

		expect(target.OCA.Viewer).toBe('keep')
		expect(target.OCA.OpenRegister.integrations._queue).toHaveLength(1)
	})

	it('does nothing when there is no global to register on', () => {
		expect(() => registerProjectsLeaf(null)).not.toThrow()
	})

	it('falls back to window when no global is passed', () => {
		const original = globalThis.window
		const win = {}
		globalThis.window = win
		try {
			registerProjectsLeaf()
			expect(win.OCA.OpenRegister.integrations._queue).toEqual([projectsLeafDescriptor])
		} finally {
			if (original === undefined) {
				delete globalThis.window
			} else {
				globalThis.window = original
			}
		}
	})
})
