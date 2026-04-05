// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Time Entries Pinia store.
 *
 * Calls the TimeEntryController REST endpoints so that server-side validation,
 * task-existence checks, and owner-only delete enforcement are always exercised.
 */
import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const API_BASE = generateUrl('/apps/planix/api/time-entries')

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
		 * Fetch all time entries for a given task via GET /api/time-entries?taskId=.
		 *
		 * @param {string} taskId Task UUID
		 * @return {Promise<Array>}
		 */
		async fetchEntries(taskId) {
			this.loading = true
			this.error = null
			try {
				const response = await axios.get(API_BASE, { params: { taskId } })
				this.entries = response.data || []
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
		 * Create a new time entry via POST /api/time-entries.
		 *
		 * The server sets the `user` field — the client does not supply it.
		 *
		 * @param {object} data Time entry data { task, duration, date, description }
		 * @return {Promise<object|null>}
		 */
		async createEntry(data) {
			this.loading = true
			this.error = null
			try {
				const response = await axios.post(API_BASE, data)
				const entry = response.data
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
		 * Delete a time entry by ID via DELETE /api/time-entries/{id}.
		 *
		 * The server enforces owner-only access.
		 *
		 * @param {string} id Time entry UUID
		 * @return {Promise<boolean>}
		 */
		async deleteEntry(id) {
			this.loading = true
			this.error = null
			try {
				await axios.delete(`${API_BASE}/${id}`)
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
