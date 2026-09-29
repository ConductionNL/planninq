/**
 * Card colours and swimlanes on the board (boards-card-display).
 *
 * Pure helpers: the colour edge of a card, the swimlane groups of a board, the
 * PATCH a drop into another swimlane writes, and the per-person view choice.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-2.1
 */
import { PRIORITIES, responsiblePatch } from './taskPeople.js'

/** Colour modes. */
export const COLOUR_MODES = ['none', 'label', 'priority']

/** Swimlane fields. */
export const GROUP_FIELDS = ['none', 'assignee', 'priority', 'epic']

/** The key of the "no value" swimlane. */
export const NO_VALUE = ''

/** Priority edge tokens; normal and low carry none. */
const PRIORITY_EDGE = {
	urgent: 'var(--color-error)',
	high: 'var(--color-warning)',
}

/**
 * The id of a reference, whichever shape it came in.
 *
 * @param {object|string|null} value The reference.
 * @return {string}
 */
function refId(value) {
	if (value === null || value === undefined) {
		return ''
	}
	return typeof value === 'object' ? String(value.id ?? value.uuid ?? '') : String(value)
}

/**
 * A valid view choice, whatever was stored.
 *
 * @param {object|null} stored The stored choice.
 * @return {{colour: string, group: string}}
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-3.1
 */
export function normaliseView(stored) {
	return {
		colour: COLOUR_MODES.includes(stored?.colour) ? stored.colour : 'none',
		group: GROUP_FIELDS.includes(stored?.group) ? stored.group : 'none',
	}
}

/**
 * The colour of a card's edge, or null for none.
 *
 * @param {object} task The task.
 * @param {Array<object>} labels The task's labels, in the task's order.
 * @param {string} mode 'none', 'label' or 'priority'.
 * @return {string|null}
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-1.1
 */
export function cardEdge(task, labels, mode) {
	if (mode === 'label') {
		return labels?.[0]?.color || null
	}
	if (mode === 'priority') {
		return PRIORITY_EDGE[task?.priority] || null
	}
	return null
}

/**
 * The value of a task in a swimlane field.
 *
 * @param {object} task The task.
 * @param {string} field 'assignee', 'priority' or 'epic'.
 * @return {string}
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-2.1
 */
export function laneValue(task, field) {
	if (field === 'assignee') {
		return String(task?.assignedTo || '')
	}
	if (field === 'priority') {
		return String(task?.priority || 'normal')
	}
	if (field === 'epic') {
		return refId(task?.epic)
	}
	return NO_VALUE
}

/**
 * The board's swimlanes: one group per value, in a stable order, the group
 * without a value last. With field 'none' it is one group holding every task.
 *
 * @param {Array<object>} tasks The board's tasks.
 * @param {string} field The grouping field.
 * @param {object} names Value to display name (people, epic titles).
 * @return {Array<{key: string, name: string, tasks: Array<object>}>}
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-2.1
 */
export function groupTasksBySwimlane(tasks, field, names = {}) {
	if (!GROUP_FIELDS.includes(field) || field === 'none') {
		return [{ key: NO_VALUE, name: '', tasks: [...(tasks || [])] }]
	}
	const groups = new Map()
	for (const task of tasks || []) {
		const key = laneValue(task, field)
		if (!groups.has(key)) {
			groups.set(key, [])
		}
		groups.get(key).push(task)
	}
	const keys = [...groups.keys()].filter((key) => key !== NO_VALUE)
	if (field === 'priority') {
		keys.sort((a, b) => PRIORITIES.indexOf(a) - PRIORITIES.indexOf(b))
	} else {
		keys.sort((a, b) => String(names[a] || a).localeCompare(String(names[b] || b)))
	}
	const out = keys.map((key) => ({ key, name: names[key] || key, tasks: groups.get(key) }))
	if (groups.has(NO_VALUE)) {
		out.push({ key: NO_VALUE, name: '', tasks: groups.get(NO_VALUE) })
	}
	return out
}

/**
 * The extra PATCH fields a drop into swimlane `key` writes, or a refusal.
 *
 * @param {object} task The dropped task.
 * @param {string} field The grouping field.
 * @param {string} key The target swimlane.
 * @return {{ok: true, patch: object}|{ok: false, reason: string}}
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-2.3
 */
export function swimlanePatch(task, field, key) {
	if (field === 'none' || laneValue(task, field) === key) {
		return { ok: true, patch: {} }
	}
	if (field === 'epic') {
		return { ok: false, reason: 'epic' }
	}
	if (field === 'assignee') {
		return { ok: true, patch: responsiblePatch(task, key) }
	}
	return { ok: true, patch: { priority: key || 'normal' } }
}

/**
 * Epic id to title, for the names of epic swimlanes.
 *
 * @param {Array<object>} tasks The project's tasks.
 * @return {object}
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-2.1
 */
export function epicTitles(tasks) {
	const titles = {}
	for (const task of tasks || []) {
		if (task?.issueType === 'epic' && task.id) {
			titles[String(task.id)] = task.title || ''
		}
	}
	return titles
}

/**
 * The fields a card created in a swimlane gets, so it lands in that row.
 *
 * @param {string} field The grouping field.
 * @param {string} key   The row it is created in.
 * @return {object}
 *
 * @spec openspec/changes/boards-card-display/tasks.md#task-2.3
 */
export function newCardFields(field, key) {
	if (field === 'epic') {
		return key ? { epic: key } : {}
	}
	const result = swimlanePatch({}, field, key)
	return result.ok ? result.patch : {}
}
