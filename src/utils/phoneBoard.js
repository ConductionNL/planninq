// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Pure helpers for the board on a phone (platform-mobile-web): under
 * PHONE_MAX_WIDTH the board shows one column at a time, chosen from a
 * switcher that names every column with its card count.
 *
 * @spec openspec/changes/archive/2026-09-30-platform-mobile-web/tasks.md#task-1.1
 */

/**
 * The width in CSS pixels below which the phone layouts apply. It matches
 * the `@media (max-width: 600px)` rules in the views and `src/assets/app.css`.
 *
 * @type {number}
 */
export const PHONE_MAX_WIDTH = 600

/**
 * The switcher's buttons: every column in board order with its card count.
 *
 * @param {Array<{id: string, title: string}>} columns The board's columns, in order
 * @param {{[columnId: string]: Array}} lanes Column id to the cards it shows
 * @return {Array<{id: string, title: string, count: number}>} One entry per column
 * @spec openspec/changes/archive/2026-09-30-platform-mobile-web/tasks.md#task-1.1
 */
export function columnSwitcher(columns, lanes) {
	return (columns || []).map((column) => ({
		id: column.id,
		title: column.title,
		count: ((lanes || {})[column.id] || []).length,
	}))
}

/**
 * The column a phone shows: the chosen one while it exists, else the first.
 *
 * @param {Array<{id: string}>} columns The board's columns, in order
 * @param {string|null} chosenId The column the user picked, if any
 * @return {string|null} The column id, or null on a board without columns
 * @spec openspec/changes/archive/2026-09-30-platform-mobile-web/tasks.md#task-1.1
 */
export function phoneColumnId(columns, chosenId) {
	const list = columns || []
	if (chosenId && list.some((column) => column.id === chosenId)) {
		return chosenId
	}
	return list.length ? list[0].id : null
}
