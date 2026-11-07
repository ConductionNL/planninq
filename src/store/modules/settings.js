import { generateUrl } from '@nextcloud/router'
/**
 * Settings Pinia store.
 *
 * Bootstraps the admin settings UI by fetching/saving the Planninq settings
 * payload (admin config + openregisters flag + isAdmin flag).
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
 */
import { defineStore } from 'pinia'

export const useSettingsStore = defineStore('settings', {
	state: () => ({
		settings: {},
		loading: false,
		hasOpenRegisters: false,
		isAdmin: false,
	}),

	getters: {
		getSettings: (state) => state.settings,
		getIsAdmin: (state) => state.isAdmin,
	},

	actions: {
		/**
		 * Fetch settings from the backend.
		 *
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		async fetchSettings() {
			this.loading = true
			try {
				const response = await fetch(generateUrl('/apps/planninq/api/settings'), {
					headers: { requesttoken: OC.requestToken },
				})
				if (response.ok) {
					const data = await response.json()
					this.settings = data
					this.hasOpenRegisters = !!data?.openregisters
					this.isAdmin = !!data?.isAdmin
					return data
				}
			} catch (error) {
				console.error('Failed to fetch settings:', error)
			} finally {
				this.loading = false
			}
			return null
		},

		/**
		 * Persist settings to the backend.
		 *
		 * @param {object} settings Settings to save
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-4
		 */
		async saveSettings(settings) {
			this.loading = true
			try {
				const response = await fetch(generateUrl('/apps/planninq/api/settings'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify(settings),
				})
				if (response.ok) {
					const data = await response.json()
					this.settings = data
					return data
				}
			} catch (error) {
				console.error('Failed to save settings:', error)
			} finally {
				this.loading = false
			}
			return null
		},

		/**
		 * Save the risk scale. The server refuses a malformed scale (400) and a
		 * smaller one while risks still use a higher level (409, with the count
		 * and the level).
		 *
		 * @param {object} scale The scale
		 * @return {Promise<{ok: boolean, error?: string, count?: number, level?: number}>}
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
		 */
		async saveRiskScale(scale) {
			try {
				const response = await fetch(generateUrl('/apps/planninq/api/settings'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({ risk_scale: JSON.stringify(scale) }),
				})
				const body = await response.json().catch(() => ({}))
				if (!response.ok) {
					return { ok: false, error: body?.error || 'risk-scale-error', count: body?.count, level: body?.level }
				}
				await this.fetchSettings()
				return { ok: true }
			} catch (error) {
				console.error('Failed to save the risk scale:', error)
				return { ok: false, error: 'risk-scale-error' }
			}
		},

		/**
		 * Persist per-user settings (notification toggles) to the backend.
		 *
		 * @param {object} settings User settings to save
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/due-date-reminder-dispatch/tasks.md#1
		 */
		async saveUserSettings(settings) {
			this.loading = true
			try {
				const response = await fetch(generateUrl('/apps/planninq/api/settings/user'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify(settings),
				})
				if (response.ok) {
					const data = await response.json()
					if (data?.config) {
						this.settings = data.config
					}
					return data
				}
			} catch (error) {
				console.error('Failed to save user settings:', error)
			} finally {
				this.loading = false
			}
			return null
		},
	},
})
