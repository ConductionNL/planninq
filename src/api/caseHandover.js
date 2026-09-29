/**
 * The case handover endpoints (integration-case-bridge): whether a handover
 * is possible and which were made, and the handover itself.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * @param {string} projectId The project.
 * @return {string}
 */
function url(projectId) {
	return generateUrl(`/apps/planninq/api/projects/${projectId}/case-handover`)
}

/**
 * Whether the case app is there, and the handovers made so far.
 *
 * @param {string} projectId The project.
 * @return {Promise<{available: boolean, handovers: Array<object>}>}
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
 */
export async function fetchHandoverStatus(projectId) {
	const response = await axios.get(url(projectId))
	return { available: response.data?.available === true, handovers: response.data?.handovers || [] }
}

/**
 * Hand the project over to its case.
 *
 * @param {string} projectId The project.
 * @return {Promise<object>} The handover record.
 *
 * @spec openspec/changes/integration-case-bridge/tasks.md#task-3.3
 */
export async function handOverToCase(projectId) {
	const response = await axios.post(url(projectId))
	return response.data
}
