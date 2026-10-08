import { buildHeaders } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
/**
 * Projects Pinia store.
 *
 * Uses the shared @conduction/nextcloud-vue objectStore for all OpenRegister
 * CRUD operations. Provides project-specific helpers on top.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-8
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-9
 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
 */
import { defineStore } from 'pinia'
import { forgeLinkRefusal } from '../utils/forgeUrl.js'
import { isMine } from '../utils/myWork.js'
import { closePatch, refusalMessage, reorderPatches } from '../utils/phaseHelpers.js'
import { canSeeProject } from '../utils/portfolioGrouping.js'
import { actionNames, transitionRequest } from '../utils/projectLifecycle.js'
import { currentGroupIds, rolePatch } from '../utils/projectRole.js'
import { epicPatch, shipPatches } from '../utils/roadmapHelpers.js'
import { duplicatePayload } from '../utils/taskBreakdown.js'
import { deleteRefusal, withTaskDefaults } from '../utils/taskEditing.js'
import { columnCopyPayload, columnTaskCopyPayload, moveTaskPatch } from '../utils/taskMove.js'
import { useObjectStore } from './objectStore.js'

// The OpenRegister register SLUG, not the app id. It moved from `planix` to
// `planninq` together with the MigrateRegisterSlug repair step, which renames
// the register ROW: OR resolves a register by slug and by nothing else, so the
// literal and the row move in the same release or neither resolves.
const REGISTER = 'planninq'
const PROJECT_SCHEMA = 'project'
const COLUMN_SCHEMA = 'column'
const TASK_SCHEMA = 'task'
const TIME_ENTRY_SCHEMA = 'plannedTimeEntry'
const LABEL_SCHEMA = 'label'
const LOG_SCHEMA = 'projectLogEntry'
const RISK_SCHEMA = 'risk'
const STATUS_REPORT_SCHEMA = 'projectStatusReport'
const PORTFOLIO_SCHEMA = 'projectPortfolio'
const PHASE_SCHEMA = 'projectPhase'
const FINANCE_LINE_SCHEMA = 'financeLine'
const PROJECT_FIELD_SCHEMA = 'projectField'
const RELEASE_SCHEMA = 'projectRelease'
const BOARD_FILTER_SCHEMA = 'boardFilter'
const BOARD_VIEW_SCHEMA = 'boardView'
const FORGE_LINK_SCHEMA = 'forgeLink'

/**
 * Largest page OpenRegister will return. Asking for more is silently capped.
 *
 * The underscore prefix is required: OpenRegister reads an unprefixed `limit`
 * as a property filter, which matches nothing and returns an empty collection.
 */
const MAX_PAGE = 1000

/**
 * GET one planninq object with the caller's rights.
 *
 * @param {string} schema The schema slug.
 * @param {string} id     The object UUID.
 * @return {Promise<object|null>} The object, or null when it is not readable.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
 */
async function readObject(schema, id) {
	if (!id) {
		return null
	}
	try {
		const response = await fetch(generateUrl(`/apps/openregister/api/objects/planninq/${schema}/${id}`), { headers: buildHeaders() })
		return response.ok ? await response.json() : null
	} catch (err) {
		console.error('readObject error:', err)
		return null
	}
}

/**
 * Read an ENTIRE collection, following pages until it is exhausted.
 *
 * OpenRegister pages at 20 by default and caps a page at 1000, and this store
 * reads whole collections for three purposes that are all wrong when truncated:
 * rendering a board, COUNTING, and cascade-DELETING. A 21-task project rendered
 * 20 tasks and reported "20" as its total; worse, deleting it removed one page
 * of tasks and left the rest as orphans, while every call reported success.
 *
 * Raising the page size alone does not fix it — the largest project on our own
 * instance holds 2,015 tasks, so a single `_limit=1000` read still silently
 * dropped half of it. Only following `_page` to exhaustion is correct.
 *
 * @param {object} objectStore the shared OpenRegister object store
 * @param {string} schema      schema slug to read
 * @param {object} filters     property filters (no control params)
 * @return {Promise<Array>} every row in the collection
 */
async function fetchEvery(objectStore, schema, filters = {}) {
	const out = []
	const seen = new Set()
	for (let page = 1; ; page++) {
		const batch = await objectStore.fetchCollection(schema, { ...filters, _limit: MAX_PAGE, _page: page })
		const rows = Array.isArray(batch) ? batch : []

		// Keep only rows we have not already collected. This is also the
		// termination guard: a server that ignored `_page` would hand back the
		// same full batch forever, and a `rows.length < MAX_PAGE` check alone
		// would loop until the tab died. Zero NEW rows means there is no more
		// collection to read, whatever the server thinks it is doing.
		let added = 0
		for (const row of rows) {
			const id = row?.id ?? row?.uuid ?? row?.['@self']?.id
			if (id !== undefined && seen.has(id)) {
				continue
			}
			if (id !== undefined) {
				seen.add(id)
			}
			out.push(row)
			added++
		}

		if (added === 0 || rows.length < MAX_PAGE) {
			break
		}
	}
	return out
}

