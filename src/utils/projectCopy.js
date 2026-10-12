/**
 * Templates and project copy (projects-templates-shared-workflow): which
 * parts a copy keeps, the request body, and who may copy.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */

/** The parts of a project a copy can keep, in the order the dialog lists them. */
export const COPY_PARTS = ['columns', 'phases', 'tasks', 'dependencies', 'people']

/**
 * The parts kept by default: the structure, not the people.
 *
 * @return {Record<string, boolean>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function defaultCopyParts() {
	return { columns: true, phases: true, tasks: true, dependencies: true, people: false }
}

/**
 * Keep the parts that depend on others consistent: dependencies need tasks,
 * and tasks keep their column and phase links only when those come along.
 *
 * @param {Record<string, boolean>} parts The chosen parts.
 * @return {Record<string, boolean>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function consistentParts(parts) {
	const next = { ...defaultCopyParts(), ...(parts || {}) }
	if (!next.tasks) {
		next.dependencies = false
	}
	return next
}

/**
 * The body of `POST /api/projects/{id}/copy`.
 *
 * @param {object} form The dialog state.
 * @param {string} form.title The new project's title.
 * @param {string} form.key The new project's key, as typed.
 * @param {string} form.startDate The new start date (Y-m-d), or empty to keep the source's.
 * @param {Record<string, boolean>} form.parts The parts to keep.
 * @return {object}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function copyPayload(form) {
	const payload = {
		title: String(form.title ?? '').trim(),
		parts: consistentParts(form.parts),
	}
	const key = String(form.key ?? '').trim().toUpperCase()
	if (key !== '') {
		payload.key = key
	}
	if (String(form.startDate ?? '') !== '') {
		payload.startDate = form.startDate
	}
	return payload
}

/**
 * The templates to offer in the creation dialog, by title.
 *
 * @param {Array<object>} projects Every project the person can read.
 * @return {Array<{id: string, label: string, startDate: string}>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function templateOptions(projects) {
	return (projects || [])
		.filter((project) => project?.isTemplate === true && project.status !== 'archived')
		.map((project) => ({ id: project.id, label: String(project.title ?? ''), startDate: String(project.startDate ?? '') }))
		.sort((a, b) => a.label.localeCompare(b.label))
}

/**
 * Whether the person may copy this project: its owner or a manager.
 * Admins and template users are let through by the server.
 *
 * @param {string} role The person's role on the project (see projectRole).
 * @return {boolean}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function mayCopyProject(role) {
	return role === 'owner' || role === 'manager'
}
