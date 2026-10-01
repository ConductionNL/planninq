// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * Pure helpers for reports a user builds (portfolio-flow-reports section 3).
 * A report stores a query, never results: the page turns it into the same
 * `dataSource.aggregate` the manifest dashboards use, and OpenRegister runs
 * it with the viewer's own rights. Only equality filters are built, because
 * OpenRegister's aggregation silently ignores any other operator.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */

/** Task fields a report may filter on, by equality only. */
export const REPORT_FILTER_FIELDS = ['status', 'priority', 'assignedTo', 'labels', 'column', 'issueType']

/** Fields a report may group by. */
export const REPORT_GROUP_BY = ['status', 'priority', 'assignedTo', 'labels', 'project', 'column']

/** Fields a report may sum. */
export const REPORT_SUM_FIELDS = ['storyPoints', 'estimatedDuration']

/** How a report is shown. */
export const REPORT_DISPLAYS = ['table', 'bar', 'donut']

/**
 * The equality filters of a report: known fields with a scalar value only.
 *
 * @param {object} filters Field to value
 * @return {object}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */
export function equalityFilters(filters) {
	const out = {}
	for (const [field, value] of Object.entries(filters || {})) {
		if (!REPORT_FILTER_FIELDS.includes(field)) {
			continue
		}
		if (typeof value === 'string' && value !== '') {
			out[field] = value
		} else if (typeof value === 'number' || typeof value === 'boolean') {
			out[field] = value
		}
	}
	return out
}

/**
 * The widget dataSource for one project of a report.
 *
 * @param {object} report The report: filters, groupBy, metric, sumField
 * @param {string} projectId One project the viewer can read
 * @return {object}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */
export function reportDataSource(report, projectId) {
	const aggregate = { groupBy: REPORT_GROUP_BY.includes(report.groupBy) ? report.groupBy : 'status', metric: report.metric === 'sum' ? 'sum' : 'count' }
	if (aggregate.metric === 'sum') {
		aggregate.sumField = REPORT_SUM_FIELDS.includes(report.sumField) ? report.sumField : 'storyPoints'
	}
	return {
		register: 'planninq',
		schema: 'task',
		filter: { ...equalityFilters(report.filters), project: projectId },
		aggregate,
	}
}

/**
 * Buckets from several projects added up by key.
 *
 * @param {Array<Array<{key: string, value: number}>>} perProject Buckets per project
 * @return {Array<{key: string, value: number}>} Largest first
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export function mergeBuckets(perProject) {
	const totals = new Map()
	for (const buckets of perProject || []) {
		for (const bucket of buckets || []) {
			totals.set(bucket.key, (totals.get(bucket.key) || 0) + Number(bucket.value || 0))
		}
	}
	return [...totals.entries()].map(([key, value]) => ({ key, value })).sort((a, b) => b.value - a.value)
}

/**
 * How many of a report's projects the viewer cannot read.
 *
 * @param {string[]} reportProjects The report's projects
 * @param {string[]} readable The projects the viewer can read
 * @return {{hidden: number, total: number, visible: string[]}}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export function visibleProjects(reportProjects, readable) {
	const can = new Set(readable || [])
	const visible = (reportProjects || []).filter((id) => can.has(id))
	return { hidden: (reportProjects || []).length - visible.length, total: (reportProjects || []).length, visible }
}

/**
 * The query parameters of OpenRegister's grouped aggregation for a dataSource,
 * as CnChartWidget sends them: `groupBy`, `metric`, `field` for a sum, and
 * one `filter[field]` per equality filter.
 *
 * @param {object} dataSource From reportDataSource()
 * @return {object}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export function groupedParams(dataSource) {
	const params = { groupBy: dataSource.aggregate.groupBy, metric: dataSource.aggregate.metric }
	if (dataSource.aggregate.metric === 'sum') {
		params.field = dataSource.aggregate.sumField
	}
	for (const [field, value] of Object.entries(dataSource.filter || {})) {
		params[`filter[${field}]`] = value
	}
	return params
}

/**
 * Reports split into the viewer's own and the ones others shared.
 *
 * @param {Array<object>} reports Reports the viewer can read
 * @param {string|undefined} uid The viewer
 * @return {{mine: Array<object>, shared: Array<object>}}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export function splitReports(reports, uid) {
	const list = (reports || []).map((report) => ({ ...report, id: report.id ?? report['@self']?.id }))
	const byTitle = (a, b) => String(a.title || '').localeCompare(String(b.title || ''))
	return {
		mine: list.filter((report) => report.owner === uid).sort(byTitle),
		shared: list.filter((report) => report.owner !== uid && report.shared === 'readers').sort(byTitle),
	}
}

/**
 * Donut segments on a circle of circumference 100 (r = 15.9155): each
 * bucket's share as a stroke dash, starting at the top and going clockwise.
 *
 * @param {Array<{key: string, value: number}>} buckets The buckets
 * @return {Array<{key: string, value: number, dash: string, offset: number}>}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export function donutSegments(buckets) {
	const total = (buckets || []).reduce((sum, bucket) => sum + Number(bucket.value || 0), 0)
	if (total === 0) {
		return []
	}
	let done = 0
	return buckets.map((bucket) => {
		const share = Math.round((Number(bucket.value || 0) / total) * 10000) / 100
		const segment = { key: bucket.key, value: bucket.value, dash: `${share} ${Math.round((100 - share) * 100) / 100}`, offset: Math.round((25 - done) * 100) / 100 }
		done += share
		return segment
	})
}
