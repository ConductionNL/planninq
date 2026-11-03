/**
 * The board filter (boards-filters): assignee, label, priority and due date,
 * each "is" or "is not" one or more values, kept in the page address.
 *
 * A filter is `{ assignee, label, priority, due }`, each `{ op, values }`
 * with `op` `is` or `isNot`. An empty `values` list leaves the dimension
 * out. `matchesFilter` is pure; "me" resolves to the current user and
 * "unassigned" matches a task nobody is responsible for.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
import { isOverdue } from './myWork.js'

/** The filter dimensions, in the bar's order. */
export const FILTER_DIMENSIONS = ['assignee', 'label', 'priority', 'due']

/** The due-date values. */
export const DUE_VALUES = ['overdue', 'thisWeek', 'none']

/** The assignee values that are not a user id. */
export const ME = 'me'
export const UNASSIGNED = 'unassigned'

/**
 * A filter that shows everything.
 *
 * @return {object}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
export function emptyFilter() {
	return Object.fromEntries(FILTER_DIMENSIONS.map((dimension) => [dimension, { op: 'is', values: [] }]))
}

/**
 * A filter read from anything: unknown dimensions and operators dropped,
 * values as distinct non-empty strings.
 *
 * @param {object} value A stored or decoded filter.
 * @return {object}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
export function normaliseFilter(value) {
	const filter = emptyFilter()
	for (const dimension of FILTER_DIMENSIONS) {
		const entry = normaliseEntry(value?.[dimension])
		if (entry) {
			filter[dimension] = entry
		}
	}
	return filter
}

/**
 * One dimension of a filter read from anything, or null when it has no values list.
 *
 * @param {object|null|undefined} entry The stored or decoded `{op, values}`.
 * @return {{op: string, values: Array<string>}|null}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
function normaliseEntry(entry) {
	if (!entry || !Array.isArray(entry.values)) {
		return null
	}
	return {
		op: entry.op === 'isNot' ? 'isNot' : 'is',
		values: [...new Set(entry.values.map((item) => String(item ?? '')).filter((item) => item !== ''))],
	}
}

/**
 * How many dimensions the filter narrows.
 *
 * @param {object} filter The filter.
 * @return {number}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
export function activeDimensions(filter) {
	return FILTER_DIMENSIONS.filter((dimension) => (filter?.[dimension]?.values || []).length > 0).length
}

/**
 * The start of a day, or null for an empty or invalid value.
 *
 * @param {string|Date|null} value A date.
 * @return {Date|null}
 */
function dayOf(value) {
	if (!value) {
		return null
	}
	const date = value instanceof Date ? new Date(value) : new Date(String(value).slice(0, 10) + 'T00:00:00')
	if (Number.isNaN(date.getTime())) {
		return null
	}
	date.setHours(0, 0, 0, 0)
	return date
}

/**
 * The due-date value a task has: overdue, this week (today up to and including Sunday), none, or '' (later).
 *
 * @param {object} task  The task.
 * @param {Date}   today Today.
 * @return {string}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
export function dueValue(task, today = new Date()) {
	const due = dayOf(task?.dueDate)
	if (due === null) {
		return 'none'
	}
	if (isOverdue(task, today)) {
		return 'overdue'
	}
	return isThisWeek(due, today) ? 'thisWeek' : ''
}

/**
 * Whether a day lies from today up to and including Sunday.
 *
 * @param {Date} due The day.
 * @param {Date} today Today.
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
function isThisWeek(due, today) {
	const start = dayOf(today)
	const sunday = new Date(start)
	sunday.setDate(start.getDate() + ((7 - start.getDay()) % 7))
	return due >= start && due <= sunday
}

/**
 * The values a task has on the assignee dimension: its responsible, plus "me" when that is the user, or "unassigned".
 *
 * @param {object} task The task.
 * @param {{uid: string}} context The current user.
 * @return {Array<string>}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
function assigneeValues(task, context) {
	const responsible = String(task?.assignedTo ?? '')
	if (responsible === '') {
		return [UNASSIGNED]
	}
	return responsible === context.uid ? [responsible, ME] : [responsible]
}

/**
 * Per dimension, the values a task has on it.
 */
const DIMENSION_VALUES = {
	assignee: assigneeValues,
	label: (task) => (Array.isArray(task?.labels) ? task.labels.map((id) => String(id ?? '')) : []),
	priority: (task) => [task?.priority || 'normal'],
	due: (task, context) => [dueValue(task, context.today)],
}

