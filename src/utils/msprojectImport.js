/**
 * Pure helpers for the Microsoft Project import (integration-msproject-import):
 * the check on a file before it is uploaded, the refusal reason the dialog
 * explains, and the order the preview lists what does not carry over.
 * Strings are translated in the dialog; these return codes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */

/** The largest file the import reads (10 MB), as on the server. */
export const MAX_BYTES = 10 * 1024 * 1024

/** What the preview counts, in the order it names them. */
export const COUNT_KINDS = ['phases', 'tasks', 'subtasks', 'milestones', 'links']

/** What does not carry over, in the order the preview lists it. */
export const LOSS_CODES = ['resources', 'collapsedLevels', 'relatedLinks', 'lags', 'phaseLinks', 'unknownLinks']

/** Refusal reasons the server sends and the dialog explains. */
const KNOWN_REASONS = ['mpp', 'unsafe', 'tooLarge', 'tooManyTasks', 'notAPlan', 'noFile']

/**
 * Why a chosen file cannot be imported, before it is sent; '' when it can.
 *
 * @param {{name: string, size: number}|null} file The chosen file.
 * @return {string} A refusal reason, or ''.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
export function fileRefusal(file) {
	if (!file) {
		return 'noFile'
	}
	if (/\.mpp$/i.test(file.name || '')) {
		return 'mpp'
	}
	if ((file.size || 0) > MAX_BYTES) {
		return 'tooLarge'
	}
	return ''
}

/**
 * The refusal reason for a failed preview or import.
 *
 * @param {number} status The HTTP status.
 * @param {object} body The response body.
 * @return {string} A known reason, 'forbidden', or 'other'.
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
export function refusalReason(status, body) {
	if (status === 403) {
		return 'forbidden'
	}
	const reason = body?.reason || ''
	return KNOWN_REASONS.includes(reason) ? reason : 'other'
}

/**
 * The losses with a count, in the order the preview lists them.
 *
 * @param {{[code: string]: number}|undefined} losses The losses from the server.
 * @return {Array<{code: string, count: number}>}
 *
 * @spec openspec/changes/integration-msproject-import/tasks.md#task-3.1
 */
export function lossList(losses) {
	return LOSS_CODES
		.map((code) => ({ code, count: Number(losses?.[code] || 0) }))
		.filter((loss) => loss.count > 0)
}
