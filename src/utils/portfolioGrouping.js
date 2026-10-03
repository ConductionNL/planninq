/**
 * Pure helpers for portfolios (projects-grouping-hierarchy-fields): who sees
 * a project, how project lists group and filter by portfolio, when a board is
 * read-only, and which risk scale a project uses.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { projectRole } from './projectRole.js'
import { parseRiskScale } from './riskHelpers.js'

/** The group and filter value for projects outside any portfolio. */
export const NO_PORTFOLIO = 'none'

/**
 * The portfolio id a project points at, from a UUID or a resolved reference.
 *
 * @param {object} project The project.
 * @return {string}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
 */
export function portfolioIdOf(project) {
	const value = project?.portfolio
	if (value && typeof value === 'object') {
		return String(value.id ?? value['@self']?.id ?? '')
	}
	return typeof value === 'string' ? value : ''
}

/**
 * Whether a user sees a project in the lists: any role on it, directly or
 * through one of their groups, or as a manager of its portfolio.
 *
 * @param {object} project The project.
 * @param {string} uid The user id.
 * @param {Array<string>} groupIds The user's group ids.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.2
 */
export function canSeeProject(project, uid, groupIds = []) {
	return projectRole(project, uid, groupIds) !== 'none'
}

/**
 * Portfolios in list order: by `order`, then by title.
 *
 * @param {Array<object>} portfolios The portfolios.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
 */
export function sortPortfolios(portfolios = []) {
	return [...(portfolios || [])].sort((a, b) => (Number(a?.order) || 0) - (Number(b?.order) || 0)
		|| String(a?.title ?? '').localeCompare(String(b?.title ?? '')))
}

/**
 * Projects grouped by portfolio: portfolios by `order` then title, each with
 * its projects in the order given, and the projects outside any known
 * portfolio last. Portfolios without a visible project are left out.
 *
 * @param {Array<object>} projects The projects.
 * @param {Array<object>} portfolios The portfolios.
 * @return {Array<{id: string, title: string, color: string, projects: Array<object>}>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
 */
export function groupByPortfolio(projects = [], portfolios = []) {
	const groups = new Map(sortPortfolios(portfolios).map((p) => [String(p.id), { id: String(p.id), title: String(p.title ?? ''), color: String(p.color ?? ''), projects: [] }]))
	const none = { id: NO_PORTFOLIO, title: '', color: '', projects: [] }
	for (const project of projects || []) {
		const group = groups.get(portfolioIdOf(project)) || none
		group.projects.push(project)
	}
	return [...groups.values(), none].filter((group) => group.projects.length > 0)
}

/**
 * Projects of one portfolio, outside any portfolio, or all ('').
 *
 * @param {Array<object>} projects The projects.
 * @param {string} portfolioId A portfolio id, NO_PORTFOLIO, or '' for all.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
 */
export function filterByPortfolio(projects = [], portfolioId = '') {
	if (!portfolioId) {
		return [...(projects || [])]
	}
	if (portfolioId === NO_PORTFOLIO) {
		return (projects || []).filter((project) => !portfolioIdOf(project))
	}
	return (projects || []).filter((project) => portfolioIdOf(project) === portfolioId)
}

/**
 * Whether a board opens read-only: the user only reads the project (a viewer,
 * directly or through a group, or a manager of its portfolio) and is not an
 * admin. The server refuses their writes the same way.
 *
 * @param {object|null} project The project.
 * @param {{uid: string, isAdmin?: boolean}|null} user The current user.
 * @param {Array<string>} groupIds The user's group ids.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.2
 */
export function isReadOnlyFor(project, user, groupIds = []) {
	if (!project || !user || user.isAdmin === true) {
		return false
	}
	return projectRole(project, user.uid, groupIds) === 'viewer'
}

/**
 * Why a board opens read-only, so it can say so in the right words: `viewer`
 * for someone on the project's viewer lists (in person or through a group),
 * `portfolio` for a manager of its portfolio who is on no list, null when the
 * board is not read-only.
 *
 * @param {object|null} project The project.
 * @param {{uid: string, isAdmin?: boolean}|null} user The current user.
 * @param {Array<string>} groupIds The user's group ids.
 * @return {'viewer'|'portfolio'|null}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.2
 */
export function readOnlyReason(project, user, groupIds = []) {
	if (!isReadOnlyFor(project, user, groupIds)) {
		return null
	}
	const withoutPortfolio = { ...project, portfolioReaders: [] }
	return projectRole(withoutPortfolio, user.uid, groupIds) === 'viewer' ? 'viewer' : 'portfolio'
}

/**
 * The risk scale of a project: its portfolio's own scale when set, else the app-wide one.
 *
 * @param {object} project The project.
 * @param {Array<object>} portfolios The portfolios.
 * @param {object} appScale The app-wide scale.
 * @return {object}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.4
 */
export function portfolioRiskScale(project, portfolios = [], appScale) {
	const id = portfolioIdOf(project)
	const own = id ? (portfolios || []).find((p) => String(p?.id) === id)?.riskScale : ''
	return own ? parseRiskScale(own) : appScale
}
