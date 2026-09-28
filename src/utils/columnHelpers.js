/**
 * Board-column helpers: the pure derivations the board and the column
 * management screens share (boards-configurable-columns).
 *
 * A board lane is a `column` object of the project; a task sits in the lane its
 * `column` references, in `columnOrder` order. A task without a column is in
 * the backlog, not on the board.
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
 */

/** Gap between two neighbouring cards' `columnOrder`, so most moves write one card. */
export const ORDER_STEP = 1000

/**
 * The id of an OpenRegister object, wherever the response put it.
 *
 * @param {object} object The object.
 * @return {string|undefined}
 */
function idOf(object) {
	return object?.id ?? object?.uuid ?? object?.['@self']?.id
}

/**
 * The columns in lane order.
 *
 * @param {Array<object>} columns The project's columns.
 * @return {Array<object>}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
 */
export function sortColumns(columns = []) {
	return [...(columns || [])].sort((a, b) => (Number(a?.order) || 0) - (Number(b?.order) || 0))
}

/**
 * The cards of a lane in their stored order.
 *
 * @param {Array<object>} tasks The lane's tasks.
 * @return {Array<object>}
 */
function sortCards(tasks) {
	return [...tasks].sort((a, b) => (Number(a?.columnOrder) || 0) - (Number(b?.columnOrder) || 0))
}

/**
 * Group tasks into the board's lanes.
 *
 * Every column id is a key, so an empty lane renders. A task without a column
 * is left out (it is in the backlog). A task whose column no longer exists
 * goes to the first lane, so no card drops off the board.
 *
 * @param {Array<object>} tasks   The project's tasks.
 * @param {Array<object>} columns The project's columns.
 * @return {{[columnId: string]: Array<object>}}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.1
 */
export function groupTasksByColumn(tasks = [], columns = []) {
	const lanes = sortColumns(columns)
	const grouped = {}
	for (const column of lanes) {
		grouped[idOf(column)] = []
	}
	const first = lanes.length ? idOf(lanes[0]) : null
	for (const task of tasks || []) {
		if (!task || !task.column) {
			continue
		}
		const key = Object.hasOwn(grouped, task.column) ? task.column : first
		if (key !== null) {
			grouped[key].push(task)
		}
	}
	for (const key of Object.keys(grouped)) {
		grouped[key] = sortCards(grouped[key])
	}
	return grouped
}

/**
 * The status a card gets when it lands in this column, or undefined to keep its own.
 *
 * @param {object} column The target column.
 * @return {string|undefined}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
export function mappedStatus(column) {
	if (column?.status) {
		return column.status
	}
	return column?.type === 'done' ? 'done' : undefined
}

/**
 * The `columnOrder` for a card put at the bottom of a list, or just before `beforeTask`.
 *
 * @param {Array<object>} laneTasks    The list's cards, without the moved card.
 * @param {object|null}   [beforeTask] The card to land in front of.
 * @return {number}
 *
 * @spec openspec/changes/backlog-list/tasks.md#task-2.1
 */
export function orderFor(laneTasks = [], beforeTask = null) {
	const lane = sortCards(laneTasks || [])
	const index = beforeTask ? lane.findIndex((task) => idOf(task) === idOf(beforeTask)) : -1
	if (index === -1) {
		const last = lane.length ? Number(lane[lane.length - 1].columnOrder) || 0 : 0
		return last + ORDER_STEP
	}
	const after = Number(lane[index].columnOrder) || 0
	const before = index > 0 ? Number(lane[index - 1].columnOrder) || 0 : after - ORDER_STEP
	return Math.floor((before + after) / 2)
}

/**
 * The PATCH that moves a card into a lane: at the bottom, or just before `beforeTask`.
 *
 * @param {object}        column       The target column.
 * @param {Array<object>} laneTasks    The target lane's cards, in order, without the moved card.
 * @param {object|null}   [beforeTask] The card to drop in front of.
 * @return {{column: string, columnOrder: number, status?: string}}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.2
 */
export function buildMovePatch(column, laneTasks = [], beforeTask = null) {
	const patch = { column: idOf(column), columnOrder: orderFor(laneTasks, beforeTask) }
	const status = mappedStatus(column)
	if (status) {
		patch.status = status
	}
	return patch
}

