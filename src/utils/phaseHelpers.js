/**
 * Pure helpers for project phases (planning-phase-gate-document): the phase
 * order, the keyboard reorder, the one write that closes a phase, the refusal
 * a close can meet, and the flag for a completed phase whose concluding
 * document is gone.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

/**
 * Phases by `order`, then title.
 *
 * @param {Array<object>} phases The phases.
 * @return {Array<object>}
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
 */
export function sortPhases(phases = []) {
	return [...(phases || [])].sort((a, b) => (Number(a?.order) || 0) - (Number(b?.order) || 0)
		|| String(a?.title ?? '').localeCompare(String(b?.title ?? '')))
}

/**
 * The writes that move one phase up (-1) or down (+1): its order and its
 * neighbour's are swapped. Tied orders are first spread over the list
 * positions, so the two phases always end up with different values.
 *
 * @param {Array<object>} phases The phases.
 * @param {string} id The phase to move.
 * @param {number} step -1 for up, +1 for down.
 * @return {Array<{id: string, order: number}>} Nothing at either end.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
 */
export function reorderPatches(phases, id, step) {
	const sorted = sortPhases(phases)
	const index = sorted.findIndex((phase) => phase.id === id)
	const other = sorted[index + step]
	if (index < 0 || !other) {
		return []
	}
	const orders = sorted.map((phase) => Number(phase.order) || 0)
	const distinct = new Set(orders).size === orders.length
	const own = distinct ? orders[index] : index
	const theirs = distinct ? orders[index + step] : index + step
	return [{ id, order: theirs }, { id: other.id, order: own }]
}

/**
 * The single write that closes a phase: its concluding document and its status together.
 *
 * @param {string|number} fileId The id of the file on the phase.
 * @return {{concludingDocument: string, status: string}}
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
 */
export function closePatch(fileId) {
	return { concludingDocument: String(fileId), status: 'completed' }
}

/**
 * What kind of refusal a phase write met: the concluding-document guard, a
 * status move the lifecycle does not allow, or anything else.
 *
 * @param {number} status The HTTP status.
 * @param {object} body The response body.
 * @return {'guard'|'transition'|'other'}
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.4
 */
export function refusalMessage(status, body) {
	const code = body?.errors?.code || body?.code || ''
	if (code === 'lifecycle-guard-denied') {
		return 'guard'
	}
	if (code === 'lifecycle-invalid-transition') {
		return 'transition'
	}
	return 'other'
}

/**
 * Whether a completed phase lacks its concluding document among its files.
 *
 * @param {object} phase The phase.
 * @param {Array<object>} files The files on the phase.
 * @return {boolean}
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.5
 */
export function missingConcludingDocument(phase, files = []) {
	if (phase?.status !== 'completed') {
		return false
	}
	const document = String(phase.concludingDocument ?? '')
	return !document || !(files || []).some((file) => String(file?.id) === document)
}

/**
 * A phase to write from the edit form. A new phase comes last.
 *
 * @param {object} fields Title, description, dates and budget hours from the form.
 * @param {string} projectId The project.
 * @param {Array<object>} phases The project's phases, for the order of a new one.
 * @return {object}
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.2
 */
export function phasePayload(fields, projectId, phases = []) {
	const payload = { title: String(fields.title ?? '').trim(), project: projectId }
	for (const key of ['description', 'startDate', 'endDate']) {
		const value = String(fields[key] ?? '').trim()
		if (value) {
			payload[key] = value
		}
	}
	const hours = Number(fields.budgetHours)
	if (fields.budgetHours !== '' && fields.budgetHours !== undefined && fields.budgetHours !== null && Number.isFinite(hours)) {
		payload.budgetHours = hours
	}
	if (!fields.id) {
		payload.order = Math.max(0, ...(phases || []).map((phase) => Number(phase.order) || 0)) + 1
	}
	return payload
}
