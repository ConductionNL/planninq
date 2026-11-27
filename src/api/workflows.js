/**
 * Shared workflows (projects-templates-shared-workflow): plain OpenRegister
 * objects on the `workflow` schema, read by every signed-in user and written by
 * admins. Planninq's sync listener carries a change to every project on it, and
 * refuses deleting a workflow that projects follow.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const BASE = '/apps/openregister/api/objects/planninq/workflow'

/** The workflows read so far, by id, so every card on a board shares one read. */
const cache = new Map()

/**
 * The server's reason for a refused request, or the fallback.
 *
 * @param {Error} error The axios error.
 * @param {string} fallback The reason when the server gave none.
 * @return {Error}
 */
function refusal(error, fallback) {
	const data = error?.response?.data
	return new Error(data?.message || data?.error || fallback, { cause: error })
}

/**
 * Every workflow, by title.
 *
 * @return {Promise<Array<object>>}
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
 */
export async function listWorkflows() {
	const response = await axios.get(generateUrl(BASE), { params: { _limit: 200 } })
	const rows = response.data?.results ?? response.data
	return (Array.isArray(rows) ? rows : []).sort((a, b) => String(a.title || '').localeCompare(String(b.title || '')))
}

/**
 * Create a workflow, or replace one that has an id.
 *
 * @param {object} workflow The workflow; with `id` it is replaced.
 * @return {Promise<object>} The stored workflow.
 * @throws {Error} With the server's reason when the write was refused.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
 */
export async function saveWorkflow(workflow) {
	const { id, ...fields } = workflow
	try {
		const response = id
			? await axios.put(generateUrl(`${BASE}/${encodeURIComponent(id)}`), fields)
			: await axios.post(generateUrl(BASE), fields)
		cache.clear()
		return response.data
	} catch (error) {
		throw refusal(error, 'workflow-save-error')
	}
}

/**
 * Delete a workflow; refused while projects follow it.
 *
 * @param {string} id The workflow.
 * @return {Promise<void>}
 * @throws {Error} With the server's reason when the delete was refused.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.3
 */
export async function deleteWorkflow(id) {
	try {
		await axios.delete(generateUrl(`${BASE}/${encodeURIComponent(id)}`))
	} catch (error) {
		throw refusal(error, 'workflow-delete-error')
	}
}

/**
 * One workflow, read once per page; null when it cannot be read.
 *
 * @param {string} id The workflow.
 * @return {Promise<object|null>}
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.4
 */
export function getWorkflow(id) {
	if (!cache.has(id)) {
		cache.set(id, axios.get(generateUrl(`${BASE}/${encodeURIComponent(id)}`)).then((response) => response.data, () => null))
	}
	return cache.get(id)
}

/**
 * Forget the workflows read so far, after one was saved.
 *
 * @spec openspec/changes/archive/2026-11-24-projects-templates-shared-workflow/tasks.md#task-2.4
 */
export function forgetWorkflows() {
	cache.clear()
}
