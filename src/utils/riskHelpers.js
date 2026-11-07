/**
 * Pure helpers for the risk register: the admin's risk scale, the bands, the
 * heat map and the payload a risk is saved with (projects-overview-logs-risks).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

/** The scale an install starts with; the same as RiskScaleService::DEFAULT_SCALE. */
export const DEFAULT_RISK_SCALE = Object.freeze({
	levels: 5,
	likelihood: ['Very low', 'Low', 'Medium', 'High', 'Very high'],
	impact: ['Very low', 'Low', 'Medium', 'High', 'Very high'],
	thresholds: { medium: 5, high: 12 },
})

/** The statuses under which a risk still needs watching. */
const OPEN_STATUSES = ['open', 'mitigating']

/**
 * Whether a parsed value is a usable scale.
 *
 * @param {object} scale The candidate.
 * @return {boolean}
 */
function isScale(scale) {
	const levels = scale?.levels
	return isLevelCount(levels) && hasLevels(scale.likelihood, levels)
		&& hasLevels(scale.impact, levels) && hasThresholds(scale.thresholds)
}

/**
 * Whether a number of levels is a whole number from 3 to 5.
 *
 * @param {number|undefined} levels The candidate.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
 */
function isLevelCount(levels) {
	return Number.isInteger(levels) && levels >= 3 && levels <= 5
}

/**
 * Whether a list holds one label per level.
 *
 * @param {Array|string|undefined} list The candidate list.
 * @param {number} levels The number of levels.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
 */
function hasLevels(list, levels) {
	return Array.isArray(list) && list.length === levels
}

/**
 * Whether both thresholds are whole numbers.
 *
 * @param {object|undefined} thresholds The candidate thresholds.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
 */
function hasThresholds(thresholds) {
	return Number.isInteger(thresholds?.medium) && Number.isInteger(thresholds?.high)
}

/**
 * The scale from the stored setting, or the default when it is missing or broken.
 *
 * @param {string|object} value The `risk_scale` setting.
 * @return {object}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
 */
export function parseRiskScale(value) {
	let scale = value
	if (typeof value === 'string') {
		try {
			scale = JSON.parse(value)
		} catch {
			scale = null
		}
	}
	return isScale(scale) ? scale : DEFAULT_RISK_SCALE
}

/**
 * Thresholds that suit a number of levels, offered when the admin changes it.
 *
 * @param {number} levels 3, 4 or 5.
 * @return {{medium: number, high: number}}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.4
 */
export function defaultThresholds(levels) {
	return { 3: { medium: 3, high: 6 }, 4: { medium: 4, high: 9 } }[levels] || { medium: 5, high: 12 }
}

/**
 * The band of a score: low, medium or high.
 *
 * @param {number} score likelihood times impact.
 * @param {object} scale The risk scale.
 * @return {'low'|'medium'|'high'}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
export function riskBand(score, scale = DEFAULT_RISK_SCALE) {
	const value = Number(score) || 0
	if (value >= scale.thresholds.high) {
		return 'high'
	}
	return value >= scale.thresholds.medium ? 'medium' : 'low'
}

/**
 * The heat map: one row per impact level, highest first, and one cell per
 * likelihood level, each with its count, score and band.
 *
 * @param {Array<object>} risks The risks.
 * @param {object} scale The risk scale.
 * @return {Array<{impact: number, label: string, cells: Array<object>}>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
export function heatMapRows(risks = [], scale = DEFAULT_RISK_SCALE) {
	const rows = []
	for (let impact = scale.levels; impact >= 1; impact--) {
		const cells = []
		for (let likelihood = 1; likelihood <= scale.levels; likelihood++) {
			const score = likelihood * impact
			cells.push({
				likelihood,
				label: scale.likelihood[likelihood - 1],
				score,
				band: riskBand(score, scale),
				count: (risks || []).filter((risk) => Number(risk?.likelihood) === likelihood && Number(risk?.impact) === impact).length,
			})
		}
		rows.push({ impact, label: scale.impact[impact - 1], cells })
	}
	return rows
}

/**
 * Risks by score, highest first; ties by title.
 *
 * @param {Array<object>} risks The risks.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
export function sortRisks(risks = []) {
	return [...(risks || [])].sort((a, b) => (Number(b?.score) || 0) - (Number(a?.score) || 0)
		|| String(a?.title ?? '').localeCompare(String(b?.title ?? '')))
}

/**
 * The highest-scored risks that are still open or being mitigated.
 *
 * @param {Array<object>} risks The risks.
 * @param {number} [limit] How many.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-1.2
 */
export function topOpenRisks(risks = [], limit = 3) {
	return sortRisks((risks || []).filter((risk) => OPEN_STATUSES.includes(risk?.status ?? 'open'))).slice(0, limit)
}

/**
 * The payload of a new or edited risk. It carries no score: the server
 * calculates it from likelihood and impact, and overwrites any a client sends.
 *
 * @param {object} fields The form fields.
 * @param {string} projectId The project.
 * @return {object}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
export function riskPayload(fields, projectId) {
	const payload = {
		title: textOf(fields?.title).trim(),
		project: projectId,
		likelihood: Number(fields?.likelihood),
		impact: Number(fields?.impact),
		status: ['open', 'mitigating', 'closed', 'occurred'].includes(fields?.status) ? fields.status : 'open',
		response: ['avoid', 'reduce', 'transfer', 'accept'].includes(fields?.response) ? fields.response : 'reduce',
		description: textOf(fields?.description),
		category: textOf(fields?.category),
		countermeasures: textOf(fields?.countermeasures),
	}
	if (fields?.owner) {
		payload.owner = fields.owner
	}
	if (fields?.reviewDate) {
		payload.reviewDate = fields.reviewDate
	}
	return payload
}

/**
 * A form value as text, '' when it is missing.
 *
 * @param {string|number|null|undefined} value The value.
 * @return {string}
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
function textOf(value) {
	return String(value ?? '')
}
