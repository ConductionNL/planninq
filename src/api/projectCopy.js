/**
 * Copying a project, or starting one from a template, through planninq's copy
 * endpoint (projects-templates-shared-workflow): the server checks the
 * creation policy and remaps every reference inside the copy.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.3
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Copy a project or template.
 *
 * @param {string} sourceId The project or template to copy.
 * @param {{title: string, key?: string, startDate?: string, parts: object}} body The copy request.
 * @return {Promise<object>} The new project.
 * @throws {Error} With the server's reason when the copy was refused or failed.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-1.3
 */
export async function copyProject(sourceId, body) {
	try {
		const response = await axios.post(generateUrl(`/apps/planninq/api/projects/${encodeURIComponent(sourceId)}/copy`), body)
		return response.data
	} catch (error) {
		throw new Error(error?.response?.data?.error || 'copy-error', { cause: error })
	}
}
