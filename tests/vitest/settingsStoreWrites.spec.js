/**
 * The settings store's writes: the request each save sends, what it keeps
 * from the answer, and what it returns on a refusal or a network error.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 */
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

const { useSettingsStore } = await import('../../src/store/modules/settings.js')

/**
 * A fetch answer.
 *
 * @param {boolean} ok - whether the status is 2xx
 * @param {object} body - the JSON body
 * @return {object} the response
 */
function answer(ok, body) {
	return { ok, json: async () => body }
}

describe('settings store writes', () => {
	let store
	let fetchMock
	let errors

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useSettingsStore()
		vi.stubGlobal('OC', { requestToken: 'tok' })
		errors = vi.spyOn(console, 'error').mockImplementation(() => {})
		fetchMock = vi.fn()
		vi.stubGlobal('fetch', fetchMock)
	})

	afterEach(() => {
		vi.unstubAllGlobals()
		errors.mockRestore()
	})

	const request = (body) => ({ method: 'POST', headers: { 'Content-Type': 'application/json', requesttoken: 'tok' }, body })

	it('saveSettings posts the settings and keeps the answer', async () => {
		fetchMock.mockResolvedValueOnce(answer(true, { a: 1 }))
		expect(await store.saveSettings({ a: 1 })).toEqual({ a: 1 })
		expect(fetchMock).toHaveBeenCalledWith('/index.php/apps/planninq/api/settings', request('{"a":1}'))
		expect(store.settings).toEqual({ a: 1 })
		expect(store.loading).toBe(false)
	})

	it('saveSettings returns null on a refusal and on a network error', async () => {
		fetchMock.mockResolvedValueOnce(answer(false, {}))
		expect(await store.saveSettings({ a: 1 })).toBeNull()
		fetchMock.mockRejectedValueOnce(new Error('down'))
		expect(await store.saveSettings({ a: 1 })).toBeNull()
		expect(errors).toHaveBeenCalledWith('Failed to save settings:', expect.any(Error))
		expect(store.loading).toBe(false)
	})

	it('saveRiskScale posts the scale as a JSON string and reloads', async () => {
		fetchMock.mockResolvedValueOnce(answer(true, {})).mockResolvedValueOnce(answer(true, { config: {} }))
		expect(await store.saveRiskScale({ levels: 3 })).toEqual({ ok: true })
		expect(fetchMock.mock.calls[0]).toEqual(['/index.php/apps/planninq/api/settings', request(JSON.stringify({ risk_scale: '{"levels":3}' }))])
		expect(fetchMock).toHaveBeenCalledTimes(2)
	})

	it('saveRiskScale passes on a refusal and maps a network error', async () => {
		fetchMock.mockResolvedValueOnce(answer(false, { error: 'in-use', count: 2, level: 4 }))
		expect(await store.saveRiskScale({})).toEqual({ ok: false, error: 'in-use', count: 2, level: 4 })
		fetchMock.mockRejectedValueOnce(new Error('down'))
		expect(await store.saveRiskScale({})).toEqual({ ok: false, error: 'risk-scale-error' })
		expect(errors).toHaveBeenCalledWith('Failed to save the risk scale:', expect.any(Error))
	})

	it('saveUserSettings posts to the user endpoint and keeps the config', async () => {
		fetchMock.mockResolvedValueOnce(answer(true, { config: { b: 2 } }))
		expect(await store.saveUserSettings({ b: 2 })).toEqual({ config: { b: 2 } })
		expect(fetchMock).toHaveBeenCalledWith('/index.php/apps/planninq/api/settings/user', request('{"b":2}'))
		expect(store.settings).toEqual({ b: 2 })
		fetchMock.mockResolvedValueOnce(answer(true, {}))
		expect(await store.saveUserSettings({})).toEqual({})
		expect(store.settings).toEqual({ b: 2 })
		fetchMock.mockRejectedValueOnce(new Error('down'))
		expect(await store.saveUserSettings({})).toBeNull()
		expect(errors).toHaveBeenCalledWith('Failed to save user settings:', expect.any(Error))
		expect(store.loading).toBe(false)
	})
})
