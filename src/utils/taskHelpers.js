/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * Pure helpers for task derived UI state.
 *
 * @spec openspec/changes/task-due-date-warning/specs/tasks/spec.md
 */

/**
 * Task statuses that count as "resolved" — a blocker in one of these states
 * no longer blocks anything. Mirrors DependencyService::RESOLVED_STATUSES.
 *
 * @type {string[]}
 */
export const RESOLVED_BLOCKER_STATUSES = ['done', 'cancelled']

/**
 * OpenRegister register slug owning the Planninq schemas.
 *
 * The VALUE moved from `planix` to `planninq` together with the
 * MigrateRegisterSlug repair step, which renames the register ROW. OpenRegister
 * resolves a register by slug and by nothing else, so the literal and the row
 * move in the same release or neither resolves.
 *
 * @type {string}
 */
export const PLANNINQ_REGISTER = 'planninq'

/**
 * Generic CnObjectSidebar tabs that must stay hidden on the task detail
 * collaboration sidebar — Planninq has its own label and sub-task concepts, so
 * the library's generic `tags` / `tasks` tabs would confuse.
 *
 * @type {string[]}
 */
export const TASK_SIDEBAR_HIDDEN_TABS = ['tags', 'tasks']

/**
 * Build the props for the task detail collaboration sidebar (CnObjectSidebar).
 *
 * Pure derived state so the wiring (correct register/schema/objectId, hidden
 * generic tabs, legacy hardcoded-tabs mode) can be unit-tested without mounting
 * the component. The sidebar surfaces the built-in Comments (notes), Files, and
 * Audit Trail tabs over OpenRegister per-object APIs (ADR-022) — there is no
 * Planninq pass-through controller.
 *
 * @param {object} task A task object carrying at least an `id` (UUID).
 * @return {object} Props to spread onto CnObjectSidebar.
 *
 * @spec openspec/specs/task-collaboration/spec.md
 */
export function taskCollaborationSidebarConfig(task) {
	return {
		objectId: (task && task.id) ? String(task.id) : '',
		register: PLANNINQ_REGISTER,
		schema: 'task',
		// `{registerSlug}-{schemaSlug}`, so it tracks the register slug above:
		// it becomes `planninq-task` now that the register itself is re-slugged.
		objectType: 'planninq-task',
		useRegistry: false,
		hiddenTabs: TASK_SIDEBAR_HIDDEN_TABS,
	}
}

/**
 * Build a UUID → status map from a list of task objects.
 *
 * Accepts either flat tasks (`{ id, status }`) or OR-shaped tasks
 * (`{ '@self': { id }, status }`); the first resolvable id wins.
 *
 * @param {Array<object>} tasks The loaded task collection.
 * @return {{[uuid: string]: string}} Map of task UUID → status string.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
export function statusMapFromTasks(tasks) {
	const map = {}
	for (const task of (tasks || []).filter(Boolean)) {
		const id = task.id || task['@self']?.id
		if (id) {
			map[id] = task.status
		}
	}
	return map
}

/**
 * Whether a dependency edge blocks: one without a type (every edge made
 * before types were used) or of type `blocks`. A related link, such as an
 * imported start-to-start link, is drawn but never blocks.
 *
 * @param {object} edge The edge.
 * @return {boolean}
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-1.2
 */
export function isBlockingEdge(edge) {
	return !edge?.type || edge.type === 'blocks'
}

