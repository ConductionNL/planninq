/**
 * The project export endpoint (project-data-export): queue an export of the
 * whole project to a zip in the caller's Files.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-4.2
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Queue the export of a project.
 *
 * @param {string} projectId The project.
 * @return {Promise<{status: string}>} The server's answer, `{ status: 'queued' }`.
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-4.2
 */
export async function startProjectExport(projectId) {
	const response = await axios.post(generateUrl(`/apps/planninq/api/projects/${projectId}/export`))
	return response.data
}

/**
 * The message to show for a failed export request: the server's own, else a fallback.
 *
 * @param {object} error The axios error.
 * @param {string} fallback The message when the server gave none.
 * @return {string}
 *
 * @spec openspec/changes/project-data-export/tasks.md#task-4.2
 */
export function exportErrorMessage(error, fallback) {
	const message = error?.response?.data?.error || error?.response?.data?.message
	return typeof message === 'string' && message !== '' ? message : fallback
}
