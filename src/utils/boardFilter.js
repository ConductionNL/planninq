/**
 * The board filter (boards-filters): assignee, label, priority and due date,
 * each "is" or "is not" one or more values, kept in the page address.
 *
 * A filter is `{ assignee, label, priority, due }`, each `{ op, values }`
 * with `op` `is` or `isNot`. An empty `values` list leaves the dimension
 * out. `matchesFilter` is pure; "me" resolves to the current user and
 * "unassigned" matches a task nobody is responsible for.
 *
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
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
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
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
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
 */
export function normaliseFilter(value) {
	const filter = emptyFilter()
	for (const dimension of FILTER_DIMENSIONS) {
		const entry = value?.[dimension]
		if (!entry || !Array.isArray(entry.values)) {
			continue
		}
		filter[dimension] = {
			op: entry.op === 'isNot' ? 'isNot' : 'is',
			values: [...new Set(entry.values.map((item) => String(item ?? '')).filter((item) => item !== ''))],
		}
	}
	return filter
}

/**
 * How many dimensions the filter narrows.
 *
 * @param {object} filter The filter.
 * @return {number}
 *
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
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
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
 */
export function dueValue(task, today = new Date()) {
	const due = dayOf(task?.dueDate)
	if (due === null) {
		return 'none'
	}
	if (isOverdue(task, today)) {
		return 'overdue'
	}
	const start = dayOf(today)
	const sunday = new Date(start)
	sunday.setDate(start.getDate() + ((7 - start.getDay()) % 7))
	return due >= start && due <= sunday ? 'thisWeek' : ''
}

/**
 * The values a task has on one dimension.
 *
 * @param {object} task      The task.
 * @param {string} dimension The dimension.
 * @param {string} uid       The current user.
 * @param {Date}   today     Today.
 * @return {Array<string>}
 */
function taskValues(task, dimension, uid, today) {
	switch (dimension) {
	case 'assignee': {
		const responsible = String(task?.assignedTo ?? '')
		if (responsible === '') {
			return [UNASSIGNED]
		}
		return responsible === uid ? [responsible, ME] : [responsible]
	}
	case 'label':
		return Array.isArray(task?.labels) ? task.labels.map((id) => String(id ?? '')) : []
	case 'priority':
		return [task?.priority || 'normal']
	default:
		return [dueValue(task, today)]
	}
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
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
 */
export function matchesFilter(task, filter, uid, today = new Date()) {
	for (const dimension of FILTER_DIMENSIONS) {
		const { op, values } = filter?.[dimension] || {}
		if (!values || values.length === 0) {
			continue
		}
		const has = taskValues(task, dimension, String(uid ?? ''), today).some((value) => values.includes(value))
		if (has !== (op !== 'isNot')) {
			return false
		}
	}
	return true
}

/**
 * The page-address form of a filter: `assignee=me`, `priority!=low,normal`.
 *
 * @param {object} filter The filter.
 * @return {object} Query parameters.
 *
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
 */
export function encodeFilter(filter) {
	const query = {}
	for (const dimension of FILTER_DIMENSIONS) {
		const { op, values } = filter?.[dimension] || {}
		if (values && values.length > 0) {
			query[op === 'isNot' ? dimension + '!' : dimension] = values.join(',')
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
 * @spec openspec/changes/boards-filters/tasks.md#task-1.1
 */
export function decodeFilter(query) {
	const raw = {}
	for (const dimension of FILTER_DIMENSIONS) {
		const negated = query?.[dimension + '!']
		const plain = query?.[dimension]
		const value = negated ?? plain
		if (typeof value === 'string' && value !== '') {
			raw[dimension] = { op: negated !== undefined ? 'isNot' : 'is', values: value.split(',') }
		}
	}
	return normaliseFilter(raw)
}

/**
 * The route query with the filter replaced: filter keys removed, then the new ones set.
 *
 * @param {object} query  The current query.
 * @param {object} filter The new filter.
 * @return {object}
 *
 * @spec openspec/changes/boards-filters/tasks.md#task-2.1
 */
export function withFilterQuery(query, filter) {
	const next = { ...query }
	for (const dimension of FILTER_DIMENSIONS) {
		delete next[dimension]
		delete next[dimension + '!']
	}
	return { ...next, ...encodeFilter(filter) }
}
