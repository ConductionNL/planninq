/**
 * The board import endpoints (projects-import-from-other-tools): the list of
 * imports this instance offers, and importing an export as a new project.
 * Admin-only on the server.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * The imports the server offers.
 *
 * @return {Promise<Array<{id: string, label: string, format: string}>>}
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
 */
export async function fetchBoardImports() {
	const response = await axios.get(generateUrl('/apps/planninq/api/import/boards'))
	return Array.isArray(response.data?.tools) ? response.data.tools : []
}

/**
 * Import an export as a new project, returning the status and body, also for a refusal.
 *
 * @param {string} tool The import id, such as "trello".
 * @param {File} file The exported file.
 * @return {Promise<{status: number, data: object}>}
 *
 * @spec openspec/changes/projects-import-from-other-tools/tasks.md#task-1.2
 */
export async function importBoard(tool, file) {
	const form = new FormData()
	form.append('file', file)
	try {
		const response = await axios.post(generateUrl(`/apps/planninq/api/import/boards/${encodeURIComponent(tool)}`), form)
		return { status: response.status, data: response.data || {} }
	} catch (err) {
		if (err?.response) {
			return { status: err.response.status, data: err.response.data || {} }
		}
		throw err
	}
}

/**
 * The file types the picker accepts for an import's format.
 *
 * @param {string} format "json" or "csv".
 * @return {string}
 */
export function acceptFor(format) {
	if (format === 'json') {
		return '.json,application/json'
	}
	if (format === 'csv') {
		return '.csv,text/csv'
	}
	return ''
}