/**
 * Whether an edge blocks and its blocker resolves in the status map with an open status.
 *
 * @param {object} edge The edge.
 * @param {{[uuid: string]: string}} statusById Map of task UUID → status.
 * @return {boolean}
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
function blocksNow(edge, statusById) {
	return Boolean(edge.blocker) && isBlockingEdge(edge) && Object.hasOwn(statusById, edge.blocker) && isOpenStatus(statusById[edge.blocker])
}

/**
 * Whether a blocker status is still open (not done or cancelled).
 *
 * @param {string|undefined} status The blocker's status.
 * @return {boolean}
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
function isOpenStatus(status) {
	return !RESOLVED_BLOCKER_STATUSES.includes(status)
}

/**
 * Whether an edge names the task as blocked.
 *
 * @param {object|null} edge The edge.
 * @param {string} taskId The task UUID.
 * @return {boolean}
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
function blocksTask(edge, taskId) {
	return Boolean(edge) && edge.blocked === taskId
}

/**
 * Decide whether a single task is blocked, given the project's dependency
 * edges and a UUID → status map of all tasks.
 *
 * A task is blocked when at least one edge names it as `blocked` whose
 * `blocker` task resolves in `statusById` and is NOT in a resolved
 * (`done`/`cancelled`) status. Edges whose blocker UUID does not resolve are
 * ignored (tolerant reads — covers out-of-band deletes). Derivation never
 * loops: it inspects each edge once, so any cyclic artifact terminates.
 *
 * @param {string} taskId             UUID of the task to test.
 * @param {Array<object>} edges       Dependency edges (`{ blocker, blocked }`).
 * @param {{[uuid: string]: string}} statusById Map of task UUID → status.
 * @return {boolean} True when the task has at least one open blocker.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
export function isBlocked(taskId, edges, statusById = {}) {
	if (!taskId) {
		return false
	}
	// Tolerant read: an edge whose blocker no longer resolves is ignored.
	return (edges || []).some((edge) => blocksTask(edge, taskId) && blocksNow(edge, statusById))
}

/**
 * Derive the set of blocked task UUIDs for a whole board in one pass.
 *
 * @param {Array<object>} edges       Dependency edges.
 * @param {{[uuid: string]: string}} statusById Map of task UUID → status.
 * @return {string[]} Sorted, de-duplicated UUIDs of blocked tasks.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
export function deriveBlockedTaskIds(edges, statusById = {}) {
	const blocked = (edges || []).filter((edge) => Boolean(edge?.blocked) && blocksNow(edge, statusById))
	return Array.from(new Set(blocked.map((edge) => edge.blocked))).sort()
}

/**
 * List the open blockers of a task — used by the task-detail blocked banner.
 *
 * @param {string} taskId             UUID of the blocked task.
 * @param {Array<object>} edges       Dependency edges.
 * @param {{[uuid: string]: string}} statusById Map of task UUID → status.
 * @return {string[]} UUIDs of the blockers that are still open.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
export function openBlockerIds(taskId, edges, statusById = {}) {
	return (edges || [])
		.filter((edge) => blocksTask(edge, taskId) && Boolean(edge.blocker) && isBlockingEdge(edge))
		.filter((edge) => statusById[edge.blocker] !== undefined && isOpenStatus(statusById[edge.blocker]))
		.map((edge) => edge.blocker)
}

/**
 * Candidate tasks for the dependency picker: same-project tasks excluding the
 * task itself. Pure so the exclusion rule is unit-testable.
 *
 * @param {object} currentTask         The task the picker is opened from (`{ id, project }`).
 * @param {Array<object>} projectTasks All tasks of the same project.
 * @return {Array<object>} The pickable tasks.
 *
 * @spec openspec/changes/task-dependencies/specs/task-dependencies/spec.md
 */
export function dependencyPickerCandidates(currentTask, projectTasks = []) {
	if (!currentTask) {
		return []
	}
	const selfId = currentTask.id || currentTask['@self']?.id
	const project = currentTask.project
	return (projectTasks || []).filter((task) => {
		if (!task) {
			return false
		}
		const id = task.id || task['@self']?.id
		if (id === selfId) {
			return false
		}
		return task.project === project
	})
}

/**
 * Classify a task's due date for badge rendering.
 *
 * Compares the task's `dueDate` against today (date-only — time is ignored).
 *
 * @param {object} task                The task object — must expose `dueDate`.
 * @param {string|Date|null|undefined} task.dueDate ISO-8601 string, Date, or absent.
 * @param {Date} [now]      Optional clock injection for testability.
 * @return {null|'approaching'|'overdue'}
 *         - `null`         when there is no due date.
 *         - `'approaching'` when the due date is today or within the next 2 days.
 *         - `'overdue'`    when the due date is strictly in the past.
 *
 * @spec openspec/specs/kanban-board/spec.md
 */
export function dueDateStatus(task, now = new Date()) {
	if (task === null || task === undefined) {
		return null
	}
	const dueDay = calendarDay(task.dueDate)
	if (dueDay === null) {
		return null
	}

	const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
	const msPerDay = 24 * 60 * 60 * 1000
	return dueBadge(Math.round((dueDay.getTime() - today.getTime()) / msPerDay))
}

/**
 * The calendar day (time dropped) of a due date, or null when it is absent or invalid.
 *
 * @param {string|Date|null|undefined} raw The due date.
 * @return {Date|null}
 *
 * @spec openspec/specs/kanban-board/spec.md
 */
function calendarDay(raw) {
	if ([null, undefined, ''].includes(raw)) {
		return null
	}
	// A stored `YYYY-MM-DD` is a calendar day, not an instant: `new Date('2026-06-15')`
	// is UTC midnight, which west of UTC is the evening before (tasks-dates decision 3).
	const day = typeof raw === 'string' ? localDate(raw) : null
	if (day !== null) {
		return day
	}
	const due = raw instanceof Date ? raw : new Date(raw)
	return Number.isNaN(due.getTime()) ? null : new Date(due.getFullYear(), due.getMonth(), due.getDate())
}

/**
 * A `YYYY-MM-DD` string as local midnight of that day, or null for any other string.
 *
 * @param {string} raw The stored date.
 * @return {Date|null}
 *
 * @spec openspec/changes/tasks-dates/specs/task-dates/spec.md#requirement-a-date-only-due-date-keeps-its-day-in-every-time-zone
 */
export function localDate(raw) {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw.trim())
	if (match === null) {
		return null
	}
	const [year, month, day] = [Number(match[1]), Number(match[2]) - 1, Number(match[3])]
	const date = new Date(year, month, day)
	if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
		return null
	}
	return date
}

