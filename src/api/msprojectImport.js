/**
 * The Microsoft Project import endpoints (integration-msproject-import): a
 * preview that writes nothing and the import itself. Both take the same file;
 * nothing is kept on the server between the two calls.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Post the file and return the status and body, also for a refusal.
 *
 * @param {string} url The endpoint.
 * @param {File} file The plan.
 * @return {Promise<{status: number, data: object}>}
 */
async function postPlan(url, file) {
	const form = new FormData()
	form.append('file', file)
	try {
		const response = await axios.post(url, form)
		return { status: response.status, data: response.data || {} }
	} catch (err) {
		if (err?.response) {
			return { status: err.response.status, data: err.response.data || {} }
		}
		throw err
	}
}

/**
 * What importing the plan into the project would do.
 *
 * @param {string} projectId The project.
 * @param {File} file The plan saved from Microsoft Project as XML.
 * @return {Promise<{status: number, data: object}>}
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
export function previewPlan(projectId, file) {
	return postPlan(generateUrl(`/apps/planninq/api/projects/${projectId}/import/msproject/preview`), file)
}

/**
 * Import the plan into the project.
 *
 * @param {string} projectId The project.
 * @param {File} file The same plan the preview read.
 * @return {Promise<{status: number, data: object}>}
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
export function importPlan(projectId, file) {
	return postPlan(generateUrl(`/apps/planninq/api/projects/${projectId}/import/msproject`), file)
}
