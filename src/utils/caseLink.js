/**
 * The case link of a project (`caseReference`) and of a task (`zaakUuid`):
 * whether the case app is there, who may change a project's link, the case's
 * name and address, and the search the Case field offers.
 *
 * Cases live in the case app's register (`dossiq`, schema `case`); they are
 * read through OpenRegister's object API, which only returns cases the
 * person may open.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * The case app, and the register and schema its cases live in.
 */
export const CASE_APP = 'dossiq'
export const CASE_REGISTER = 'dossiq'
export const CASE_SCHEMA = 'case'

/**
 * Fewer characters than this do not start a search.
 */
export const MIN_SEARCH_LENGTH = 2

/**
 * How many cases one search offers.
 */
export const SEARCH_LIMIT = 20

/**
 * Case names already read, by case id.
 *
 * @type {Map<string, Promise<string|null>>}
 */
const titles = new Map()

/**
 * Whether the case app is enabled for this person. Nextcloud lists the web
 * root of every app enabled for the person on `OC.appswebroots`.
 *
 * @param {object|undefined} oc The `OC` global.
 * @return {boolean}
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-2.1
 */
export function caseAppInstalled(oc = globalThis.OC) {
	return Boolean(oc?.appswebroots && Object.hasOwn(oc.appswebroots, CASE_APP))
}

/**
 * Whether a project role may change the project's case link: the owner or a
 * manager, as for the other Details fields, or an administrator.
 *
 * @param {string} role A role from projectRole().
 * @param {boolean} isAdmin Whether the person is an administrator.
 * @return {boolean}
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
 */
export function canEditCaseLink(role, isAdmin) {
	return isAdmin === true || role === 'owner' || role === 'manager'
}

/**
 * The address of a case in the case app.
 *
 * @param {string} caseId The case id.
 * @return {string}
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.2
 */
export function caseUrl(caseId) {
	return generateUrl('/apps/{app}/cases/{id}', { app: CASE_APP, id: String(caseId) })
}

/**
 * A case's name: its title, else its identifier, else its id.
 *
 * @param {object|null} item A case object.
 * @return {string}
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.2
 */
export function caseLabel(item) {
	const title = String(item?.title || '').trim()
	if (title) {
		return title
	}
	const identifier = String(item?.identifier || '').trim()
	return identifier || String(item?.id || item?.['@self']?.id || '')
}

/**
 * A case as a select option.
 *
 * @param {object} item A case object.
 * @return {{id: string, label: string}}
 */
export function caseOption(item) {
	return { id: String(item?.id || item?.['@self']?.id || ''), label: caseLabel(item) }
}

/**
 * The value a Case field writes: the picked case's id, or null when cleared.
 *
 * @param {{id?: string}|null} option The picked option.
 * @return {string|null}
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
 */
export function caseValue(option) {
	const id = String(option?.id || '').trim()
	return id || null
}

/**
 * The cases whose text matches a search term, as select options. A term
 * shorter than MIN_SEARCH_LENGTH asks nothing.
 *
 * @param {string} term What the person typed.
 * @param {{signal?: AbortSignal}} [options] Lets a newer search abort this one.
 * @return {Promise<Array<{id: string, label: string}>>}
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.1
 */
export async function searchCases(term, options = {}) {
	const query = String(term || '').trim()
	if (query.length < MIN_SEARCH_LENGTH) {
		return []
	}
	const url = generateUrl('/apps/openregister/api/objects/{register}/{schema}', { register: CASE_REGISTER, schema: CASE_SCHEMA })
	const response = await axios.get(url, { params: { _search: query, _limit: SEARCH_LIMIT }, signal: options.signal })
	const rows = Array.isArray(response.data?.results) ? response.data.results : []
	return rows.map(caseOption).filter((option) => option.id !== '')
}

/**
 * A case's name, read once per case. Null when the case cannot be read: it
 * is gone, the person may not open it, or the case app is not there.
 *
 * @param {string} caseId The case id.
 * @return {Promise<string|null>}
 *
 * @spec openspec/changes/case-link-editing/tasks.md#task-1.2
 */
export function caseTitle(caseId) {
	const id = String(caseId || '').trim()
	if (!id) {
		return Promise.resolve(null)
	}
	if (!titles.has(id)) {
		const url = generateUrl('/apps/openregister/api/objects/{register}/{schema}/{id}', { register: CASE_REGISTER, schema: CASE_SCHEMA, id })
		titles.set(id, axios.get(url).then((response) => caseLabel(response.data) || null, () => {
			titles.delete(id)
			return null
		}))
	}
	return titles.get(id)
}

/**
 * Forget the names read so far.
 *
 * @spec exclude Test seam: clears the module cache between tests.
 */
export function forgetCaseTitles() {
	titles.clear()
}
