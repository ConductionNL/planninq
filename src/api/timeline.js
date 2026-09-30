/**
 * Timeline API — stateless read functions for the project Gantt view.
 *
 * Deliberately NOT a Pinia store: it needs no shared reactive state. The
 * endpoint is read-only; the timeline view edits task dates through the
 * object API (`updateTask`), not through here (planning-timeline-editing).
 * Each call hits the Planninq read-only endpoint
 * `GET /api/projects/{projectId}/timeline`, which returns the project's tasks
 * (scheduled + unscheduled) and its existing dependency links, RBAC-scoped by
 * OpenRegister server-side. Nothing here creates or mutates an object.
 *
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Fetch a project's timeline (scheduled tasks, unscheduled backlog, and the
 * existing dependency edges) for an optional [from, to] window.
 *
 * @param {string} projectId The OR UUID of the project.
 * @param {string|null} [from] Optional ISO date lower bound.
 * @param {string|null} [to] Optional ISO date upper bound.
 * @return {Promise<{projectId: string, window: object, tasks: Array<object>, unscheduled: Array<object>, dependencies: Array<object>}>}
 *
 * @spec openspec/changes/gantt-timeline-view/specs/gantt-timeline-view/spec.md#requirement-a-projects-tasks-can-be-viewed-on-a-time-axis
 */
export async function fetchProjectTimeline(projectId, from = null, to = null) {
	const params = {}
	if (from) {
		params.from = from
	}
	if (to) {
		params.to = to
	}

	const url = generateUrl(`/apps/planninq/api/projects/${projectId}/timeline`)
	const response = await axios.get(url, { params })
	const data = response.data || {}

	return {
		projectId: data.projectId || projectId,
		window: data.window || { from, to },
		tasks: Array.isArray(data.tasks) ? data.tasks : [],
		unscheduled: Array.isArray(data.unscheduled) ? data.unscheduled : [],
		dependencies: Array.isArray(data.dependencies) ? data.dependencies : [],
	}
}

/**
 * Fetch several projects for one time axis through a single RBAC-scoped
 * request per 50 projects. Projects the caller cannot read come back under
 * `skipped`; `dependencies` holds edges between any two tasks in the answer.
 *
 * @param {Array<string>} projectIds The OR UUIDs of the projects.
 * @return {Promise<{projects: Array<object>, dependencies: Array<object>, skipped: Array<string>}>}
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-3.1
 */
export async function fetchPortfolioTimeline(projectIds = []) {
	const ids = [...new Set((projectIds || []).filter(Boolean).map(String))]
	const answer = { projects: [], dependencies: [], skipped: [] }
	const url = generateUrl('/apps/planninq/api/timeline')
	for (let offset = 0; offset < ids.length; offset += 50) {
		const response = await axios.get(url, { params: { projects: ids.slice(offset, offset + 50).join(',') } })
		const data = response.data || {}
		answer.projects.push(...(Array.isArray(data.projects) ? data.projects : []))
		answer.dependencies.push(...(Array.isArray(data.dependencies) ? data.dependencies : []))
		answer.skipped.push(...(Array.isArray(data.skipped) ? data.skipped : []))
	}
	return answer
}
