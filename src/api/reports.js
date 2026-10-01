/**
 * Reports API (portfolio-flow-reports section 3): saved reports are `taskReport`
 * objects in OpenRegister, read with the viewer's rights; a report's numbers
 * come from OpenRegister's grouped aggregation, one call per project the
 * viewer can read, so a shared report never shows more than they may see.
 *
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { groupedParams, mergeBuckets, reportDataSource } from '../utils/reportBuilder.js'

const REPORTS = '/apps/openregister/api/objects/planninq/taskReport'

/**
 * Every report the viewer can read (their own and shared ones).
 *
 * @return {Promise<Array<object>>}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export async function fetchReports() {
	const response = await axios.get(generateUrl(REPORTS), { params: { _limit: 500 } })
	const data = response.data || {}
	return Array.isArray(data.results) ? data.results : (Array.isArray(data) ? data : [])
}

/**
 * One report.
 *
 * @param {string} id The report's UUID
 * @return {Promise<object>}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export async function fetchReport(id) {
	const response = await axios.get(generateUrl(`${REPORTS}/${id}`))
	return response.data || {}
}

/**
 * Save a report: POST without an id, PATCH with one. The server sets the owner.
 *
 * @param {object} report The fields, with `id` for an existing report
 * @return {Promise<object>} The saved report
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.2
 */
export async function saveReport(report) {
	const { id, ...fields } = report
	const response = id
		? await axios.patch(generateUrl(`${REPORTS}/${id}`), fields)
		: await axios.post(generateUrl(REPORTS), fields)
	return response.data || {}
}

/**
 * The report's buckets over the projects the viewer can read, added up.
 *
 * @param {object} report The report
 * @param {string[]} projectIds The report's projects the viewer can read
 * @return {Promise<Array<{key: string, value: number}>>}
 * @spec openspec/changes/archive/2026-10-01-portfolio-flow-reports/tasks.md#task-3.3
 */
export async function runReport(report, projectIds) {
	const perProject = await Promise.all(projectIds.map(async (projectId) => {
		const dataSource = reportDataSource(report, projectId)
		const url = generateUrl('/apps/openregister/api/objects/aggregations/{register}/{schema}/grouped', { register: dataSource.register, schema: dataSource.schema })
		const response = await axios.get(url, { params: groupedParams(dataSource) })
		return (response.data && response.data.groups) || []
	}))
	return mergeBuckets(perProject)
}
