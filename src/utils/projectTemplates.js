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
