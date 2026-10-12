/**
 * A project moving onto a shared workflow (projects-templates-shared-workflow):
 * which of its columns the workflow keeps, and how many tasks move to the
 * workflow's first column. Mirrors WorkflowColumnPlanner on the server, so the
 * dialog can say what will happen before it saves.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
 */

/**
 * The text of a title, for matching: trimmed and lower case.
 *
 * @param {string} title A column heading.
 * @return {string}
 */
function titleKey(title) {
	return String(title ?? '').trim().toLowerCase()
}

/**
 * The ids of the project columns a workflow keeps: a column that already
 * follows one of the workflow's columns by key, or one without a key whose
 * title matches a workflow column that no other column follows.
 *
 * @param {Array<object>} columns The project's columns.
 * @param {Array<object>} workflowColumns The workflow's columns, in order.
 * @return {Set<string>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
 */
export function keptColumnIds(columns, workflowColumns) {
	const kept = new Set()
	const followed = new Set()
	for (const flow of workflowColumns || []) {
		const byKey = (columns || []).find((column) => column.workflowKey === flow.key && !kept.has(column.id))
		if (byKey) {
			kept.add(byKey.id)
			followed.add(flow.key)
		}
	}
	for (const flow of workflowColumns || []) {
		if (followed.has(flow.key)) {
			continue
		}
		const byTitle = (columns || []).find((column) => !column.workflowKey && !kept.has(column.id) && titleKey(column.title) === titleKey(flow.title))
		if (byTitle) {
			kept.add(byTitle.id)
		}
	}
	return kept
}

/**
 * What moving the project onto the workflow does to its tasks.
 *
 * Tasks in a kept column stay; tasks in any other column move to the
 * workflow's first column; tasks in the backlog (no column) stay there.
 *
 * @param {Array<object>} columns The project's columns.
 * @param {Array<object>} tasks The project's tasks.
 * @param {Array<object>} workflowColumns The workflow's columns, in order.
 * @return {{moved: number, target: string}} How many tasks move, and the title they move to
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
 */
export function mappingPreview(columns, tasks, workflowColumns) {
	const kept = keptColumnIds(columns, workflowColumns)
	const known = new Set((columns || []).map((column) => column.id))
	let moved = 0
	for (const task of tasks || []) {
		if (task.column && known.has(task.column) && !kept.has(task.column)) {
			moved++
		}
	}
	return { moved, target: String(workflowColumns?.[0]?.title ?? '') }
}
