/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Sharing a project's board by link (projects-public-share decisions 1 and 4).
 *
 * A share is two OpenRegister things: a view that selects the project's
 * columns and tasks by `project`, and a `read` access link on that view.
 * OpenRegister owns the anchor, the end date, the password and the record of
 * every use; this module only creates, lists and switches them off.
 *
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */

import { buildHeaders } from '@conduction/nextcloud-vue'
import { generateUrl } from '@nextcloud/router'

/** The end date a new link gets, in days from today. */
export const DEFAULT_LINK_DAYS = 30

/** The furthest end date a link may have, in days from today. */
export const MAX_LINK_DAYS = 365

const DAY = 24 * 3600 * 1000

/**
 * A date as `YYYY-MM-DD`, in UTC.
 *
 * @param {Date} date The date.
 * @return {string}
 */
function isoDay(date) {
	return date.toISOString().slice(0, 10)
}

/**
 * The end date a new link starts with: 30 days from today.
 *
 * @param {Date} [now] Today.
 * @return {string} The date as `YYYY-MM-DD`.
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export function defaultEndDate(now = new Date()) {
	return isoDay(new Date(now.getTime() + DEFAULT_LINK_DAYS * DAY))
}

/**
 * The latest end date a link may have: one year from today.
 *
 * @param {Date} [now] Today.
 * @return {string} The date as `YYYY-MM-DD`.
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export function latestEndDate(now = new Date()) {
	return isoDay(new Date(now.getTime() + MAX_LINK_DAYS * DAY))
}

/**
 * Why an end date cannot be used, or null when it can.
 *
 * @param {string} endDate The chosen date as `YYYY-MM-DD`.
 * @param {Date} [now] Today.
 * @return {string|null} `missing`, `past` or `too-far`, or null.
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export function endDateRefusal(endDate, now = new Date()) {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(String(endDate ?? ''))) {
		return 'missing'
	}

	if (endDate <= isoDay(now)) {
		return 'past'
	}

	if (endDate > latestEndDate(now)) {
		return 'too-far'
	}

	return null
}

/**
 * The view's stored query for a project: its columns and tasks, by `project`.
 *
 * @param {string} projectId The project.
 * @return {{registers: string[], schemas: string[], filters: {project: string}}}
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export function projectViewQuery(projectId) {
	return { registers: ['planninq'], schemas: ['column', 'task'], filters: { project: String(projectId) } }
}

/**
 * Whether a view is the share view of a project.
 *
 * @param {object} view A view as OpenRegister lists it.
 * @param {string} projectId The project.
 * @return {boolean}
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export function isProjectView(view, projectId) {
	const query = view?.query ?? {}
	const schemas = Array.isArray(query.schemas) ? query.schemas : []
	return !!projectId && String(query.filters?.project ?? '') === String(projectId) && schemas.includes('task')
}

/**
 * The id a link names a view by.
 *
 * @param {object} view A view.
 * @return {string}
 */
export function viewIdOf(view) {
	return String(view?.uuid ?? view?.id ?? '')
}

/**
 * Whether a link is a live link on one of the views.
 *
 * @param {object} link A link as OpenRegister lists it.
 * @param {object[]} views The project's views.
 * @return {boolean}
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export function isLinkOnViews(link, views) {
	if (link?.subjectType !== 'view' || link?.live === false) {
		return false
	}

	const names = new Set()
	for (const view of views) {
		for (const id of [view?.uuid, view?.id]) {
			if (id !== undefined && id !== null && String(id) !== '') {
				names.add(String(id))
			}
		}
	}

	return names.has(String(link.subjectId ?? ''))
}

/**
 * The public board's address for a link.
 *
 * @param {string} anchor The link's anchor.
 * @return {string} The absolute URL.
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export function publicBoardUrl(anchor) {
	const path = generateUrl('/apps/planninq/public/{anchor}', { anchor })
	return typeof window === 'undefined' ? path : window.location.origin + path
}

/**
 * Fetch JSON from OpenRegister, throwing on a refusal.
 *
 * @param {string} path The path under /apps/openregister/api.
 * @param {object} [init] Fetch options.
 * @return {Promise<object>}
 */
