/**
 * Pure helpers for portfolios (projects-grouping-hierarchy-fields): who sees
 * a project, how project lists group and filter by portfolio, when a board is
 * read-only, and which risk scale a project uses.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
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
 * Whether a user sees a project in the lists: as a member or as a manager of its portfolio.
 *
 * @param {object} project The project.
 * @param {string} uid The user id.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.2
 */
export function canSeeProject(project, uid) {
	const listed = (field) => Array.isArray(project?.[field]) && project[field].includes(uid)
	return listed('members') || listed('portfolioReaders')
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
 * Whether a board opens read-only: the user reads the project as a manager of
 * its portfolio and is neither on it nor an admin. The server refuses their
 * writes the same way.
 *
 * @param {object|null} project The project.
 * @param {{uid: string, isAdmin?: boolean}|null} user The current user.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-1.6
 */
export function isReadOnlyFor(project, user) {
	if (!project || !user || user.isAdmin === true || project.owner === user.uid) {
		return false
	}
	const members = Array.isArray(project.members) ? project.members : []
	const readers = Array.isArray(project.portfolioReaders) ? project.portfolioReaders : []
	return !members.includes(user.uid) && readers.includes(user.uid)
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
