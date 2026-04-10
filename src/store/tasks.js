// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Tasks Pinia store.
 *
 * Uses the shared @conduction/nextcloud-vue objectStore for all OpenRegister
 * CRUD operations. Provides task-specific helpers on top.
 *
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-1
 */
import { defineStore } from 'pinia'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const REGISTER = 'planix'
const TASK_SCHEMA = 'task'

/**
 * @spec openspec/changes/dashboard-my-work/tasks.md#task-1
 */
export const useTasksStore = defineStore('tasks', {
	state: () => ({
		/** @type {Array} */ tasks: [],
		/** @type {boolean} */ loading: false,
		/** @type {string|null} */ error: null,
	}),

	actions: {
		_objectStore() {
			const store = useObjectStore()
			if (!store.objectTypeRegistry?.[TASK_SCHEMA]) {
				store.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER)
			}
			return store
		},

		_currentUid() {
			return getCurrentUser()?.uid || ''
		},

		/**
		 * Fetch tasks with optional filters.
		 *
		 * @param {object} filters Query filters (e.g. { assignedTo: uid })
		 * @return {Promise<Array>}
		 * @spec openspec/changes/dashboard-my-work/tasks.md#task-3
		 */
		async fetchTasks(filters = {}) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const results = await objectStore.fetchCollection(TASK_SCHEMA, filters)
				this.tasks = results || []
				return this.tasks
			} catch (err) {
				this.error = err.message || 'fetch-error'
				console.error('fetchTasks error:', err)
				return []
			} finally {
				this.loading = false
			}
		},

		/**
		 * Update the status of a task.
		 *
		 * @param {string} taskId Task ID
		 * @param {string} newStatus New status value
		 * @return {Promise<object|null>}
		 * @spec openspec/changes/dashboard-my-work/tasks.md#task-8
		 */
		async updateStatus(taskId, newStatus) {
			try {
				const objectStore = this._objectStore()
				const data = { id: taskId, status: newStatus }
				if (newStatus === 'done') {
					data.completedAt = new Date().toISOString()
				}
				const updated = await objectStore.saveObject(TASK_SCHEMA, data)
				if (!updated) {
					throw new Error('update-error')
				}
				// Update local state.
				this.tasks = this.tasks.map((task) =>
					task.id === taskId ? { ...task, ...updated } : task,
				)
				return updated
			} catch (err) {
				showError(t('planix', 'Failed to update task status'))
				throw err
			}
		},
	},
})
