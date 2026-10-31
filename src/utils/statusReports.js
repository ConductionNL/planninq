/**
 * Pure helpers for project status reports (portfolio-status-overview): the
 * six aspects, the worst-status rule, the suggestions for money, time and
 * risk, the report order and the payload the Status tab writes.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { riskBand, sortRisks } from './riskHelpers.js'

/** The six aspects in the order the form and the tables show them. */
export const ASPECTS = ['money', 'organisation', 'time', 'information', 'quality', 'risk']

/** The statuses from best to worst. */
export const STATUSES = ['onTrack', 'atRisk', 'offTrack']

/** Money is at risk once cost passes this share of the budget, off track past the budget. */
export const MONEY_AT_RISK_RATIO = 0.9

/** Task statuses that no longer count as open work. */
const CLOSED_TASK_STATUSES = ['done', 'cancelled']

/** Risk statuses that still count as open. */
const OPEN_RISK_STATUSES = ['open', 'mitigating']

/** Risk band to the status it suggests. */
const BAND_STATUS = { low: 'onTrack', medium: 'atRisk', high: 'offTrack' }

/**
 * `money` to `Money`, the suffix of `statusMoney`, `noteMoney` and `healthMoney`.
 *
 * @param {string} aspect The aspect.
 * @return {string}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function aspectKey(aspect) {
	return aspect.charAt(0).toUpperCase() + aspect.slice(1)
}

/**
 * The worst status in a list, or null when it holds none.
 *
 * @param {Array<string>} statuses The statuses.
 * @return {string|null}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function worstStatus(statuses = []) {
	let worst = -1
	for (const status of statuses || []) {
		worst = Math.max(worst, STATUSES.indexOf(status))
	}
	return worst >= 0 ? STATUSES[worst] : null
}

/**
 * @param {string|undefined} value A date or date-time.
 * @return {string} The YYYY-MM-DD part, or ''.
 */
function day(value) {
	return typeof value === 'string' ? value.slice(0, 10) : ''
}

/**
 * Today as YYYY-MM-DD in the viewer's time zone.
 *
 * @return {string}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function localToday() {
	const now = new Date()
	const pad = (n) => String(n).padStart(2, '0')
	return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

/**
 * The time suggestion: off track when the end date has passed with open
 * tasks, at risk when an open task is past its due date, else on track.
 *
 * @param {Array<object>} tasks The project's tasks.
 * @param {object|null} project The project, for its end date.
 * @param {string} today YYYY-MM-DD.
 * @return {{status: string, code: string, count: number}}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function suggestTime(tasks = [], project = null, today = localToday()) {
	const open = (tasks || []).filter((task) => task && !CLOSED_TASK_STATUSES.includes(task.status))
	const end = day(project?.endDate)
	if (end && end < today && open.length > 0) {
		return { status: 'offTrack', code: 'endPassed', count: open.length }
	}
	const late = open.filter((task) => day(task.dueDate) && day(task.dueDate) < today).length
	if (late > 0) {
		return { status: 'atRisk', code: 'late', count: late }
	}
	return { status: 'onTrack', code: 'onSchedule', count: 0 }
}

/**
 * The risk suggestion: the band of the highest-scored open risk.
 *
 * @param {Array<object>} risks The project's risks.
 * @param {object} scale The risk scale.
 * @return {{status: string, code: string, score?: number, title?: string}}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function suggestRisk(risks = [], scale) {
	const open = sortRisks((risks || []).filter((risk) => OPEN_RISK_STATUSES.includes(risk?.status ?? 'open')))
	if (open.length === 0) {
		return { status: 'onTrack', code: 'noOpenRisks' }
	}
	const top = open[0]
	const score = Number(top.score) || 0
	return { status: BAND_STATUS[riskBand(score, scale)], code: 'highestRisk', score, title: String(top.title ?? '') }
}

/**
 * The money suggestion from the budget and the cost so far. Without a budget
 * or without recorded costs there is no suggestion.
 *
 * @param {{budget: number, cost: number|null}} figures Budget and cost in the same currency.
 * @return {{status: string|null, code: string, percent?: number}}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function suggestMoney({ budget, cost } = {}) {
	const planned = Number(budget) || 0
	if (planned <= 0) {
		return { status: null, code: 'noBudget' }
	}
	if (cost === null || cost === undefined) {
		return { status: null, code: 'noCosts' }
	}
	const ratio = (Number(cost) || 0) / planned
	const percent = Math.round(ratio * 100)
	if (ratio > 1) {
		return { status: 'offTrack', code: 'overBudget', percent }
	}
	if (ratio > MONEY_AT_RISK_RATIO) {
		return { status: 'atRisk', code: 'nearBudget', percent }
	}
	return { status: 'onTrack', code: 'withinBudget', percent }
}

/**
 * Reports newest first: by report date, then by the time they were saved.
 *
 * @param {Array<object>} reports The reports.
 * @return {Array<object>}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function sortReports(reports = []) {
	return [...(reports || [])].sort((a, b) => day(b?.reportDate).localeCompare(day(a?.reportDate))
		|| String(b?.['@self']?.created ?? '').localeCompare(String(a?.['@self']?.created ?? '')))
}

/**
 * The payload of a new report. It carries no overall status: the server
 * calculates the worst of the six and overwrites any a client sends. An
 * aspect without a chosen status is left out, so the schema refuses it.
 *
 * @param {object} fields `reportDate` and, per aspect, `{status, note}`.
 * @param {string} projectId The project.
 * @return {object}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
export function reportPayload(fields, projectId) {
	const payload = { project: projectId, reportDate: day(fields?.reportDate) || localToday() }
	for (const aspect of ASPECTS) {
		const key = aspectKey(aspect)
		const status = fields?.[aspect]?.status
		if (STATUSES.includes(status)) {
			payload[`status${key}`] = status
		}
		const note = String(fields?.[aspect]?.note ?? '').trim()
		if (note) {
			payload[`note${key}`] = note
		}
	}
	return payload
}
