/**
 * initializeStores: the boot routine points both object stores at
 * OpenRegister's object API, the legacy one also at its schema API, and loads
 * the settings before the UI renders. The planninq object store is the
 * library's createObjectStore under the planninq id.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/retrofit-2026-05-25-app-shell-and-data-store/tasks.md#task-3
 */
import { describe, expect, it, vi } from 'vitest'

const calls = vi.hoisted(() => [])
const conductionStore = vi.hoisted(() => ({ configure: vi.fn() }))
const legacyStore = vi.hoisted(() => ({ configure: vi.fn() }))
const settingsStore = vi.hoisted(() => ({ fetchSettings: vi.fn(async () => calls.push('fetchSettings')) }))

vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => '/index.php' + path }))
vi.mock('@conduction/nextcloud-vue', () => ({
	createObjectStore: (id) => {
		calls.push(['createObjectStore', id])
		return () => conductionStore
	},
}))
vi.mock('../../src/store/modules/object.js', () => ({ useObjectStore: () => legacyStore }))
vi.mock('../../src/store/modules/settings.js', () => ({ useSettingsStore: () => settingsStore }))

const { initializeStores, useSettingsStore } = await import('../../src/store/store.js')

describe('initializeStores', () => {
	it('configures both object stores against OpenRegister, then loads the settings', async () => {
		const result = await initializeStores()

		expect(calls[0]).toEqual(['createObjectStore', 'planninq-objects'])
		expect(legacyStore.configure).toHaveBeenCalledWith({
			baseUrl: '/index.php/apps/openregister/api/objects',
			schemaBaseUrl: '/index.php/apps/openregister/api/schemas',
		})
		expect(conductionStore.configure).toHaveBeenCalledWith({ baseUrl: '/index.php/apps/openregister/api/objects' })
		expect(settingsStore.fetchSettings).toHaveBeenCalledTimes(1)
		expect(result).toEqual({ settingsStore, objectStore: legacyStore })
		expect(useSettingsStore()).toBe(settingsStore)
	})
})
