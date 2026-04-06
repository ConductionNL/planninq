/**
 * Projects Pinia store.
 *
 * Uses the shared @conduction/nextcloud-vue objectStore for all OpenRegister
 * CRUD operations. Provides project-specific helpers on top.
 */
import { defineStore } from 'pinia'
import { useObjectStore } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { showError, showWarning } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const REGISTER = 'planix'
const PROJECT_SCHEMA = 'project'
const COLUMN_SCHEMA = 'column'
const TASK_SCHEMA = 'task'
const TIME_ENTRY_SCHEMA = 'timeEntry'

/**
 * Default columns created for every new project.
 *
 * @return {Array} Column definitions
 */
function getDefaultColumns() {
	try {
		const state = loadState('planix', 'default_columns', null)
		if (Array.isArray(state) && state.length > 0) return state
	} catch {
		// fall through to hardcoded defaults
	}
	return [
		{ title: 'To Do', order: 0, wipLimit: null, type: 'active' },
		{ title: 'In Progress', order: 1, wipLimit: 3, type: 'active' },
		{ title: 'Review', order: 2, wipLimit: 2, type: 'active' },
		{ title: 'Done', order: 3, wipLimit: null, type: 'done' },
	]
}

export const useProjectsStore = defineStore('projects', {
	state: () => ({
		/** @type {Array} */ projects: [],
		/** @type {object|null} */ activeProject: null,
		/** @type {boolean} */ loading: false,
		/** @type {string|null} */ error: null,
		/** @type {Array} */ columns: [],
		/** @type {Array} */ tasks: [],
		/** @type {boolean} */ boardLoading: false,
	}),

	actions: {
		// ── Internal helpers ──────────────────────────────────────────────

		_objectStore() {
			const store = useObjectStore()
			// Register types if not yet registered.
			if (!store.objectTypeRegistry?.[PROJECT_SCHEMA]) {
				store.registerObjectType(PROJECT_SCHEMA, PROJECT_SCHEMA, REGISTER)
			}
			if (!store.objectTypeRegistry?.[COLUMN_SCHEMA]) {
				store.registerObjectType(COLUMN_SCHEMA, COLUMN_SCHEMA, REGISTER)
			}
			if (!store.objectTypeRegistry?.[TASK_SCHEMA]) {
				store.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER)
			}
			if (!store.objectTypeRegistry?.[TIME_ENTRY_SCHEMA]) {
				store.registerObjectType(TIME_ENTRY_SCHEMA, TIME_ENTRY_SCHEMA, REGISTER)
			}
			return store
		},

		_currentUid() {
			return getCurrentUser()?.uid || ''
		},

		// ── 2.2 fetchProjects ─────────────────────────────────────────────

		/**
		 * Fetch projects the current user is a member of.
		 *
		 * @param {object} filters Additional filters (e.g. { status: 'active' })
		 * @return {Promise<Array>}
		 */
		async fetchProjects(filters = {}) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const uid = this._currentUid()

				// Fetch all projects; client-side filter below keeps only member projects.
				// Note: server-side `members[]` array filter uses PostgreSQL jsonb syntax
				// which is incompatible with MariaDB — do not pass it as a query param.
				const params = { ...filters }

				const results = await objectStore.fetchCollection(PROJECT_SCHEMA, params)

				// Client-side guard: ensure only member projects are shown.
				this.projects = uid
					? results.filter((p) => Array.isArray(p.members) && p.members.includes(uid))
					: results

				return this.projects
			} catch (err) {
				const status = err.response?.status ?? err.status
				const message = err.response?.data?.message ?? err.message ?? 'fetch-error'
				this.error = message
				console.error('fetchProjects error:', { status, message, err })
				return []
			} finally {
				this.loading = false
			}
		},

		// ── 2.3 fetchProject ──────────────────────────────────────────────

		/**
		 * Fetch a single project by ID.
		 * Sets error='forbidden' and redirects on 403.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<object|null>}
		 */
		async fetchProject(id) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const project = await objectStore.fetchObject(PROJECT_SCHEMA, id)
				if (!project) {
					// Check for a 403 error stored by the objectStore.
					const storeError = objectStore.getError(PROJECT_SCHEMA)
					if (storeError?.status === 403 || storeError?.statusCode === 403) {
						this.error = 'forbidden'
					} else {
						this.error = 'not-found'
					}
					this.activeProject = null
					return null
				}
				this.activeProject = project
				return project
			} catch (err) {
				this.error = err.message || 'fetch-error'
				console.error('fetchProject error:', err)
				return null
			} finally {
				this.loading = false
			}
		},

		// ── 2.4 createProject ─────────────────────────────────────────────

		/**
		 * Create a new project. Also creates default columns on success.
		 *
		 * @param {object} data Project fields (title required)
		 * @return {Promise<object>} Created project
		 */
		async createProject(data) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const uid = this._currentUid()
				const projectData = {
					...data,
					status: data.status || 'active',
					members: uid ? [uid] : [],
				}

				const project = await objectStore.saveObject(PROJECT_SCHEMA, projectData)
				if (!project) {
					const err = objectStore.getError(PROJECT_SCHEMA)
					this.error = err?.message || 'create-error'
					throw new Error(this.error)
				}

				this.projects = [...this.projects, project]

				// Create default columns (non-blocking).
				await this.createDefaultColumns(project.id)

				return project
			} catch (err) {
				this.error = err.message || 'create-error'
				throw err
			} finally {
				this.loading = false
			}
		},

		// ── 2.5 updateProject ─────────────────────────────────────────────

		/**
		 * Update an existing project.
		 *
		 * @param {string} id Project ID
		 * @param {object} data Updated fields
		 * @return {Promise<object|null>}
		 */
		async updateProject(id, data) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const updated = await objectStore.saveObject(PROJECT_SCHEMA, { id, ...data })
				if (!updated) {
					const err = objectStore.getError(PROJECT_SCHEMA)
					this.error = err?.message || 'update-error'
					return null
				}

				// Update in local arrays.
				this.projects = this.projects.map((p) => (p.id === id ? { ...p, ...updated } : p))
				if (this.activeProject?.id === id) {
					this.activeProject = { ...this.activeProject, ...updated }
				}

				return updated
			} catch (err) {
				this.error = err.message || 'update-error'
				return null
			} finally {
				this.loading = false
			}
		},

		// ── 2.6 createDefaultColumns ──────────────────────────────────────

		/**
		 * Create default columns for a newly-created project.
		 * Partial failures show a warning toast but do not throw.
		 *
		 * @param {string} projectId Parent project ID
		 * @return {Promise<{created: number, failed: number}>}
		 */
		async createDefaultColumns(projectId) {
			const objectStore = this._objectStore()
			const columns = getDefaultColumns()
			let created = 0
			const failedTitles = []

			for (const col of columns) {
				const result = await objectStore.saveObject(COLUMN_SCHEMA, {
					...col,
					project: projectId,
				})
				if (result) {
					created++
				} else {
					failedTitles.push(col.title)
				}
			}

			if (failedTitles.length > 0) {
				showWarning(
					t('planix', 'Some columns could not be created: {columns}', {
						columns: failedTitles.join(', '),
					}),
				)
			}

			return { created, failed: failedTitles.length }
		},

		// ── 2.6b fetchColumns ─────────────────────────────────────────────

		/**
		 * Fetch columns for a project, ordered by position.
		 *
		 * @param {string} projectId Parent project ID
		 * @return {Promise<Array>}
		 */
		async fetchColumns(projectId) {
			try {
				const objectStore = this._objectStore()
				const results = await objectStore.fetchCollection(COLUMN_SCHEMA, { project: projectId })
				this.columns = [...results].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
				return this.columns
			} catch (err) {
				console.error('fetchColumns error:', err)
				this.columns = []
				return []
			}
		},

		// ── 2.6c fetchTasks ───────────────────────────────────────────────

		/**
		 * Fetch tasks for a project.
		 *
		 * @param {string} projectId Parent project ID
		 * @return {Promise<Array>}
		 */
		async fetchTasks(projectId) {
			try {
				const objectStore = this._objectStore()
				const results = await objectStore.fetchCollection(TASK_SCHEMA, { project: projectId })
				this.tasks = results
				return this.tasks
			} catch (err) {
				console.error('fetchTasks error:', err)
				this.tasks = []
				return []
			}
		},

		// ── 2.6d fetchBoard ───────────────────────────────────────────────

		/**
		 * Fetch all board data (columns + tasks) for a project.
		 *
		 * @param {string} projectId Parent project ID
		 * @return {Promise<{columns: Array, tasks: Array}>}
		 */
		async fetchBoard(projectId) {
			this.boardLoading = true
			try {
				const [columns, tasks] = await Promise.all([
					this.fetchColumns(projectId),
					this.fetchTasks(projectId),
				])
				return { columns, tasks }
			} finally {
				this.boardLoading = false
			}
		},

		// ── 2.6e createColumn ─────────────────────────────────────────────

		/**
		 * Create a new column for a project.
		 *
		 * @param {object} data Column fields
		 * @return {Promise<object>}
		 */
		async createNewColumn(data) {
			const objectStore = this._objectStore()
			const column = await objectStore.saveObject(COLUMN_SCHEMA, data)
			if (column) {
				this.columns = [...this.columns, column].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
			}
			return column
		},

		// ── 2.6f updateTask ───────────────────────────────────────────────

		/**
		 * Update a task (e.g. move to a different column).
		 *
		 * @param {string} taskId Task ID
		 * @param {object} data Updated fields
		 * @return {Promise<object|null>}
		 */
		async updateTask(taskId, data) {
			const objectStore = this._objectStore()
			const updated = await objectStore.saveObject(TASK_SCHEMA, { id: taskId, ...data })
			if (updated) {
				this.tasks = this.tasks.map((t) => (t.id === taskId ? { ...t, ...updated } : t))
			}
			return updated
		},

		// ── 2.7 archiveProject ────────────────────────────────────────────

		/**
		 * Archive a project by setting status to 'archived'.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<object|null>}
		 */
		async archiveProject(id) {
			const updated = await this.updateProject(id, { status: 'archived' })
			if (updated) {
				// Remove from the active list (default filter excludes archived).
				this.projects = this.projects.filter((p) => p.id !== id)
			}
			return updated
		},

		// ── 2.8 deleteProject ─────────────────────────────────────────────

		/**
		 * Cascade-delete a project and all dependent objects.
		 * Order: timeEntries → tasks → columns → project.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<boolean>}
		 */
		async deleteProject(id) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()

				// 1. Fetch tasks for this project.
				const tasks = await objectStore.fetchCollection(TASK_SCHEMA, { project: id })

				// 2. Fetch and delete time entries for each task.
				for (const task of tasks) {
					const entries = await objectStore.fetchCollection(TIME_ENTRY_SCHEMA, { task: task.id })
					for (const entry of entries) {
						const ok = await objectStore.deleteObject(TIME_ENTRY_SCHEMA, entry.id)
						if (!ok) {
							showError(t('planix', 'Failed to delete time entry — deletion stopped'))
							return false
						}
					}
				}

				// 3. Delete tasks.
				for (const task of tasks) {
					const ok = await objectStore.deleteObject(TASK_SCHEMA, task.id)
					if (!ok) {
						showError(t('planix', 'Failed to delete task — deletion stopped'))
						return false
					}
				}

				// 4. Fetch and delete columns.
				const columns = await objectStore.fetchCollection(COLUMN_SCHEMA, { project: id })
				for (const col of columns) {
					const ok = await objectStore.deleteObject(COLUMN_SCHEMA, col.id)
					if (!ok) {
						showError(t('planix', 'Failed to delete column — deletion stopped'))
						return false
					}
				}

				// 5. Delete the project itself.
				const ok = await objectStore.deleteObject(PROJECT_SCHEMA, id)
				if (!ok) {
					showError(t('planix', 'Failed to delete project'))
					return false
				}

				this.projects = this.projects.filter((p) => p.id !== id)
				if (this.activeProject?.id === id) {
					this.activeProject = null
				}

				return true
			} catch (err) {
				this.error = err.message || 'delete-error'
				showError(t('planix', 'An error occurred during project deletion'))
				return false
			} finally {
				this.loading = false
			}
		},

		// ── 2.9 addMember ─────────────────────────────────────────────────

		/**
		 * Add a Nextcloud user as a project member.
		 *
		 * @param {string} projectId Project ID
		 * @param {string} userUid Nextcloud UID to add
		 * @return {Promise<object|null>}
		 */
		async addMember(projectId, userUid) {
			const project = await this.fetchProject(projectId)
			if (!project) return null

			const members = Array.isArray(project.members) ? [...project.members] : []
			if (members.includes(userUid)) return project // Guard against duplicates.

			members.push(userUid)
			return this.updateProject(projectId, { members })
		},

		// ── 2.10 removeMember ─────────────────────────────────────────────

		/**
		 * Remove a member from a project.
		 * Returns the count of tasks currently assigned to that member.
		 *
		 * @param {string} projectId Project ID
		 * @param {string} userUid Nextcloud UID to remove
		 * @return {Promise<number>} Count of tasks assigned to that member
		 */
		async removeMember(projectId, userUid) {
			const project = await this.fetchProject(projectId)
			if (!project) return 0

			// Count assigned tasks for the warning dialog.
			const objectStore = this._objectStore()
			const tasks = await objectStore.fetchCollection(TASK_SCHEMA, {
				project: projectId,
				assignedTo: userUid,
			})
			const assignedCount = tasks.length

			const members = (Array.isArray(project.members) ? project.members : []).filter(
				(uid) => uid !== userUid,
			)
			await this.updateProject(projectId, { members })

			return assignedCount
		},

		// ── 2.11 leaveProject ─────────────────────────────────────────────

		/**
		 * Current user leaves a project.
		 * Returns { isLastMember: true } without removing if the user is the last member.
		 *
		 * @param {string} projectId Project ID
		 * @return {Promise<{isLastMember: boolean}|number>}
		 */
		async leaveProject(projectId) {
			const project = await this.fetchProject(projectId)
			if (!project) return { isLastMember: false }

			const members = Array.isArray(project.members) ? project.members : []
			const uid = this._currentUid()

			if (members.filter((m) => m !== uid).length === 0) {
				return { isLastMember: true }
			}

			return this.removeMember(projectId, uid)
		},

		/**
		 * Get task count for a project (used by delete dialog).
		 *
		 * @param {string} projectId Project ID
		 * @return {Promise<number>}
		 */
		async getTaskCount(projectId) {
			try {
				const objectStore = this._objectStore()
				const tasks = await objectStore.fetchCollection(TASK_SCHEMA, { project: projectId })
				return tasks.length
			} catch {
				return 0
			}
		},
	},
})
