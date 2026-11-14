/**
 * Pure helpers for the portfolio overview (portfolio-status-overview,
 * section 2): the roll-up of the six aspects over a portfolio's projects,
 * the out-of-date marker and who sees the money columns.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { aspectKey, ASPECTS, STATUSES, worstStatus } from './statusReports.js'

/** The reporting period when the admin setting is missing or not a whole number. */
export const DEFAULT_REPORT_PERIOD_DAYS = 30

/** Milliseconds in a day. */
const MS_PER_DAY = 86400000

/**
 * One aspect over a set of projects: how many are on track, at risk and off
 * track, how many have no report at all, and the worst state among them.
 * A project without a report never counts as on track.
 *
 * @param {Array<object>} projects The projects, with their copied health fields.
 * @param {string} aspect One of ASPECTS.
 * @return {{onTrack: number, atRisk: number, offTrack: number, noReport: number, state: string|null}}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.2
 */
export function rollupAspect(projects = [], aspect) {
	const counts = { onTrack: 0, atRisk: 0, offTrack: 0, noReport: 0 }
	const seen = []
	for (const project of projects || []) {
		if (!project?.healthDate) {
			counts.noReport++
			continue
		}
		const status = project[`health${aspectKey(aspect)}`]
		if (STATUSES.includes(status)) {
			counts[status]++
			seen.push(status)
		}
	}
	return { ...counts, state: worstStatus(seen) }
}

/**
 * The roll-up of all six aspects, in their fixed order.
 *
 * @param {Array<object>} projects The projects.
 * @return {Array<{aspect: string, onTrack: number, atRisk: number, offTrack: number, noReport: number, state: string|null}>}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.2
 */
export function portfolioRollup(projects = []) {
	return ASPECTS.map((aspect) => ({ aspect, ...rollupAspect(projects, aspect) }))
}

/**
 * Whether a report date lies more than the reporting period before today.
 *
 * @param {string} reportDate The report date (YYYY-MM-DD or a date-time).
 * @param {string} today Today as YYYY-MM-DD.
 * @param {number|string} periodDays The admin's reporting period.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.2
 */
export function isOutOfDate(reportDate, today, periodDays) {
	const date = typeof reportDate === 'string' ? reportDate.slice(0, 10) : ''
	if (!date || !today) {
		return false
	}
	const period = /^\d+$/.test(String(periodDays ?? '').trim()) ? Number(String(periodDays).trim()) : DEFAULT_REPORT_PERIOD_DAYS
	const age = (Date.parse(`${today}T00:00:00Z`) - Date.parse(`${date}T00:00:00Z`)) / MS_PER_DAY
	return age > period
}

/**
 * Whether the viewer sees a project's money columns: admins, the project's
 * owner and the managers of its portfolio. Until project roles exist
 * (projects-members-and-roles) these are the people who answer for money.
 *
 * @param {object} project The project.
 * @param {object|null} portfolio The portfolio being viewed.
 * @param {{uid: string, isAdmin?: boolean}|null} user The viewer.
 * @return {boolean}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-2.1
 */
export function canSeeMoney(project, portfolio, user) {
	if (!user || !portfolio) {
		return false
	}
	if (user.isAdmin === true || project?.owner === user.uid) {
		return true
	}
	return Array.isArray(portfolio.managers) && portfolio.managers.includes(user.uid)
}