/**
 * Whether a task passes the filter: every narrowed dimension must hold (is)
 * or must not hold (is not) one of its values.
 *
 * @param {object} task   The task.
 * @param {object} filter The filter.
 * @param {string} uid    The current user.
 * @param {Date}   [today] Today.
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
export function matchesFilter(task, filter, uid, today = new Date()) {
	const context = { uid: String(uid ?? ''), today }
	return FILTER_DIMENSIONS.every((dimension) => passes(task, { dimension, rule: filter?.[dimension], context }))
}

/**
 * Whether a task passes one dimension of the filter; a dimension without values passes.
 *
 * @param {object} task The task.
 * @param {{dimension: string, rule: object|undefined, context: {uid: string, today: Date}}} check The dimension, its `{op, values}` and the user and day.
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
function passes(task, check) {
	const { op, values } = check.rule || {}
	if (!values || values.length === 0) {
		return true
	}
	const has = DIMENSION_VALUES[check.dimension](task, check.context).some((value) => values.includes(value))
	return has === (op !== 'isNot')
}

/**
 * The page-address form of a filter: `assignee=me`, `priority!=low,normal`.
 *
 * @param {object} filter The filter.
 * @return {object} Query parameters.
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
export function encodeFilter(filter) {
	const query = {}
	for (const dimension of FILTER_DIMENSIONS) {
		const { op, values } = filter?.[dimension] || {}
		if (values && values.length > 0) {
			query[queryKey(dimension, op)] = values.join(',')
		}
	}
	return query
}

/**
 * A filter read from the page address.
 *
 * @param {object} query The route query.
 * @return {object}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
export function decodeFilter(query) {
	const raw = {}
	for (const dimension of FILTER_DIMENSIONS) {
		const entry = queryEntry(query, dimension)
		if (entry) {
			raw[dimension] = entry
		}
	}
	return normaliseFilter(raw)
}

/**
 * The page-address key of a dimension: with `!` for "is not".
 *
 * @param {string} dimension The dimension.
 * @param {string} op The operator.
 * @return {string}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
function queryKey(dimension, op) {
	return op === 'isNot' ? dimension + '!' : dimension
}

/**
 * One dimension read from the page address, the negated key first, or null when it is not set.
 *
 * @param {object|null|undefined} query The route query.
 * @param {string} dimension The dimension.
 * @return {{op: string, values: Array<string>}|null}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-1.1
 */
function queryEntry(query, dimension) {
	const negated = query?.[dimension + '!']
	const value = negated ?? query?.[dimension]
	if (typeof value !== 'string' || value === '') {
		return null
	}
	return { op: negated !== undefined ? 'isNot' : 'is', values: value.split(',') }
}

/**
 * The route query with the filter replaced: filter keys removed, then the new ones set.
 *
 * @param {object} query  The current query.
 * @param {object} filter The new filter.
 * @return {object}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-2.1
 */
export function withFilterQuery(query, filter) {
	const next = { ...query }
	for (const dimension of FILTER_DIMENSIONS) {
		delete next[dimension]
		delete next[dimension + '!']
	}
	return { ...next, ...encodeFilter(filter) }
}

/**
 * The fields a saved filter is written with.
 *
 * @param {object}  input         The dialog input.
 * @param {string}  input.name    The name.
 * @param {boolean} input.shared  Whether project members see it.
 * @param {object}  input.filter  The active filter.
 * @param {string}  input.project The project UUID.
 * @return {object}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
 */
export function savedFilterPayload({ name, shared, filter, project }) {
	return { name: String(name ?? '').trim(), shared: !!shared, project, criteria: normaliseFilter(filter) }
}

/**
 * Whether a user may rename or delete a saved filter: its owner or an admin.
 * The server enforces the same rule.
 *
 * @param {object}      saved The saved filter.
 * @param {object|null} user  The current user (`uid`, `isAdmin`).
 * @return {boolean}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
 */
export function canManageSavedFilter(saved, user) {
	if (!user?.uid) {
		return false
	}
	return user.isAdmin === true || saved?.owner === user.uid
}

/**
 * The filter a saved filter applies, with labels that no longer exist
 * dropped, and whether anything was dropped.
 *
 * @param {object}        saved    The saved filter.
 * @param {Array<string>} labelIds The ids of the existing labels.
 * @return {{filter: object, changed: boolean}}
 *
 * @spec openspec/changes/archive/2026-09-30-boards-filters/tasks.md#task-3.2
 */
export function applySavedFilter(saved, labelIds) {
	const filter = normaliseFilter(saved?.criteria)
	const known = new Set(labelIds || [])
	const kept = filter.label.values.filter((id) => known.has(id))
	const changed = kept.length !== filter.label.values.length
	filter.label = { ...filter.label, values: kept }
	return { filter, changed }
}