export const useProjectsStore = defineStore('projects', {
	state: () => ({
		/** @type {Array} */ projects: [],
		/** @type {object|null} */ activeProject: null,
		/** @type {object|null} */ activeTask: null,
		/** @type {boolean} */ loading: false,
		/** @type {string|null} */ error: null,
	}),

	actions: {
		// ── Internal helpers ──────────────────────────────────────────────

		/**
		 * @spec exclude Internal helper — lazily registers Planninq schemas on the shared object store and returns it.
		 */
		_objectStore() {
			const store = useObjectStore()
			// Register types if not yet registered. The schema constants ARE the
			// canonical OR slugs (lib/Settings/planninq_register.json), so they are
			// passed as slug hints too — liveUpdatesPlugin then derives the
			// or-collection-{registerSlug}-{schemaSlug} event key without a lazy
			// register/schema fetch on first subscribe().
			if (!store.objectTypeRegistry?.[PROJECT_SCHEMA]) {
				store.registerObjectType(PROJECT_SCHEMA, PROJECT_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: PROJECT_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[COLUMN_SCHEMA]) {
				store.registerObjectType(COLUMN_SCHEMA, COLUMN_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: COLUMN_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[TASK_SCHEMA]) {
				store.registerObjectType(TASK_SCHEMA, TASK_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: TASK_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[TIME_ENTRY_SCHEMA]) {
				store.registerObjectType(TIME_ENTRY_SCHEMA, TIME_ENTRY_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: TIME_ENTRY_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[LABEL_SCHEMA]) {
				store.registerObjectType(LABEL_SCHEMA, LABEL_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: LABEL_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[LOG_SCHEMA]) {
				store.registerObjectType(LOG_SCHEMA, LOG_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: LOG_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[RISK_SCHEMA]) {
				store.registerObjectType(RISK_SCHEMA, RISK_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: RISK_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[FINANCE_LINE_SCHEMA]) {
				store.registerObjectType(FINANCE_LINE_SCHEMA, FINANCE_LINE_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: FINANCE_LINE_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[RELEASE_SCHEMA]) {
				store.registerObjectType(RELEASE_SCHEMA, RELEASE_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: RELEASE_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[BOARD_FILTER_SCHEMA]) {
				store.registerObjectType(BOARD_FILTER_SCHEMA, BOARD_FILTER_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: BOARD_FILTER_SCHEMA })
			}
			if (!store.objectTypeRegistry?.[BOARD_VIEW_SCHEMA]) {
				store.registerObjectType(BOARD_VIEW_SCHEMA, BOARD_VIEW_SCHEMA, REGISTER, { registerSlug: REGISTER, schemaSlug: BOARD_VIEW_SCHEMA })
			}
			return store
		},

		/**
		 * @spec exclude Auth passthrough — returns the current user's UID.
		 */
		_currentUid() {
			return getCurrentUser()?.uid || ''
		},

		// ── 2.2 fetchProjects ─────────────────────────────────────────────

		/**
		 * Fetch projects the current user is a member of.
		 *
		 * @param {object} filters Additional filters (e.g. { status: 'active' })
		 * @return {Promise<Array>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
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
				//
				// `_limit` is what makes "fetch all" true. OpenRegister pages at 20 by
				// default, and filtering for membership CLIENT-SIDE over a SERVER-PAGED
				// result is only correct when the page holds everything: on an instance
				// with 31 projects the user's own board simply stopped appearing in the
				// list, with no error and no empty state — it was on page two, and page
				// two was never requested. The bug scales with the instance, so it shows
				// up first for whoever has the most data.
				//
				// The underscore is not cosmetic: OpenRegister treats an unprefixed
				// `limit` as a PROPERTY filter, which matches nothing and returns an
				// empty collection — the same silent disappearance, one layer down.
				const results = await fetchEvery(objectStore, PROJECT_SCHEMA, filters)

				// Client-side guard: only projects the user is on or reads as a portfolio manager.
				// Members, and the managers of the project's portfolio (portfolioReaders).
				this.projects = uid
					? results.filter((p) => canSeeProject(p, uid, currentGroupIds()))
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

		/**
		 * Apply a live-refetched project collection to store state, re-applying
		 * the same member filter `fetchProjects` uses. Called by the ProjectList
		 * live-update bridge when liveUpdatesPlugin refreshes the 'project'
		 * collection after an or-collection event.
		 *
		 * @param {Array} results Fresh collection results from the object store
		 * @return {Array} The filtered project list now in state
		 *
		 * @spec openspec/specs/realtime-updates.md
		 */
		applyLiveProjects(results) {
			const uid = this._currentUid()
			const list = Array.isArray(results) ? results : []
			this.projects = uid
				? list.filter((p) => canSeeProject(p, uid, currentGroupIds()))
				: list
			return this.projects
		},

		// ── 2.3 fetchProject ──────────────────────────────────────────────

		/**
		 * Fetch a single project by ID.
		 * Sets error='forbidden' and redirects on 403.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
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

		// ── 2.3b fetchTask ────────────────────────────────────────────────

		/**
		 * Fetch a single task by ID and store it on `activeTask`.
		 *
		 * Used by the task detail view (TaskDetail.vue) to render the task and
		 * mount the collaboration sidebar (comments / files / audit trail). Reads
		 * directly from OpenRegister via the shared object store (ADR-022) — no
		 * Planninq pass-through controller.
		 *
		 * @param {string} id Task UUID
		 * @return {Promise<object|null>} The task, or null on error / not found.
		 *
		 * @spec openspec/specs/task-collaboration.md
		 */
		async fetchTask(id) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()
				const task = await objectStore.fetchObject(TASK_SCHEMA, id)
				if (!task) {
					const storeError = objectStore.getError(TASK_SCHEMA)
					this.error = (storeError?.status === 403 || storeError?.statusCode === 403)
						? 'forbidden'
						: 'not-found'
					this.activeTask = null
					return null
				}
				this.activeTask = task
				return task
			} catch (err) {
				this.error = err.message || 'fetch-error'
				console.error('fetchTask error:', err)
				return null
			} finally {
				this.loading = false
			}
		},

		/**
		 * Ask whether a project key is well formed and free (tasks-readable-keys).
		 *
		 * @param {string} key The normalised key.
		 * @return {Promise<{valid: boolean, available: boolean}|null>} Null when the check failed.
		 *
		 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.2
		 */
		async checkProjectKey(key) {
			try {
				const url = generateUrl('/apps/planninq/api/projects/key-available') + '?key=' + encodeURIComponent(key)
				const response = await fetch(url, { headers: buildHeaders() })
				return response.ok ? await response.json() : null
			} catch {
				return null
			}
		},

		// ── 2.4 createProject ─────────────────────────────────────────────

		/**
		 * Create a new project via the Planninq server-side proxy endpoint.
		 *
		 * Posts to `/api/projects` (ProjectController::create) which enforces
		 * the `allow_project_creation` policy server-side BEFORE writing to OR,
		 * closing the TOCTOU gap where a caller could bypass the policy by
		 * posting directly to OR's generic object API (C1 fix).
		 *
		 * Owner and initial membership are set server-side; any values passed
		 * here for those fields are overridden by the controller.
		 *
		 * @param {object} data Project fields (title required)
		 * @return {Promise<object>} Created project
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-6
		 */
		async createProject(data) {
			this.loading = true
			this.error = null
			try {
				const url = generateUrl('/apps/planninq/api/projects')
				const response = await fetch(url, {
					method: 'POST',
					headers: buildHeaders(),
					body: JSON.stringify({
						...data,
						status: data.status || 'active',
					}),
				})

				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}))
					const message = errorData?.error || 'create-error'
					this.error = message
					throw new Error(message)
				}

				const project = await response.json()

				this.projects = [...this.projects, project]

				// The server created the project's default columns in the same
				// request (ProjectController::create, boards-configurable-columns).
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
		 * Update an existing project (full PUT via OR object store).
		 *
		 * Used for owner-initiated updates where ALL fields are provided.
		 * For partial/single-field updates (e.g. members-only changes) use
		 * patchProject() instead to avoid OR's PUT fill-missing-with-null
		 * semantics wiping fields like `owner` (C2 fix).
		 *
		 * @param {string} id Project ID
		 * @param {object} data Updated fields
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-7
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

		// ── 2.5b patchProject ────────────────────────────────────────────

		/**
		 * Partially update a project via OR's PATCH endpoint.
		 *
		 * OR PATCH merges the supplied fields with the existing object rather
		 * than replacing it (unlike PUT which fills missing schema properties
		 * with null). Use this for member-only or other single-field changes
		 * to avoid silently wiping `owner` and other required fields (C2 fix).
		 *
		 * @param {string} id Project ID
		 * @param {object} partial Only the fields to change
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/specs/projects.md
		 */
		async patchProject(id, partial) {
			this.loading = true
			this.error = null
			try {
				const url = generateUrl(`/apps/openregister/api/objects/planninq/project/${id}`)
				const response = await fetch(url, {
					method: 'PATCH',
					headers: buildHeaders(),
					body: JSON.stringify(partial),
				})

				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}))
					this.error = errorData?.message || 'patch-error'
					return null
				}

				const updated = await response.json()

				// Update local arrays.
				this.projects = this.projects.map((p) => (p.id === id ? { ...p, ...updated } : p))
				if (this.activeProject?.id === id) {
					this.activeProject = { ...this.activeProject, ...updated }
				}

				return updated
			} catch (err) {
				this.error = err.message || 'patch-error'
				return null
			} finally {
				this.loading = false
			}
		},

		// ── 2.6 board columns ─────────────────────────────────────────────

		/**
		 * Fetch a project's board columns, in lane order.
		 *
		 * Reads the `column` schema straight from OpenRegister (ADR-022), which
		 * scopes the read to the project's members.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The columns (empty array on error)
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
		 */
		async fetchColumns(projectId) {
			try {
				const objectStore = this._objectStore()
				const columns = await fetchEvery(objectStore, COLUMN_SCHEMA, { project: projectId })
				return Array.isArray(columns)
					? [...columns].sort((a, b) => (Number(a?.order) || 0) - (Number(b?.order) || 0))
					: []
			} catch (err) {
				console.error('fetchColumns error:', err)
				return []
			}
		},

		/**
		 * Write a column: POST when it has no id, PATCH the given fields when it has one.
		 *
		 * PATCH, not PUT, for the same reason as `updateTaskStatus`. The server
		 * refuses the write unless the caller owns the project or is an admin
		 * (ColumnOwnerGuardListener).
		 *
		 * @param {object} column The column, or the fields to change plus its id
		 * @return {Promise<object|null>} The saved column, or null on failure
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
		 */
		async saveColumn(column) {
			const { id, ...fields } = column
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/column/${id}`)
				: generateUrl('/apps/openregister/api/objects/planninq/column')
			try {
				const response = await fetch(url, {
					method: id ? 'PATCH' : 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(fields),
				})
				if (!response.ok) {
					return null
				}
				return await response.json()
			} catch (err) {
				console.error('saveColumn error:', err)
				return null
			}
		},

		/**
		 * Delete a column. Its cards must have been moved first.
		 *
		 * @param {string} id Column UUID
		 * @return {Promise<boolean>} Whether the column is gone
		 *
		 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
		 */
		async deleteColumn(id) {
			try {
				const url = generateUrl(`/apps/openregister/api/objects/planninq/column/${id}`)
				const response = await fetch(url, { method: 'DELETE', headers: buildHeaders() })
				return response.ok
			} catch (err) {
				console.error('deleteColumn error:', err)
				return false
			}
		},

		/**
		 * Every log entry of a project. OpenRegister's read rule on the
		 * members list scopes the read to the project's members.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The entries (empty array on error)
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		async fetchLogEntries(projectId) {
			try {
				const objectStore = this._objectStore()
				const entries = await fetchEvery(objectStore, LOG_SCHEMA, { project: projectId })
				return Array.isArray(entries) ? entries : []
			} catch (err) {
				console.error('fetchLogEntries error:', err)
				return []
			}
		},

		/**
		 * Write a log entry: POST when it has no id, PATCH the given fields when
		 * it has one. The server stamps the author, the time and the members
		 * list, and refuses a caller who is not on the project.
		 *
		 * @param {object} entry The entry, or the fields to change plus its id
		 * @return {Promise<object|null>} The saved entry, or null on failure
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-2.2
		 */
		async saveLogEntry(entry) {
			const { id, ...fields } = entry
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/${LOG_SCHEMA}/${id}`)
				: generateUrl(`/apps/openregister/api/objects/planninq/${LOG_SCHEMA}`)
			try {
				const response = await fetch(url, {
					method: id ? 'PATCH' : 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(fields),
				})
				if (!response.ok) {
					return null
				}
				return await response.json()
			} catch (err) {
				console.error('saveLogEntry error:', err)
				return null
			}
		},

		/**
		 * Every risk of a project, scoped by OpenRegister to its members.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The risks (empty array on error)
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		async fetchRisks(projectId) {
			try {
				const objectStore = this._objectStore()
				const risks = await fetchEvery(objectStore, RISK_SCHEMA, { project: projectId })
				return Array.isArray(risks) ? risks : []
			} catch (err) {
				console.error('fetchRisks error:', err)
				return []
			}
		},

		/**
		 * Write a risk: POST when it has no id, PATCH when it has one. The
		 * server calculates the score and refuses a caller not on the project.
		 *
		 * @param {object} risk The risk, or the fields to change plus its id
		 * @return {Promise<object|null>} The saved risk, or null on failure
		 *
		 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
		 */
		async saveRisk(risk) {
			const { id, ...fields } = risk
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/${RISK_SCHEMA}/${id}`)
				: generateUrl(`/apps/openregister/api/objects/planninq/${RISK_SCHEMA}`)
			try {
				const response = await fetch(url, {
					method: id ? 'PATCH' : 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(fields),
				})
				if (!response.ok) {
					return null
				}
				return await response.json()
			} catch (err) {
				console.error('saveRisk error:', err)
				return null
			}
		},

		/**
		 * The finance lines of a project. OpenRegister returns them only to the
		 * owner, the portfolio managers, the finance import and admins.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The lines (empty array on error)
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		async fetchFinanceLines(projectId) {
			try {
				const lines = await fetchEvery(this._objectStore(), FINANCE_LINE_SCHEMA, { project: projectId })
				return Array.isArray(lines) ? lines : []
			} catch (err) {
				console.error('fetchFinanceLines error:', err)
				return []
			}
		},

		/**
		 * Write a manual finance line: POST without an id, PATCH with one.
		 *
		 * @param {object} line The line fields, with `id` for an existing one
		 * @return {Promise<object|null>} The saved line, or null when refused
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		async saveFinanceLine(line) {
			const { id, ...fields } = line
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/${FINANCE_LINE_SCHEMA}/${id}`)
				: generateUrl(`/apps/openregister/api/objects/planninq/${FINANCE_LINE_SCHEMA}`)
			try {
				const response = await fetch(url, {
					method: id ? 'PATCH' : 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(fields),
				})
				if (!response.ok) {
					return null
				}
				return await response.json()
			} catch (err) {
				console.error('saveFinanceLine error:', err)
				return null
			}
		},

		/**
		 * Remove a manual finance line.
		 *
		 * @param {string} id The line UUID
		 * @return {Promise<boolean>} Whether it was removed
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.2
		 */
		async deleteFinanceLine(id) {
			try {
				const response = await fetch(generateUrl(`/apps/openregister/api/objects/planninq/${FINANCE_LINE_SCHEMA}/${id}`), {
					method: 'DELETE',
					headers: buildHeaders(),
				})
				return response.ok
			} catch (err) {
				console.error('deleteFinanceLine error:', err)
				return false
			}
		},

		/**
		 * The finance lines of a portfolio's projects and the time booked on the
		 * projects whose money the viewer may see, for the portfolio totals.
		 * The lines are read with the one equality filter `portfolio = id`.
		 *
		 * @param {string} portfolioId The portfolio UUID
		 * @param {Array<object>} projects The portfolio's projects whose money the viewer may see
		 * @return {Promise<{lines: Array, entriesByProject: object}>}
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-3.4
		 */
		async fetchPortfolioMoney(portfolioId, projects) {
			try {
				const [lines, ...entries] = await Promise.all([
					fetchEvery(this._objectStore(), FINANCE_LINE_SCHEMA, { portfolio: portfolioId }),
					...projects.map((project) => this.fetchProjectTimeEntries(project.id)),
				])
				return {
					lines: Array.isArray(lines) ? lines : [],
					entriesByProject: Object.fromEntries(projects.map((project, i) => [project.id, entries[i]])),
				}
			} catch (err) {
				console.error('fetchPortfolioMoney error:', err)
				return { lines: [], entriesByProject: {} }
			}
		},

		/**
		 * The project fields an admin defined, for the Details tab and the overview.
		 *
		 * @return {Promise<Array>} The field definitions (empty array on error)
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
		 */
		async fetchProjectFields() {
			try {
				const fields = await fetchEvery(this._objectStore(), PROJECT_FIELD_SCHEMA, {})
				return Array.isArray(fields) ? fields : []
			} catch (err) {
				console.error('fetchProjectFields error:', err)
				return []
			}
		},

		/**
		 * Every time entry booked on a project, for its labour cost.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The entries (empty array on error)
		 *
		 * @spec openspec/changes/portfolio-finance/tasks.md#task-2.1
		 */
		async fetchProjectTimeEntries(projectId) {
			try {
				const entries = await fetchEvery(this._objectStore(), TIME_ENTRY_SCHEMA, { project: projectId })
				return Array.isArray(entries) ? entries : []
			} catch (err) {
				console.error('fetchProjectTimeEntries error:', err)
				return []
			}
		},

		/**
		 * Every status report of a project, scoped by OpenRegister to its members.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The reports (empty array on error)
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		async fetchStatusReports(projectId) {
			try {
				const objectStore = this._objectStore()
				const reports = await fetchEvery(objectStore, STATUS_REPORT_SCHEMA, { project: projectId })
				return Array.isArray(reports) ? reports : []
			} catch (err) {
				console.error('fetchStatusReports error:', err)
				return []
			}
		},

		/**
		 * Write a new status report. The server calculates the overall status,
		 * refuses anyone but the project owner, and copies the newest report
		 * onto the project.
		 *
		 * @param {object} report The report payload
		 * @return {Promise<object|null>} The saved report, or null on failure
		 *
		 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
		 */
		async saveStatusReport(report) {
			try {
				const response = await fetch(generateUrl(`/apps/openregister/api/objects/planninq/${STATUS_REPORT_SCHEMA}`), {
					method: 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(report),
				})
				if (!response.ok) {
					return null
				}
				return await response.json()
			} catch (err) {
				console.error('saveStatusReport error:', err)
				return null
			}
		},

		/**
		 * Every portfolio. Every signed-in user reads their names, so lists can group by them.
		 *
		 * @return {Promise<Array>} The portfolios (empty array on error)
		 *
		 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
		 */
		async fetchPortfolios() {
			try {
				const portfolios = await fetchEvery(this._objectStore(), PORTFOLIO_SCHEMA, {})
				return Array.isArray(portfolios) ? portfolios : []
			} catch (err) {
				console.error('fetchPortfolios error:', err)
				return []
			}
		},

		/**
		 * Every release of a project, scoped by OpenRegister to its members.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The releases (empty array on error)
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
		 */
		async fetchReleases(projectId) {
			try {
				const releases = await fetchEvery(this._objectStore(), RELEASE_SCHEMA, { project: projectId })
				return Array.isArray(releases) ? releases : []
			} catch (err) {
				console.error('fetchReleases error:', err)
				return []
			}
		},

		/**
		 * The saved filters of a project the user may read: their own, and the
		 * ones shared with the project (the register's read rule decides).
		 *
		 * @param {string} projectId Project UUID
		 * @return {Promise<Array<object>>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
		 */
		async fetchBoardFilters(projectId) {
			try {
				const filters = await fetchEvery(this._objectStore(), BOARD_FILTER_SCHEMA, { project: projectId })
				return Array.isArray(filters) ? filters : []
			} catch (err) {
				console.error('fetchBoardFilters error:', err)
				return []
			}
		},

		/**
		 * Save a filter: POST without an id, PATCH with one. The server sets
		 * the owner (BoardFilterOwnerListener) and refuses a change by anyone
		 * else.
		 *
		 * @param {object} filter The fields, with `id` for an existing filter
		 * @return {Promise<object|null>} The saved filter, or null on failure
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
		 */
		async saveBoardFilter(filter) {
			const { id, ...fields } = filter
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/${BOARD_FILTER_SCHEMA}/${id}`)
				: generateUrl(`/apps/openregister/api/objects/planninq/${BOARD_FILTER_SCHEMA}`)
			try {
				const response = await fetch(url, { method: id ? 'PATCH' : 'POST', headers: buildHeaders(), body: JSON.stringify(fields) })
				return response.ok ? await response.json() : null
			} catch (err) {
				console.error('saveBoardFilter error:', err)
				return null
			}
		},

		/**
		 * Delete a saved filter.
		 *
		 * @param {string} id The filter UUID
		 * @return {Promise<boolean>} Whether it is gone
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
		 */
		async deleteBoardFilter(id) {
			try {
				const url = generateUrl(`/apps/openregister/api/objects/planninq/${BOARD_FILTER_SCHEMA}/${id}`)
				const response = await fetch(url, { method: 'DELETE', headers: buildHeaders() })
				return response.ok
			} catch (err) {
				console.error('deleteBoardFilter error:', err)
				return false
			}
		},

		/**
		 * The code links of a task (integration-code-forge-links). OpenRegister
		 * returns them only to members of the task's project and admins.
		 *
		 * @param {string} taskId The task UUID
		 * @return {Promise<Array>} The links (empty array on error)
		 *
		 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.2
		 */
		async fetchForgeLinks(taskId) {
			try {
				const links = await fetchEvery(this._objectStore(), FORGE_LINK_SCHEMA, { task: taskId })
				return Array.isArray(links) ? links : []
			} catch (err) {
				console.error('fetchForgeLinks error:', err)
				return []
			}
		},

		/**
		 * Add a code link by hand. The server fills the project from the task
		 * and refuses a second link to the same forge item on the task.
		 *
		 * @param {object} link The link fields, with `task`
		 * @return {Promise<{ok: boolean, duplicate: boolean}>} The outcome
		 *
		 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.2
		 */
		async saveForgeLink(link) {
			try {
				const response = await fetch(generateUrl(`/apps/openregister/api/objects/planninq/${FORGE_LINK_SCHEMA}`), {
					method: 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(link),
				})
				if (response.ok) {
					return { ok: true, duplicate: false }
				}
				const body = await response.json().catch(() => ({}))
				return { ok: false, duplicate: forgeLinkRefusal(body) === 'duplicate' }
			} catch (err) {
				console.error('saveForgeLink error:', err)
				return { ok: false, duplicate: false }
			}
		},

		/**
		 * Remove a code link.
		 *
		 * @param {string} id The link UUID
		 * @return {Promise<boolean>} Whether it is gone
		 *
		 * @spec openspec/changes/archive/2026-09-30-integration-code-forge-links/tasks.md#task-2.2
		 */
		async deleteForgeLink(id) {
			try {
				const response = await fetch(generateUrl(`/apps/openregister/api/objects/planninq/${FORGE_LINK_SCHEMA}/${id}`), {
					method: 'DELETE',
					headers: buildHeaders(),
				})
				return response.ok
			} catch (err) {
				console.error('deleteForgeLink error:', err)
				return false
			}
		},

		/**
		 * Read one cross-project view with the user's rights: null when it
		 * does not exist or is not shared with them.
		 *
		 * @param {string} id The view UUID
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
		 */
		async fetchBoardView(id) {
			return readObject(BOARD_VIEW_SCHEMA, id)
		},

		/**
		 * Read one project with the user's rights and without touching the
		 * active project: null when they may not read it or it is gone
		 * (OpenRegister answers both with 404).
		 *
		 * @param {string} id The project UUID
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-2.2
		 */
		async readProject(id) {
			return readObject(PROJECT_SCHEMA, id)
		},

		/**
		 * The cross-project views the user owns or that are shared with them
		 * (the register's read rule decides).
		 *
		 * @return {Promise<Array<object>>}
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		async fetchBoardViews() {
			try {
				const views = await fetchEvery(this._objectStore(), BOARD_VIEW_SCHEMA)
				return Array.isArray(views) ? views : []
			} catch (err) {
				console.error('fetchBoardViews error:', err)
				return []
			}
		},

		/**
		 * Save a view: POST without an id, PATCH with one. The server sets the
		 * owner and refuses a change by anyone else.
		 *
		 * @param {object} view The fields, with `id` for an existing view
		 * @return {Promise<object|null>} The saved view, or null on failure
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		async saveBoardView(view) {
			const { id, ...fields } = view
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/${BOARD_VIEW_SCHEMA}/${id}`)
				: generateUrl(`/apps/openregister/api/objects/planninq/${BOARD_VIEW_SCHEMA}`)
			try {
				const response = await fetch(url, { method: id ? 'PATCH' : 'POST', headers: buildHeaders(), body: JSON.stringify(fields) })
				return response.ok ? await response.json() : null
			} catch (err) {
				console.error('saveBoardView error:', err)
				return null
			}
		},

		/**
		 * Delete a view; its projects and tasks stay.
		 *
		 * @param {string} id The view UUID
		 * @return {Promise<boolean>} Whether it is gone
		 *
		 * @spec openspec/changes/archive/2026-09-30-boards-cross-project-board/tasks.md#task-3.1
		 */
		async deleteBoardView(id) {
			try {
				const url = generateUrl(`/apps/openregister/api/objects/planninq/${BOARD_VIEW_SCHEMA}/${id}`)
				const response = await fetch(url, { method: 'DELETE', headers: buildHeaders() })
				return response.ok
			} catch (err) {
				console.error('deleteBoardView error:', err)
				return false
			}
		},

		/**
		 * Write a release: POST without an id, PATCH with one, so only the
		 * fields given are sent.
		 *
		 * @param {object} release The release fields, with `id` for an existing one
		 * @return {Promise<object|null>} The saved release, or null on failure
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
		 */
		async saveRelease(release) {
			const { id, ...fields } = release
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/${RELEASE_SCHEMA}/${id}`)
				: generateUrl(`/apps/openregister/api/objects/planninq/${RELEASE_SCHEMA}`)
			try {
				const response = await fetch(url, {
					method: id ? 'PATCH' : 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(fields),
				})
				if (!response.ok) {
					return null
				}
				return await response.json()
			} catch (err) {
				console.error('saveRelease error:', err)
				return null
			}
		},

		/**
		 * Mark a release as released. The unfinished tasks are moved, cleared
		 * or kept first, as the member chose; if one of those writes fails the
		 * release is left planned, so nothing is marked shipped with work lost.
		 *
		 * @param {object} release The release
		 * @param {Array<object>} tasks The project's tasks
		 * @param {string} choice 'move', 'clear' or 'keep'
		 * @param {string|null} targetId The release to move the tasks to
		 * @return {Promise<{ok: boolean}>}
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
		 */
		async shipRelease(release, tasks, choice, targetId) {
			const plan = shipPatches(release, tasks, choice, targetId)
			for (const write of plan.tasks) {
				if (!await this.setTaskRelease(write.id, write.patch.release)) {
					return { ok: false }
				}
			}
			const saved = await this.saveRelease({ id: release.id, ...plan.release })
			return { ok: saved !== null }
		},

		/**
		 * Plan a task against a release, or clear it with null. Sends only `release`.
		 *
		 * @param {string} taskId Task UUID
		 * @param {string|null} releaseId Release UUID, or null
		 * @return {Promise<object|null>} The updated task, or null on failure
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.1
		 */
		async setTaskRelease(taskId, releaseId) {
			return this.updateTask(taskId, { release: releaseId || null })
		},

		/**
		 * Link a task to an epic of its own project, or unlink it with null.
		 * Another project's epic, or an epic under an epic, is refused here
		 * and never sent.
		 *
		 * @param {object} task The task
		 * @param {object|null} epic The epic, or null
		 * @return {Promise<{ok: boolean, reason?: string, task?: object}>}
		 *
		 * @spec openspec/changes/backlog-releases-roadmap/tasks.md#task-2.3
		 */
		async setTaskEpic(task, epic) {
			const result = epicPatch(task, epic)
			if (!result.ok) {
				return result
			}
			const updated = await this.updateTask(task.id, result.patch)
			return updated ? { ok: true, task: updated } : { ok: false, reason: 'other' }
		},

		/**
		 * Every phase of a project, scoped by OpenRegister to its members.
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The phases (empty array on error)
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
		 */
		async fetchPhases(projectId) {
			try {
				const phases = await fetchEvery(this._objectStore(), PHASE_SCHEMA, { project: projectId })
				return Array.isArray(phases) ? phases : []
			} catch (err) {
				console.error('fetchPhases error:', err)
				return []
			}
		},

		/**
		 * Write a phase: POST without an id, PATCH with one.
		 *
		 * @param {object} phase The phase fields, with `id` for an existing one
		 * @return {Promise<{ok: boolean, phase?: object, reason?: string}>}
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
		 */
		async savePhase(phase) {
			const { id, ...fields } = phase
			const url = id
				? generateUrl(`/apps/openregister/api/objects/planninq/${PHASE_SCHEMA}/${id}`)
				: generateUrl(`/apps/openregister/api/objects/planninq/${PHASE_SCHEMA}`)
			try {
				const response = await fetch(url, {
					method: id ? 'PATCH' : 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(fields),
				})
				const body = await response.json().catch(() => ({}))
				if (!response.ok) {
					return { ok: false, reason: refusalMessage(response.status, body) }
				}
				return { ok: true, phase: body }
			} catch (err) {
				console.error('savePhase error:', err)
				return { ok: false, reason: 'other' }
			}
		},

		/**
		 * Move a phase up (-1) or down (+1) by swapping its order with its neighbour's.
		 *
		 * @param {Array<object>} phases The project's phases
		 * @param {string} id The phase to move
		 * @param {number} step -1 or +1
		 * @return {Promise<boolean>} Whether both writes succeeded
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
		 */
		async reorderPhase(phases, id, step) {
			const results = await Promise.all(reorderPatches(phases, id, step).map((patch) => this.savePhase(patch)))
			return results.length > 0 && results.every((result) => result.ok)
		},

		/**
		 * Close a phase: its concluding document and status in one write, which
		 * OpenRegister's lifecycle guard accepts only with the file attached.
		 *
		 * @param {string} id The phase
		 * @param {string|number} fileId The concluding document on the phase
		 * @return {Promise<{ok: boolean, phase?: object, reason?: string}>}
		 *
		 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
		 */
		async closePhase(id, fileId) {
			return this.savePhase({ id, ...closePatch(fileId) })
		},

		// ── 2.7 archiveProject ────────────────────────────────────────────

		/**
		 * Run a lifecycle transition on a project through OpenRegister, which
		 * checks the caller's update right and the schema's lifecycle block.
		 *
		 * @param {string} id Project ID
		 * @param {string} action The transition: archive, restore, approve or reject
		 * @param {object} [data] Input values the transition accepts
		 * @return {Promise<object|null>} The saved project, or null on a refusal (message in `error`)
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.3
		 */
		async runProjectTransition(id, action, data) {
			this.error = null
			const { path, body } = transitionRequest(id, action)
			try {
				const response = await fetch(generateUrl(path), {
					method: 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(data ? { ...body, data } : body),
				})
				const answer = await response.json().catch(() => ({}))
				if (!response.ok) {
					this.error = answer?.error || 'transition-error'
					return null
				}
				const updated = { ...answer, id: answer.id || answer['@self']?.id || id }
				this.projects = this.projects.map((p) => (p.id === id ? { ...p, ...updated } : p))
				if (this.activeProject?.id === id) {
					this.activeProject = { ...this.activeProject, ...updated }
				}
				return updated
			} catch (err) {
				this.error = err.message || 'transition-error'
				return null
			}
		},

		/**
		 * The lifecycle actions OpenRegister offers the caller on a project.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<string[]|null>} Action names, or null when the list could not be read
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.4
		 */
		async fetchProjectActions(id) {
			try {
				const url = generateUrl(`/apps/openregister/api/objects/${encodeURIComponent(id)}/available-actions`)
				const response = await fetch(url, { headers: buildHeaders() })
				return response.ok ? actionNames(await response.json()) : null
			} catch {
				return null
			}
		},

		/**
		 * Archive a project through the `archive` transition.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.3
		 */
		async archiveProject(id) {
			const updated = await this.runProjectTransition(id, 'archive')
			if (updated) {
				// Remove from the active list (default filter excludes archived).
				this.projects = this.projects.filter((p) => p.id !== id)
			}
			return updated
		},

		/**
		 * Bring an archived project back through the `restore` transition.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.3
		 */
		async restoreProject(id) {
			return this.runProjectTransition(id, 'restore')
		},

		// ── 2.8 deleteProject ─────────────────────────────────────────────

		/**
		 * Cascade-delete a project and all dependent objects.
		 * Order: timeEntries → tasks → columns → project.
		 *
		 * @param {string} id Project ID
		 * @return {Promise<boolean>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-9
		 */
		async deleteProject(id) {
			this.loading = true
			this.error = null
			try {
				const objectStore = this._objectStore()

				// 1. Fetch tasks for this project.
				const tasks = await fetchEvery(objectStore, TASK_SCHEMA, { project: id })

				// 2. Fetch and delete time entries for each task.
				// NOTE: deletion is sequential and non-transactional. A mid-flight failure
				// (network drop, server error) will leave orphaned objects. The error messages
				// below prompt the user to retry, which will pick up where deletion failed.
				for (const task of tasks) {
					const entries = await fetchEvery(objectStore, TIME_ENTRY_SCHEMA, { task: task.id })
					for (const entry of entries) {
						const ok = await objectStore.deleteObject(TIME_ENTRY_SCHEMA, entry.id)
						if (!ok) {
							showError(t('planninq', 'Could not delete a time entry, so some data may remain. Try deleting the project again.'))
							return false
						}
					}
				}

				// 3. Delete tasks.
				for (const task of tasks) {
					const ok = await objectStore.deleteObject(TASK_SCHEMA, task.id)
					if (!ok) {
						showError(t('planninq', 'Could not delete a task, so some data may remain. Try deleting the project again.'))
						return false
					}
				}

				// 4. Fetch and delete columns.
				const columns = await fetchEvery(objectStore, COLUMN_SCHEMA, { project: id })
				for (const col of columns) {
					const ok = await objectStore.deleteObject(COLUMN_SCHEMA, col.id)
					if (!ok) {
						showError(t('planninq', 'Could not delete a column, so some data may remain. Try deleting the project again.'))
						return false
					}
				}

				// 5. Delete the project itself.
				const ok = await objectStore.deleteObject(PROJECT_SCHEMA, id)
				if (!ok) {
					showError(t('planninq', 'Could not delete the project. Please try again.'))
					return false
				}

				this.projects = this.projects.filter((p) => p.id !== id)
				if (this.activeProject?.id === id) {
					this.activeProject = null
				}

				return true
			} catch (err) {
				this.error = err.message || 'delete-error'
				showError(t('planninq', 'An error occurred during project deletion'))
				return false
			} finally {
				this.loading = false
			}
		},

		// ── 2.9 addMember ─────────────────────────────────────────────────

		/**
		 * Add a Nextcloud user as a project member.
		 *
		 * Uses PATCH (not PUT) so that only `members` is changed server-side.
		 * OR's PUT semantics fill every missing schema property with null,
		 * which would wipe `owner` and make the project uneditable (C2 fix).
		 *
		 * @param {string} projectId Project ID
		 * @param {string} userUid Nextcloud UID to add
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async addMember(projectId, userUid) {
			const project = await this.fetchProject(projectId)
			if (!project) {
				return null
			}

			const members = Array.isArray(project.members) ? [...project.members] : []
			if (members.includes(userUid)) { // Guard against duplicates.
				return project
			}

			members.push(userUid)
			return this.patchProject(projectId, { members })
		},

		/**
		 * Share a project with a Nextcloud group as members: everyone in the
		 * group reads and writes its tasks. PATCHes only `memberGroups`.
		 *
		 * @param {string} projectId Project ID
		 * @param {string} groupId Nextcloud group id
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
		 */
		async addMemberGroup(projectId, groupId) {
			const project = await this.fetchProject(projectId)
			if (!project) {
				return null
			}
			const memberGroups = Array.isArray(project.memberGroups) ? [...project.memberGroups] : []
			if (memberGroups.includes(groupId)) {
				return project
			}
			memberGroups.push(groupId)
			return this.patchProject(projectId, { memberGroups })
		},

		/**
		 * Hand the project to a group, or take it back (gid null). PATCHes only
		 * `ownerGroups`, a list of at most one group.
		 *
		 * @param {string} projectId Project ID
		 * @param {string|null} gid Nextcloud group id, or null for no owning group
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
		 */
		async setOwnerGroup(projectId, gid) {
			const project = await this.fetchProject(projectId)
			if (!project) {
				return null
			}
			const ownerGroups = gid ? [gid] : []
			const before = Array.isArray(project.ownerGroups) ? project.ownerGroups : []
			if (before.length === ownerGroups.length && before.every((entry, index) => entry === ownerGroups[index])) {
				return project
			}
			return this.patchProject(projectId, { ownerGroups })
		},

		/**
		 * Give a person or a group a role on the project, or take it away
		 * (role null). PATCHes only the lists that change.
		 *
		 * @param {string} projectId Project ID
		 * @param {string} id User or group id
		 * @param {'user'|'group'} type Whether the id is a user or a group
		 * @param {'manager'|'member'|'viewer'|null} role The new role
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.4
		 */
		async setMemberRole(projectId, id, type, role) {
			const project = await this.fetchProject(projectId)
			if (!project) {
				return null
			}
			const patch = rolePatch(project, id, type, role)
			if (Object.keys(patch).length === 0) {
				return project
			}
			return this.patchProject(projectId, patch)
		},

		/**
		 * Stop sharing a project with a group. PATCHes only `memberGroups`.
		 *
		 * @param {string} projectId Project ID
		 * @param {string} groupId Nextcloud group id
		 * @return {Promise<object|null>}
		 *
		 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.2
		 */
		async removeMemberGroup(projectId, groupId) {
			const project = await this.fetchProject(projectId)
			if (!project) {
				return null
			}
			const memberGroups = (Array.isArray(project.memberGroups) ? project.memberGroups : []).filter((gid) => gid !== groupId)
			return this.patchProject(projectId, { memberGroups })
		},

		// ── 2.10 getMemberTaskCount ───────────────────────────────────────

		/**
		 * Return the number of tasks currently assigned to a member in a project.
		 * Pure read — no side effects.
		 *
		 * @param {string} projectId Project ID
		 * @param {string} userUid Nextcloud UID to query
		 * @return {Promise<number>} Count of tasks assigned to that member
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async getMemberTaskCount(projectId, userUid) {
			try {
				const objectStore = this._objectStore()
				const tasks = await fetchEvery(objectStore, TASK_SCHEMA, {
					project: projectId,
					assignedTo: userUid,
				})
				return tasks.length
			} catch {
				return 0
			}
		},

		// ── 2.11 removeMember ─────────────────────────────────────────────

		/**
		 * Remove a member from a project (owner removing another member).
		 *
		 * Pure write — does NOT query or return task counts.
		 * Call getMemberTaskCount first if a warning is needed.
		 *
		 * Uses PATCH (not PUT) so that only `members` is changed server-side,
		 * avoiding OR's PUT fill-missing-with-null behaviour that would wipe
		 * `owner` and make the project permanently uneditable (C2 fix).
		 *
		 * For the current user removing themselves, use leaveProject() instead,
		 * which routes through a server-side proxy that bypasses the owner-match
		 * RBAC rule (C3 fix).
		 *
		 * @param {string} projectId Project ID
		 * @param {string} userUid Nextcloud UID to remove
		 * @return {Promise<object|null>} Updated project or null on failure
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async removeMember(projectId, userUid) {
			const project = await this.fetchProject(projectId)
			if (!project) {
				return null
			}

			const patch = rolePatch(project, userUid, 'user', null)

			// Refuse to leave a project with no remaining members — an orphaned
			// project is inaccessible and unrecoverable without admin intervention.
			if (Array.isArray(patch.members) && patch.members.length === 0) {
				throw new Error('Cannot remove the last member from a project')
			}

			return Object.keys(patch).length ? this.patchProject(projectId, patch) : project
		},

		// ── 2.12 leaveProject ────────────────────────────────────────────

		/**
		 * Current user leaves a project via the Planninq server-side proxy (C3 fix).
		 *
		 * Non-owner members cannot update a project through OR's normal write
		 * path because OR RBAC requires `match: { owner: "$userId" }` for updates.
		 * The `/api/projects/{id}/leave` endpoint validates membership and performs
		 * the update with `_rbac: false` (OR's server-trust escape hatch), so only
		 * the `members` array is changed and no other fields are affected.
		 *
		 * The caller is responsible for confirming last-member situations before
		 * calling this action (the server will also reject it with 422).
		 *
		 * @param {string} projectId Project ID
		 * @return {Promise<object|null>} Updated project or null on failure
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-10
		 */
		async leaveProject(projectId) {
			this.loading = true
			this.error = null
			try {
				const url = generateUrl(`/apps/planninq/api/projects/${projectId}/leave`)
				const response = await fetch(url, {
					method: 'POST',
					headers: buildHeaders(),
				})

				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}))
					this.error = errorData?.error || 'leave-error'
					return null
				}

				const updated = await response.json()

				// Remove from local project list (user is no longer a member).
				this.projects = this.projects.filter((p) => p.id !== projectId)
				if (this.activeProject?.id === projectId) {
					this.activeProject = null
				}

				return updated
			} catch (err) {
				this.error = err.message || 'leave-error'
				return null
			} finally {
				this.loading = false
			}
		},

		/**
		 * Get task count for a project (used by delete dialog).
		 *
		 * @param {string} projectId Project ID
		 * @return {Promise<number>}
		 *
		 * @spec openspec/changes/retrofit-2026-05-24-annotate-planix/tasks.md#task-9
		 */
		async getTaskCount(projectId) {
			try {
				const objectStore = this._objectStore()
				const tasks = await fetchEvery(objectStore, TASK_SCHEMA, { project: projectId })
				return tasks.length
			} catch {
				return 0
			}
		},

		// ── 2.13 fetchTasks ───────────────────────────────────────────────

		/**
		 * Fetch all tasks of a project for the kanban board.
		 *
		 * Reads directly from OpenRegister via the shared object store (ADR-022) —
		 * there is no Planninq pass-through controller. OpenRegister scopes the read
		 * to objects the current user may see, so the board only ever shows tasks
		 * of a project the user is a member of (the board view additionally guards
		 * non-member access via `accessDenied`).
		 *
		 * @param {string} projectId Parent project UUID
		 * @return {Promise<Array>} The project's tasks (empty array on error)
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		async fetchTasks(projectId) {
			try {
				const objectStore = this._objectStore()
				const tasks = await fetchEvery(objectStore, TASK_SCHEMA, { project: projectId })
				return Array.isArray(tasks) ? tasks : []
			} catch (err) {
				console.error('fetchTasks error:', err)
				return []
			}
		},

		// ── 2.13a fetchMyTasks ────────────────────────────────────────────

		/**
		 * Every task assigned to the current user or shared with them, in
		 * every project they can read (OpenRegister scopes task reads to the
		 * project's members), open and closed; My tasks filters further.
		 *
		 * One paged read of the task collection, filtered here: `sharedWith`
		 * is an array, and an array filter on the object list is not
		 * portable across databases (see fetchProjects).
		 *
		 * @return {Promise<Array>}
		 *
		 * @spec openspec/changes/portfolio-my-work-dashboard/tasks.md#task-1.1
		 */
		async fetchMyTasks() {
			const uid = this._currentUid()
			if (!uid) {
				return []
			}
			try {
				const tasks = await fetchEvery(this._objectStore(), TASK_SCHEMA, {})
				return (Array.isArray(tasks) ? tasks : []).filter((task) => isMine(task, uid))
			} catch (err) {
				console.error('fetchMyTasks error:', err)
				return []
			}
		},

		// ── 2.13b fetchLabels ─────────────────────────────────────────────

		/**
		 * Fetch every app-wide label, for the board's card chips and its label
		 * filter.
		 *
		 * Reads the `label` schema straight from OpenRegister (ADR-022), NOT the
		 * `/apps/planninq/api/labels` admin endpoint the labels store uses: that
		 * one aggregates usage counts and answers 403 to anyone who is not an
		 * admin, so a board built on it would show no chips at all to the
		 * ordinary project members the board exists for. The schema's own
		 * authorization grants `read` to every authenticated user.
		 *
		 * Labels are app-wide, not per project, so this takes no filter — a task
		 * may reference any of them.
		 *
		 * @return {Promise<Array>} Every label object (empty array on error)
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		async fetchLabels() {
			try {
				const objectStore = this._objectStore()
				const labels = await fetchEvery(objectStore, LABEL_SCHEMA)
				return Array.isArray(labels) ? labels : []
			} catch (err) {
				console.error('fetchLabels error:', err)
				return []
			}
		},

		// ── 2.14 updateTaskStatus ─────────────────────────────────────────

		/**
		 * Move a task to a new status column on the board.
		 *
		 * Uses PATCH (not PUT) so only `status` is changed: OpenRegister's PUT
		 * fills every missing schema property with null, which would wipe
		 * `title`, `project`, and other required fields (same root cause as the
		 * project C2 fix). OpenRegister enforces per-object RBAC on the write, so
		 * a user can only move tasks of a project they are a member of — there is
		 * no app-level `@NoAdminRequired` endpoint to bypass (ADR-005).
		 *
		 * @param {string} taskId Task UUID
		 * @param {string} status New status (one of the task schema's status enum)
		 * @return {Promise<object|null>} The updated task, or null on failure
		 *
		 * @spec openspec/specs/kanban-board.md
		 */
		async updateTaskStatus(taskId, status) {
			try {
				const url = generateUrl(`/apps/openregister/api/objects/planninq/task/${taskId}`)
				const response = await fetch(url, {
					method: 'PATCH',
					headers: buildHeaders(),
					body: JSON.stringify({ status }),
				})
				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}))
					this.error = errorData?.message || 'task-status-error'
					return null
				}
				return await response.json()
			} catch (err) {
				this.error = err.message || 'task-status-error'
				console.error('updateTaskStatus error:', err)
				return null
			}
		},

		// ── 2.14b createTask ──────────────────────────────────────────────

		/**
		 * Create a task through OpenRegister's object API (ADR-022). The
		 * server stamps the members list and refuses a caller who is not a
		 * member of the task's project (ProjectMemberAccessListener).
		 *
		 * A task without a status or priority gets `open` and `normal`. The
		 * server records the caller as `reporter` (TaskReporterGuardListener).
		 *
		 * @param {object} data The task fields; `title` and `project` at least
		 * @return {Promise<object|null>} The created task, or null on failure
		 *
		 * @spec openspec/changes/backlog-list/tasks.md#task-1.2
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-1.1
		 */
		async createTask(data) {
			try {
				const url = generateUrl('/apps/openregister/api/objects/planninq/task')
				const response = await fetch(url, {
					method: 'POST',
					headers: buildHeaders(),
					body: JSON.stringify(withTaskDefaults(data)),
				})
				if (!response.ok) {
					return null
				}
				return await response.json()
			} catch (err) {
				console.error('createTask error:', err)
				return null
			}
		},

		// ── 2.14c deleteTask ──────────────────────────────────────────────

		/**
		 * Delete one task, unless it has logged time.
		 *
		 * A task with time entries is not deleted: the hours belong to the
		 * people who logged them, and the dialog offers to cancel the task
		 * instead. The server refuses the same (TaskReporterGuardListener),
		 * and also refuses anyone but the reporter, the project owner or an
		 * admin; the refusal's code says which.
		 *
		 * @param {string} taskId Task UUID
		 * @return {Promise<{deleted: boolean, reason?: string}>} `reason` is has-time, not-allowed or failed
		 *
		 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-1.2
		 */
		async deleteTask(taskId) {
			try {
				const entries = await fetchEvery(this._objectStore(), TIME_ENTRY_SCHEMA, { task: taskId })
				if (entries.length > 0) {
					return { deleted: false, reason: 'has-time' }
				}
				const url = generateUrl(`/apps/openregister/api/objects/planninq/task/${taskId}`)
				const response = await fetch(url, { method: 'DELETE', headers: buildHeaders() })
				if (!response.ok) {
					const body = await response.json().catch(() => ({}))
					return { deleted: false, reason: deleteRefusal(body) }
				}
				return { deleted: true }
			} catch (err) {
				console.error('deleteTask error:', err)
				return { deleted: false, reason: 'failed' }
			}
		},

		// ── 2.14d subtasks, duplicate, delete a parent ────────────────────

		/**
		 * Copy a task and its subtasks (duplicatePayload: no dates, people or time).
		 *
		 * @param {object}        task     The task.
		 * @param {Array<object>} children Its subtasks.
		 * @return {Promise<object|null>} The copy, or null when the copy itself failed
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.1
		 */
		async duplicateTask(task, children = []) {
			const copy = await this.createTask(duplicatePayload(task, t('planninq', 'Copy of {title}', { title: '{title}' })))
			const copyId = copy?.id ?? copy?.uuid ?? copy?.['@self']?.id
			if (!copyId) {
				return null
			}
			for (const child of children) {
				if (!await this.createTask(duplicatePayload(child, '{title}', copyId))) {
					showError(t('planninq', 'The task was copied, but not all of its subtasks. Please check the copy.'))
					break
				}
			}
			return copy
		},

		/**
		 * Delete a parent task, deleting its subtasks too or keeping them as separate tasks.
		 *
		 * Nothing is deleted when any task that would go has logged time.
		 *
		 * @param {object}        task     The parent.
		 * @param {Array<object>} children Its subtasks.
		 * @param {string}        mode     `delete` or `detach`.
		 * @return {Promise<{deleted: boolean, reason?: string}>}
		 *
		 * @spec openspec/changes/tasks-subtasks-checklist/tasks.md#task-5.2
		 */
		async deleteTaskTree(task, children, mode) {
			const going = mode === 'delete' ? [...children, task] : [task]
			for (const one of going) {
				const entries = await fetchEvery(this._objectStore(), TIME_ENTRY_SCHEMA, { task: one.id })
				if (entries.length > 0) {
					return { deleted: false, reason: 'has-time' }
				}
			}
			for (const child of children) {
				const done = mode === 'delete'
					? (await this.deleteTask(child.id)).deleted
					: !!(await this.updateTask(child.id, { parent: null }))
				if (!done) {
					return { deleted: false, reason: 'failed' }
				}
			}
			return this.deleteTask(task.id)
		},

		// ── 2.15 updateTask ────────────────────────────────────────────────

		/**
		 * Move a task, with its subtasks, to another project's backlog.
		 *
		 * Writes the parent first, then each subtask, so a failure leaves the
		 * parent's children where they were. The server drops the task's
		 * dependency links (TaskDependencyCleanupListener) and re-stamps the
		 * members lists (ProjectMemberAccessListener).
		 *
		 * @param {object}        task     The task to move.
		 * @param {Array<object>} subtasks Its subtasks.
		 * @param {object}        project  The target project.
		 * @return {Promise<object|null>} The moved task, or null on failure
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-2.1
		 */
		async moveTaskToProject(task, subtasks, project) {
			const moved = await this.updateTask(task.id, moveTaskPatch(task, project))
			if (!moved) {
				return null
			}
			for (const child of subtasks || []) {
				if (!await this.updateTask(child.id, moveTaskPatch(child, project, true))) {
					showError(t('planninq', 'Not everything could be moved or copied. Please check the target project.'))
					break
				}
			}
			return moved
		},

		/**
		 * Copy a column and its tasks to another project, as that board's last column.
		 *
		 * @param {object}        column  The source column.
		 * @param {Array<object>} tasks   The column's tasks.
		 * @param {object}        project The target project.
		 * @return {Promise<object|null>} The new column, or null on failure
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
		 */
		async copyColumnToProject(column, tasks, project) {
			const projectId = project?.id ?? project?.uuid
			const targetColumns = await this.fetchColumns(String(projectId))
			const created = await this.saveColumn(columnCopyPayload(column, project, targetColumns))
			const columnId = created?.id ?? created?.uuid ?? created?.['@self']?.id
			if (!columnId) {
				return null
			}
			for (const task of tasks || []) {
				if (!await this.createTask(columnTaskCopyPayload(task, project, columnId))) {
					showError(t('planninq', 'Not everything could be moved or copied. Please check the target project.'))
					break
				}
			}
			return created
		},

		/**
		 * Move a column and its tasks to another project, as that board's last column.
		 *
		 * The column and its tasks are copied first; the originals go once every
		 * copy exists. Their time stays on the project it was booked on.
		 *
		 * @param {object}        column  The source column.
		 * @param {Array<object>} tasks   The column's tasks.
		 * @param {object}        project The target project.
		 * @return {Promise<object|null>} The new column, or null on failure
		 *
		 * @spec openspec/changes/tasks-move-between-projects/tasks.md#task-3.1
		 */
		async moveColumnToProject(column, tasks, project) {
			const projectId = project?.id ?? project?.uuid
			const targetColumns = await this.fetchColumns(String(projectId))
			const created = await this.saveColumn(columnCopyPayload(column, project, targetColumns))
			const columnId = created?.id ?? created?.uuid ?? created?.['@self']?.id
			if (!columnId) {
				return null
			}
			for (const task of tasks || []) {
				const moved = await this.updateTask(task.id, { ...moveTaskPatch(task, project), column: columnId, columnOrder: task.columnOrder ?? 0 })
				if (!moved) {
					showError(t('planninq', 'Not everything could be moved or copied. Please check the target project.'))
					return created
				}
			}
			await this.deleteColumn(column.id)
			return created
		},

		/**
		 * Apply one patch to many tasks, a few requests at a time.
		 *
		 * Every task is tried; one failing PATCH does not stop the rest. A
		 * function patch is called with each task id and returns that task's
		 * fields, so a change that depends on the task (add a label to the ones
		 * it already has) is built per task; an empty result writes nothing.
		 *
		 * @param {Array<string>} ids         The task ids.
		 * @param {object|function(string): object} patch The fields to write, or `(id) => fields`.
		 * @param {number}        concurrency How many requests run at once.
		 * @return {Promise<{done: Array<string>, failed: Array<string>}>} Per-task result
		 *
		 * @spec openspec/changes/tasks-search-and-bulk/tasks.md#task-2.2
		 */
		async bulkUpdateTasks(ids, patch, concurrency = 4) {
			const result = { done: [], failed: [] }
			const queue = [...(ids || [])]
			const worker = async () => {
				while (queue.length) {
					const id = queue.shift()
					const fields = typeof patch === 'function' ? patch(id) : patch
					const updated = Object.keys(fields || {}).length === 0 ? true : await this.updateTask(id, fields)
					;(updated ? result.done : result.failed).push(id)
				}
			}
			await Promise.all(Array.from({ length: Math.min(concurrency, queue.length) }, worker))
			return result
		},

		/**
		 * Patch arbitrary task fields (e.g. `estimatedDuration`).
		 *
		 * Uses PATCH (not PUT) for the same reason as `updateTaskStatus`:
		 * OpenRegister's PUT nulls every unsupplied schema property, wiping
		 * required fields. OR enforces per-object RBAC on the write.
		 *
		 * @param {string} taskId Task UUID
		 * @param {object} patch  Fields to change
		 * @return {Promise<object|null>} The updated task, or null on failure
		 *
		 * @spec openspec/specs/time-tracking.md
		 */
		async updateTask(taskId, patch) {
			try {
				const url = generateUrl(`/apps/openregister/api/objects/planninq/task/${taskId}`)
				const response = await fetch(url, {
					method: 'PATCH',
					headers: buildHeaders(),
					body: JSON.stringify(patch),
				})
				if (!response.ok) {
					const errorData = await response.json().catch(() => ({}))
					this.error = errorData?.message || 'task-update-error'
					return null
				}
				return await response.json()
			} catch (err) {
				this.error = err.message || 'task-update-error'
				console.error('updateTask error:', err)
				return null
			}
		},
	},
})
