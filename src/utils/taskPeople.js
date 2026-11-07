/**
 * People, priority and labels on a task (tasks-assignment-priority-labels).
 *
 * `assignedTo` is the one person responsible; `sharedWith` lists the others
 * who work on the task. The responsible person is never in `sharedWith`.
 * Every helper returns a PATCH body with only what changed, so a save never
 * sends a field it did not mean to write.
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
 */

/** The priority levels, most urgent first. */
export const PRIORITIES = ['urgent', 'high', 'normal', 'low']

/**
 * A list of distinct, non-empty user ids.
 *
 * @param {Array<string>} uids The ids.
 * @return {Array<string>}
 */
function distinct(uids) {
	return [...new Set((uids || []).map((uid) => String(uid ?? '')).filter((uid) => uid !== ''))]
}

/**
 * The people picker's options: the project's members and its owner, by display name.
 *
 * @param {object|null} project The project.
 * @param {object}      names   User id to display name.
 * @return {Array<{id: string, label: string}>}
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
 */
export function memberOptions(project, names = {}) {
	if (!project) {
		return []
	}
	return distinct([project.owner, ...(project.members || [])])
		.map((id) => ({ id, label: names?.[id] || id }))
		.sort((a, b) => a.label.localeCompare(b.label))
}

/**
 * Everyone on a task: the responsible person first, then the people it is shared with.
 *
 * @param {object} task The task.
 * @return {Array<string>}
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.2
 */
export function peopleOf(task) {
	return distinct([task?.assignedTo, ...(task?.sharedWith || [])])
}

/**
 * The PATCH that makes `uid` responsible (or nobody, for an empty value).
 *
 * @param {object}      task The task.
 * @param {string|null} uid  The new responsible person.
 * @return {object}
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
 */
export function responsiblePatch(task, uid) {
	const next = String(uid ?? '')
	const patch = {}
	if (next !== String(task?.assignedTo ?? '')) {
		patch.assignedTo = next
	}
	const shared = distinct(task?.sharedWith)
	if (next !== '' && shared.includes(next)) {
		patch.sharedWith = shared.filter((other) => other !== next)
	}
	return patch
}

/**
 * The PATCH that shares the task with `uids`, the responsible person left out.
 *
 * @param {object}        task The task.
 * @param {Array<string>} uids The people to share with.
 * @return {object}
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
 */
export function sharedWithPatch(task, uids) {
	const next = distinct(uids).filter((uid) => uid !== String(task?.assignedTo ?? ''))
	const current = distinct(task?.sharedWith)
	if (next.length === current.length && next.every((uid, i) => uid === current[i])) {
		return {}
	}
	return { sharedWith: next }
}

/**
 * The PATCH that sets a priority, or nothing for an unknown level or no change.
 *
 * @param {object} task     The task.
 * @param {string} priority The level.
 * @return {object}
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-3.1
 */
export function priorityPatch(task, priority) {
	if (!PRIORITIES.includes(priority) || task?.priority === priority) {
		return {}
	}
	return { priority }
}

/**
 * The PATCH that sets a task's labels, or nothing when the set is unchanged.
 *
 * @param {object}        task The task.
 * @param {Array<string>} ids  The label ids.
 * @return {object}
 *
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-4.1
 */
export function labelsPatch(task, ids) {
	const next = distinct(ids)
	const current = distinct(task?.labels)
	if (next.length === current.length && next.every((id) => current.includes(id))) {
		return {}
	}
	return { labels: next }
}