/**
 * Refuse a start date after the due date (tasks-dates decision 2).
 *
 * Either date may be empty: an order needs both. Answers the key of the
 * message to show, or null when the dates are in order.
 *
 * @param {string|null|undefined} startDate The start date, `YYYY-MM-DD`.
 * @param {string|null|undefined} dueDate The due date, `YYYY-MM-DD`.
 * @return {null|'startAfterDue'}
 *
 * @spec openspec/changes/tasks-dates/specs/task-dates/spec.md#requirement-a-project-member-can-set-a-start-date
 */
export function validateTaskDates(startDate, dueDate) {
	const start = calendarDay(startDate)
	const due = calendarDay(dueDate)
	if (start === null || due === null) {
		return null
	}
	return start.getTime() > due.getTime() ? 'startAfterDue' : null
}

/**
 * The badge for a due date this many days from today.
 *
 * @param {number} diffDays Days until the due date, negative when past.
 * @return {null|'approaching'|'overdue'}
 *
 * @spec openspec/specs/kanban-board/spec.md
 */
function dueBadge(diffDays) {
	if (diffDays < 0) {
		return 'overdue'
	}
	return diffDays <= 2 ? 'approaching' : null
}

/**
 * The kanban board's columns, in display order. One lane per task `status`
 * value (the task schema's status enum is the single source of truth for the
 * board lanes).
 *
 * @type {string[]}
 */
export const BOARD_STATUSES = ['open', 'in_progress', 'blocked', 'done', 'cancelled']

/**
 * Group a flat list of tasks into the board's status columns.
 *
 * Every column key in `statuses` is always present in the result so empty
 * columns render gracefully. A task whose `status` is not a known column falls
 * back to the first column (`open` by default), so an out-of-band / legacy
 * status never silently drops a card off the board.
 *
 * @param {Array<object>} tasks        The project's tasks.
 * @param {string[]} [statuses]        Ordered status column keys.
 * @return {{[status: string]: Array<object>}} Map of status → tasks in that column.
 *
 * @spec openspec/specs/kanban-board/spec.md
 */
export function groupTasksByStatus(tasks, statuses = BOARD_STATUSES) {
	const fallback = statuses[0]
	const grouped = Object.fromEntries(statuses.map((status) => [status, []]))
	for (const task of (tasks || []).filter(Boolean)) {
		const status = grouped[task.status] ? task.status : fallback
		grouped[status].push(task)
	}
	return grouped
}

/**
 * A task's links split into what blocks it, what it blocks and what is only
 * related (relates, duplicates and the other non-blocking types, either way).
 *
 * @param {string} taskId The task UUID.
 * @param {Array<object>} edges The dependency edges.
 * @return {{blockedBy: Array<object>, blocks: Array<object>, related: Array<object>}}
 *
 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.2
 */
export function linkGroups(taskId, edges) {
	const groups = { blockedBy: [], blocks: [], related: [] }
	for (const edge of edges || []) {
		if (namesTask(edge, taskId)) {
			groups[linkGroupOf(edge, taskId)].push(linkOf(edge, taskId))
		}
	}
	return groups
}

/**
 * Whether an edge names the task on either end.
 *
 * @param {object|null} edge The edge.
 * @param {string} taskId The task UUID.
 * @return {boolean}
 *
 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.2
 */
function namesTask(edge, taskId) {
	return Boolean(edge) && (edge.blocked === taskId || edge.blocker === taskId)
}

/**
 * The group of linkGroups an edge of the task belongs to.
 *
 * @param {object} edge The edge, which names the task.
 * @param {string} taskId The task UUID.
 * @return {'blockedBy'|'blocks'|'related'}
 *
 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.2
 */
function linkGroupOf(edge, taskId) {
	if (!isBlockingEdge(edge)) {
		return 'related'
	}
	return edge.blocked === taskId ? 'blockedBy' : 'blocks'
}

/**
 * The link row of an edge, seen from the task.
 *
 * @param {object} edge The edge, which names the task.
 * @param {string} taskId The task UUID.
 * @return {{edgeId: string, otherId: string, type: string}}
 *
 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.2
 */
function linkOf(edge, taskId) {
	return { edgeId: edge.id, otherId: edge.blocker === taskId ? edge.blocked : edge.blocker, type: edge.type || 'blocks' }
}

/**
 * The link the picker creates: for "Blocked by" the picked task blocks this
 * one; for a related link this task comes first.
 *
 * @param {string} taskId This task.
 * @param {string} pickedId The picked task.
 * @param {string} type The link type.
 * @return {{blocker: string, blocked: string, type: string}}
 *
 * @spec openspec/changes/planning-dependencies-on-task-page/tasks.md#task-3.2
 */
export function newLinkFor(taskId, pickedId, type = 'blocks') {
	if (type === 'blocks') {
		return { blocker: pickedId, blocked: taskId, type }
	}
	return { blocker: taskId, blocked: pickedId, type }
}