/**
 * The `columnOrder` writes that move one card a step up (-1) or down (+1).
 *
 * One write when an integer fits between the new neighbours, otherwise the
 * whole lane renumbered in steps of ORDER_STEP. No write at the lane's edge.
 *
 * @param {Array<object>} laneTasks The lane's cards.
 * @param {object}        task      The card to move.
 * @param {number}        direction -1 for up, +1 for down.
 * @return {Array<{id: string, columnOrder: number}>}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.4
 */
export function orderPatchesForStep(laneTasks, task, direction) {
	const lane = sortCards(laneTasks || [])
	const from = lane.findIndex((card) => idOf(card) === idOf(task))
	const to = from + direction
	if (from === -1 || to < 0 || to >= lane.length) {
		return []
	}
	const rest = lane.filter((_, i) => i !== from)
	// Neighbours around the new slot, in the lane without the moved card.
	const above = to > 0 ? Number(rest[to - 1].columnOrder) || 0 : null
	const below = to < rest.length ? Number(rest[to].columnOrder) || 0 : null
	let order = null
	if (above === null) {
		order = below - ORDER_STEP
	} else if (below === null) {
		order = above + ORDER_STEP
	} else if (below - above > 1) {
		order = Math.floor((above + below) / 2)
	}
	if (order !== null) {
		return [{ id: idOf(task), columnOrder: order }]
	}
	rest.splice(to, 0, task)
	return rest.map((card, i) => ({ id: idOf(card), columnOrder: (i + 1) * ORDER_STEP }))
}

/**
 * The lane header's count, against the WIP limit when there is one.
 *
 * @param {number}      count The cards in the lane.
 * @param {number|null} limit The lane's WIP limit; null or 0 means none.
 * @return {{text: string, over: boolean}}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-3.3
 */
export function wipState(count, limit) {
	const max = Number(limit)
	if (!max || max < 1) {
		return { text: String(count), over: false }
	}
	return { text: `${count} / ${max}`, over: count > max }
}

/**
 * Whether a column may be removed: never the last done column.
 *
 * @param {Array<object>} columns The project's columns.
 * @param {object}        column  The column to remove.
 * @return {boolean}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.2
 */
export function canRemoveColumn(columns, column) {
	if (column?.type !== 'done') {
		return true
	}
	return (columns || []).some((other) => other?.type === 'done' && idOf(other) !== idOf(column))
}

/**
 * The `order` writes that move a column one place left (-1) or right (+1).
 *
 * @param {Array<object>} columns   The project's columns.
 * @param {object}        column    The column to move.
 * @param {number}        direction -1 for left, +1 for right.
 * @return {Array<{id: string, order: number}>}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
 */
export function swapColumnPatches(columns, column, direction) {
	const lanes = sortColumns(columns)
	const from = lanes.findIndex((lane) => idOf(lane) === idOf(column))
	const to = from + direction
	if (from === -1 || to < 0 || to >= lanes.length) {
		return []
	}
	const moved = [...lanes]
	moved.splice(to, 0, moved.splice(from, 1)[0])
	return moved
		.map((lane, i) => ({ id: idOf(lane), order: i }))
		.filter((patch, i) => Number(moved[i].order) !== patch.order)
}

/**
 * The column object the edit dialog writes, from its form fields.
 *
 * An empty or non-positive WIP limit means no limit (null). An empty status
 * keeps the card's own status (null). A done column always maps to `done`.
 *
 * @param {object} draft The form fields: title, wipLimit, color, status, done.
 * @return {{title: string, wipLimit: number|null, color?: string, status: string|null, type: string}}
 *
 * @spec openspec/changes/boards-configurable-columns/tasks.md#task-4.1
 */
export function columnPayload(draft = {}) {
	const limit = Number.parseInt(draft.wipLimit, 10)
	const payload = {
		title: String(draft.title ?? '').trim(),
		wipLimit: Number.isInteger(limit) && limit > 0 ? limit : null,
		status: draft.done ? 'done' : (draft.status || null),
		type: draft.done ? 'done' : 'active',
	}
	if (draft.color) {
		payload.color = String(draft.color).toUpperCase()
	}
	return payload
}
