/**
 * The files on a project phase, through OpenRegister's per-object files API
 * (planning-phase-gate-document). Nothing is stored by planninq itself.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * @param {string} phaseId The phase.
 * @return {string}
 */
function filesUrl(phaseId) {
	return generateUrl(`/apps/openregister/api/objects/planninq/projectPhase/${phaseId}/files`)
}

/**
 * The files attached to a phase.
 *
 * @param {string} phaseId The phase.
 * @return {Promise<Array<{id: number, title: string}>>}
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
 */
export async function listPhaseFiles(phaseId) {
	const response = await axios.get(filesUrl(phaseId))
	const data = response.data
	const files = Array.isArray(data) ? data : (Array.isArray(data?.results) ? data.results : [])
	return files.map((file) => ({ id: file.id, title: file.title || file.name || String(file.id) }))
}

/**
 * Attach a file to a phase.
 *
 * @param {string} phaseId The phase.
 * @param {File} file The file the member picked.
 * @return {Promise<Array<{id: number, title: string}>>} The phase's files afterwards.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
 */
export async function uploadPhaseFile(phaseId, file) {
	const form = new FormData()
	form.append('files[]', file)
	await axios.post(generateUrl(`/apps/openregister/api/objects/planninq/projectPhase/${phaseId}/filesMultipart`), form)
	return listPhaseFiles(phaseId)
}
