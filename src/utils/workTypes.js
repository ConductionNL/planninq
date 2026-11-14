/**
 * The admin's list of work types, as the settings carry it.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */

/**
 * The work type names from the stored setting: a JSON list, or a list already.
 * Anything else, blank names and repeats read as nothing.
 *
 * @param {string|Array|undefined} raw The `work_types` setting.
 * @return {Array<string>}
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.3
 */
export function workTypeNames(raw) {
	let list = raw
	if (typeof raw === 'string') {
		try {
			list = JSON.parse(raw)
		} catch {
			return []
		}
	}
	if (!Array.isArray(list)) {
		return []
	}
	const names = []
	for (const name of list) {
		if (typeof name === 'string' && name.trim() !== '' && !names.some((n) => n.toLowerCase() === name.trim().toLowerCase())) {
			names.push(name.trim())
		}
	}
	return names
}

/**
 * The list after renaming one type; the new name keeps the old one's place.
 *
 * @param {Array<string>} names The current names.
 * @param {string} from The name to rename.
 * @param {string} to The new name.
 * @return {Array<string>|null} The new list, or null when the new name is blank or already taken.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */
export function renameWorkType(names, from, to) {
	const target = (to || '').trim()
	if (target === '' || names.some((n) => n !== from && n.toLowerCase() === target.toLowerCase())) {
		return null
	}
	return names.map((n) => (n === from ? target : n))
}

/**
 * The list with one more type at the end.
 *
 * @param {Array<string>} names The current names.
 * @param {string} name The name to add.
 * @return {Array<string>|null} The new list, or null when the name is blank or already there.
 *
 * @spec openspec/changes/time-timer-and-work-type/tasks.md#task-2.1
 */
export function addWorkType(names, name) {
	const target = (name || '').trim()
	if (target === '' || names.some((n) => n.toLowerCase() === target.toLowerCase())) {
		return null
	}
	return [...names, target]
}
