// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Time Entries Pinia store.
 *
 * Uses the shared @conduction/nextcloud-vue objectStore for all OpenRegister
 * CRUD operations on timeEntry objects.
 */
import { defineStore } from 'pinia'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const REGISTER = 'planix'
const TIME_ENTRY_SCHEMA = 'timeEntry'

export const useTimeEntriesStore = defineStore('timeEntries', {
	state: () => ({
		/** @type {Array} */ entries: [],
		/** @type {boolean} */ loading: false,
		/** @type {string|null} */ error: null,
	}),

	getters: {
		/**
		 * Total logged duration in minutes for the current entries.
		 *
		 * @param {object} state Store state
		 * @return {number}
		 */
		totalDuration(state) {
			return state.entries.reduce((sum, e) => sum + (e.duration || 0), 0)
		},
	},

	actions: {
		/**
		 * Get the shared objectStore with timeEntry type registered.
		 *
		 * @return {object}
		 */
		_objectStore() {
			const store = useObjectStore()
			if (!store.objectTypeRegistry?.[TIME_ENTRY_SCHEMA]) {
				store.registerObjectType(TIME_ENTRY_SCHEMA, TIME_ENTRY_SCHEMA, REGISTER)
			}
			return store
		},

		/**
		 * Fetch all time entries for a given task.
		 *
		 * @param {string} taskId Task UUID
		 * @return {Promise<Array>}
		 */
		async fetchEntries(taskId) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const results = await objectStore.fetchCollection(TIME_ENTRY_SCHEMA, { task: taskId })
				this.entries = results || []
				return this.entries
			} catch (err) {
				this.error = err.message || 'fetch-error'
				console.error('fetchTimeEntries error:', err)
				return []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Create a new time entry.
		 *
		 * @param {object} data Time entry data { task, duration, date, description }
		 * @return {Promise<object|null>}
		 */
		async createEntry(data) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const uid = getCurrentUser()?.uid || ''
				const entry = await objectStore.saveObject(TIME_ENTRY_SCHEMA, {
					...data,
					user: uid,
				})
				if (!entry) {
					const err = objectStore.getError(TIME_ENTRY_SCHEMA)
					this.error = err?.message || 'create-error'
					showError(t('planix', 'Failed to create time entry'))
					return null
				}
				this.entries = [...this.entries, entry]
				return entry
			} catch (err) {
				this.error = err.message || 'create-error'
				showError(t('planix', 'Failed to create time entry'))
				return null
			} finally {
				this.loading = false
			}
		},

		/**
		 * Delete a time entry by ID.
		 *
		 * @param {string} id Time entry UUID
		 * @return {Promise<boolean>}
		 */
		async deleteEntry(id) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const ok = await objectStore.deleteObject(TIME_ENTRY_SCHEMA, id)
				if (!ok) {
					showError(t('planix', 'Failed to delete time entry'))
					return false
				}
				this.entries = this.entries.filter((e) => e.id !== id)
				return true
			} catch (err) {
				this.error = err.message || 'delete-error'
				showError(t('planix', 'Failed to delete time entry'))
				return false
			} finally {
				this.loading = false
			}
		},
	},
})
