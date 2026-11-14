/**
 * Project templates and copies (projects-templates-shared-workflow): which
 * projects a list chip shows, which projects are templates to start from, and
 * the body of a copy request.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */

/** The chip value that lists the templates. */
export const TEMPLATE_CHIP = 'template'

/**
 * Whether a project is a template.
 *
 * @param {object|null} project The project.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */
export function isTemplate(project) {
	return project?.isTemplate === true
}

/**
 * The projects a status chip lists: the templates under the Templates chip,
 * working projects only under Active, and every project of that status under
 * the other chips; no chip lists them all.
 *
 * @param {Array<object>} projects The projects.
 * @param {string|null} chip The chip's value.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.1
 */
export function projectsForChip(projects, chip) {
	if (chip === TEMPLATE_CHIP) {
		return projects.filter(isTemplate)
	}
	if (chip === 'active') {
		return projects.filter((project) => project.status === 'active' && !isTemplate(project))
	}
	return chip ? projects.filter((project) => project.status === chip) : projects
}

/** The parts of a project a copy can take along, in the order the copy dialog offers them. */
export const COPY_PARTS = ['columns', 'tasks', 'phases', 'dependencies', 'people']

/**
 * The parts a copy takes by default: everything but the people.
 *
 * @return {{columns: boolean, tasks: boolean, phases: boolean, dependencies: boolean, people: boolean}}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function defaultCopyParts() {
	return { columns: true, tasks: true, phases: true, dependencies: true, people: false }
}

/**
 * The templates to start a project from, by title, as select options.
 *
 * @param {Array<object>} projects The projects the person can see.
 * @return {Array<{id: string, label: string}>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function templateOptions(projects) {
	return (Array.isArray(projects) ? projects : [])
		.filter(isTemplate)
		.map((project) => ({ id: project.id, label: project.title || project.id }))
		.sort((a, b) => a.label.localeCompare(b.label))
}

/**
 * The body of a copy request: the trimmed title, the key and start date when
 * given, and the chosen parts. Dependencies only come with tasks.
 *
 * @param {{title: string, key?: string, startDate?: string, parts?: object}} form The copy form.
 * @return {{title: string, key?: string, startDate?: string, parts: object}}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-1.3
 */
export function copyBody(form) {
	const parts = { ...defaultCopyParts(), ...(form.parts || {}) }
	parts.dependencies = parts.dependencies && parts.tasks
	const body = { title: String(form.title || '').trim(), parts }
	for (const field of ['key', 'startDate']) {
		if (form[field]) {
			body[field] = form[field]
		}
	}
	return body
}
