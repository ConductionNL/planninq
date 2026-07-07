/**
 * Timeline API — stateless read functions for the project Gantt view.
 *
 * Deliberately NOT a Pinia store: the timeline is a read-only surface, so it
 * needs no shared reactive state. Each call hits the planix read-only endpoint
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

	const url = generateUrl(`/apps/planix/api/projects/${projectId}/timeline`)
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