async function orFetch(path, init = {}) {
	const response = await fetch(generateUrl('/apps/openregister/api' + path), { ...init, headers: buildHeaders() })
	if (!response.ok) {
		const error = new Error(`OpenRegister answered ${response.status}`)
		error.status = response.status
		throw error
	}

	const text = await response.text()
	return text ? JSON.parse(text) : {}
}

/**
 * The project's share views the caller can see.
 *
 * @param {string} projectId The project.
 * @return {Promise<object[]>}
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export async function projectViews(projectId) {
	const body = await orFetch('/views')
	const views = Array.isArray(body?.results) ? body.results : []
	return views.filter((view) => isProjectView(view, projectId))
}

/**
 * The project's share view, created when it has none yet.
 *
 * @param {{id: string, title: string}} project The project.
 * @return {Promise<object>} The view.
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export async function ensureProjectView(project) {
	const existing = await projectViews(project.id)
	if (existing.length > 0) {
		return existing[0]
	}

	const body = await orFetch('/views', {
		method: 'POST',
		body: JSON.stringify({ name: project.title, query: projectViewQuery(project.id) }),
	})
	return body?.view ?? body
}

/**
 * Mint a read link on the project's view.
 *
 * @param {{id: string, title: string}} project The project.
 * @param {{endDate: string, password?: string}} terms The end date (`YYYY-MM-DD`) and an optional password.
 * @return {Promise<object>} The minted link.
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export async function createProjectLink(project, terms) {
	const view = await ensureProjectView(project)
	const payload = {
		subjectType: 'view',
		subjectId: viewIdOf(view),
		capabilities: ['read'],
		expiresAt: `${terms.endDate}T23:59:59Z`,
		label: project.title,
	}
	if (terms.password) {
		payload.password = terms.password
	}

	const body = await orFetch('/access-links', { method: 'POST', body: JSON.stringify(payload) })
	return body?.link ?? body
}

/**
 * The live links on the project's views that OpenRegister lists for the caller.
 *
 * @param {string} projectId The project.
 * @param {object[]} [views] The project's views, when already read.
 * @return {Promise<object[]>}
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export async function projectLinks(projectId, views = null) {
	const onViews = views ?? await projectViews(projectId)
	if (onViews.length === 0) {
		return []
	}

	const body = await orFetch('/access-links')
	const links = Array.isArray(body?.results) ? body.results : []
	return links.filter((link) => isLinkOnViews(link, onViews))
}

/**
 * Switch a link off for good.
 *
 * @param {number} linkId The link's row id.
 * @return {Promise<void>}
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.1
 */
export async function switchOffLink(linkId) {
	await orFetch(`/access-links/${encodeURIComponent(String(linkId))}`, { method: 'DELETE' })
}

/**
 * Revoke the project's links and delete its share views, before the project goes.
 *
 * Deleting the view also ends every link on it, including links someone else
 * made, because a view link serves nothing once its view is gone.
 *
 * @param {string} projectId The project.
 * @return {Promise<{revoked: number, viewsDeleted: number, failed: number}>}
 * @spec openspec/changes/projects-public-share/tasks.md#task-3.2
 */
export async function removeProjectShares(projectId) {
	const result = { revoked: 0, viewsDeleted: 0, failed: 0 }
	const views = await projectViews(projectId)
	if (views.length === 0) {
		return result
	}

	for (const link of await projectLinks(projectId, views)) {
		try {
			await switchOffLink(link.id)
			result.revoked++
		} catch {
			result.failed++
		}
	}

	for (const view of views) {
		try {
			await orFetch(`/views/${encodeURIComponent(viewIdOf(view))}`, { method: 'DELETE' })
			result.viewsDeleted++
		} catch {
			result.failed++
		}
	}

	return result
}
