// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Pure helpers for the live project board (board-live-updates): a fresh read
 * of the project's tasks replaces the board's cards, except the card the user
 * is dragging, which keeps the version under the pointer until it is dropped.
 *
 * @spec openspec/changes/board-live-updates/tasks.md#task-1.2
 */

/**
 * The id a task is matched by across reads.
 *
 * @param {object|null|undefined} task A task row
 * @return {string} Its id, '' when it has none
 */
export function liveTaskId(task) {
	const id = task?.id ?? task?.uuid ?? task?.['@self']?.id
	return id === undefined || id === null ? '' : String(id)
}

/**
 * Merge a fresh read of the project's tasks into the board.
 *
 * Without a held card the fresh read is the board: moved cards change lane,
 * new tasks appear and deleted ones disappear. With a held card (the one being
 * dragged) every other card follows the fresh read, while the held card keeps
 * its current version and stays on the board even when the read changed or
 * dropped it; `held` then says that an update for it waits for the drop.
 *
 * @param {Array<object>} current The cards on the board now
 * @param {Array<object>} fresh   The project's tasks as just read
 * @param {string}        [heldId] Id of the card being dragged, '' for none
 * @return {{tasks: Array<object>, held: boolean}} The new cards, and whether the held card has an update waiting
 * @spec openspec/changes/board-live-updates/tasks.md#task-1.2
 */
export function mergeLiveTasks(current, fresh, heldId = '') {
	const rows = Array.isArray(fresh) ? fresh : []
	const held = heldId ? (Array.isArray(current) ? current : []).find((task) => liveTaskId(task) === heldId) : undefined
	if (!held) {
		return { tasks: rows, held: false }
	}

	const freshHeld = rows.find((task) => liveTaskId(task) === heldId)
	const tasks = freshHeld
		? rows.map((task) => (liveTaskId(task) === heldId ? held : task))
		: [...rows, held]

	return { tasks, held: true }
}
