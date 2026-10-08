/**
 * Task editing helpers: the pure parts of creating, editing and deleting a
 * task from the board and the task page (tasks-create-edit-delete).
 *
 * The server stamps `reporter` (TaskReporterGuardListener), so no payload
 * built here carries it.
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-1.1
 */
import { buildMovePatch } from './columnHelpers.js'
import { responsiblePatch, sharedWithPatch } from './taskPeople.js'

/** The fields the task dialog edits, in the order it shows them. */
export const EDITABLE_FIELDS = ['title', 'description', 'status', 'priority']

/** The date-only fields the dialog edits; an empty value clears the date. */
export const DATE_FIELDS = ['startDate', 'dueDate']

/** Longest excerpt a board card shows. */
export const EXCERPT_LENGTH = 140

/**
 * A task payload with the create defaults: status `open`, priority `normal`.
 *
 * @param {object} data The fields given.
 * @return {object}
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-1.1
 */
export function withTaskDefaults(data = {}) {
	return { status: 'open', priority: 'normal', ...data }
}

/**
 * A new task at the bottom of a board lane, or in the backlog when there is no lane.
 *
 * @param {{title: string, description?: string, priority?: string, assignedTo?: string, sharedWith?: Array<string>}} fields What was typed.
 * @param {string}        projectId The project UUID.
 * @param {object|null}   column    The lane, or null.
 * @param {Array<object>} laneTasks The lane's cards.
 * @return {object}
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-3.1
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
 */
export function newLaneTask(fields, projectId, column, laneTasks = []) {
	const task = withTaskDefaults({ title: String(fields?.title ?? '').trim(), project: projectId })
	if (fields?.description) {
		task.description = String(fields.description)
	}
	if (fields?.priority) {
		task.priority = fields.priority
	}
	for (const field of DATE_FIELDS) {
		if (fields?.[field]) {
			task[field] = String(fields[field])
		}
	}
	if (fields?.assignedTo) {
		task.assignedTo = String(fields.assignedTo)
	}
	if (fields?.sharedWith?.length) {
		const shared = sharedWithPatch(task, fields.sharedWith).sharedWith
		if (shared?.length) {
			task.sharedWith = shared
		}
	}
	if (!column) {
		return { ...task, column: null }
	}
	return { ...task, ...buildMovePatch(column, laneTasks) }
}

/**
 * The PATCH body for an edit: only the fields that changed. The people
 * fields count only when the dialog showed them (`assignedTo` in the draft).
 *
 * @param {object} task  The stored task.
 * @param {object} draft The dialog's values.
 * @return {object}
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-2.1
 * @spec openspec/changes/tasks-assignment-priority-labels/tasks.md#task-2.1
 */
export function editPatch(task, draft) {
	const patch = {}
	for (const field of EDITABLE_FIELDS) {
		const value = field === 'title' ? String(draft?.[field] ?? '').trim() : (draft?.[field] ?? '')
		if (value !== (task?.[field] ?? '')) {
			patch[field] = value
		}
	}
	for (const field of DATE_FIELDS) {
		if (draft && field in draft && (draft[field] || null) !== (task?.[field] || null)) {
			patch[field] = draft[field] || null
		}
	}
	if (draft && 'assignedTo' in draft) {
		Object.assign(patch, responsiblePatch(task, draft.assignedTo))
		Object.assign(patch, sharedWithPatch({ ...task, ...patch }, draft.sharedWith || []))
	}
	return patch
}

/**
 * A plain-text excerpt of a Markdown description for a board card.
 *
 * Strips headings, list markers, emphasis, code marks and link syntax. The
 * card interpolates the result as text, so markup in it is never rendered.
 *
 * @param {string|null} markdown The description.
 * @param {number}      [max]    The longest excerpt.
 * @return {string}
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-3.2
 */
export function descriptionExcerpt(markdown, max = EXCERPT_LENGTH) {
	const text = String(markdown ?? '')
		.replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
		.replace(/^\s{0,3}(#{1,6}|>|[-*+]|\d+\.)\s+/gm, '')
		.replace(/\[[ xX]\]\s+/g, '')
		.replace(/(\*\*|__|\*|_|~~|`)(\S(?:.*?\S)?)\1/g, '$2')
		.replace(/\s+/g, ' ')
		.trim()
	if (text.length <= max) {
		return text
	}
	return text.slice(0, max - 1).trimEnd() + '…'
}

/**
 * Whether the user sees "Delete task": the reporter, the project owner or an admin.
 *
 * @param {object}      task    The task.
 * @param {object|null} project The task's project.
 * @param {{uid: string, isAdmin?: boolean}|null} user The current user.
 * @return {boolean}
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-4.1
 */
export function canDeleteTask(task, project, user) {
	if (!user?.uid) {
		return false
	}
	return user.isAdmin === true || (!!task?.reporter && task.reporter === user.uid) || (!!project?.owner && project.owner === user.uid)
}

/**
 * Why the server refused a delete: `has-time`, `not-allowed` or `failed`.
 *
 * @param {object} body The error body OpenRegister returned.
 * @return {string}
 *
 * @spec openspec/changes/tasks-create-edit-delete/tasks.md#task-1.2
 */
export function deleteRefusal(body) {
	const code = body?.code ?? body?.errors?.code ?? ''
	if (code === 'planninq-task-has-logged-time') {
		return 'has-time'
	}
	if (code === 'planninq-task-delete-not-allowed') {
		return 'not-allowed'
	}
	return 'failed'
}
