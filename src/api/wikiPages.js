// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
// SPDX-License-Identifier: EUPL-1.2

/**
 * The project wiki's calls to OpenRegister (projects-wiki): the pages of a
 * project, a save that carries `_expectedUpdated` so it cannot overwrite a
 * newer version, the page lock, the audit trail and the revert. Every call
 * runs as the signed-in person, so OpenRegister applies the project's rights.
 *
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-page-keeps-its-history-and-one-writer-at-a-time
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const BASE = '/apps/openregister/api/objects/planninq/wikiPage'

/**
 * @param {string} [id] The page id; none for the collection.
 * @param {string} [action] A sub-resource: lock, unlock, audit-trails, revert.
 * @return {string}
 */
function url(id, action) {
	let path = BASE
	if (id) {
		path += `/${encodeURIComponent(id)}`
	}
	if (action) {
		path += `/${action}`
	}
	return generateUrl(path)
}

/**
 * The rows of a list answer, whatever its shape.
 *
 * @param {object|Array} data The answer.
 * @return {Array<object>}
 */
function rows(data) {
	if (Array.isArray(data)) {
		return data
	}
	return Array.isArray(data?.results) ? data.results : []
}

/**
 * Every page of a project.
 *
 * @param {string} projectId The project.
 * @return {Promise<Array<object>>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#requirement-a-project-member-keeps-wiki-pages-next-to-the-tasks
 */
export async function fetchWikiPages(projectId) {
	const response = await axios.get(url(), { params: { project: projectId, _limit: 1000 } })
	return rows(response.data)
}

/**
 * Create a page, or change the given fields of one. A change sends the
 * `updated` time the writer saw as `_expectedUpdated`, so OpenRegister
 * refuses it when someone saved in between.
 *
 * @param {object} page The page, or its id and the fields to change.
 * @param {string|null} [expectedUpdated] The `updated` time the writer saw.
 * @return {Promise<object>} The saved page.
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-second-writer-sees-the-page-is-being-edited
 */
export async function saveWikiPage(page, expectedUpdated = null) {
	const { id, ...fields } = page
	if (!id) {
		return (await axios.post(url(), fields)).data
	}
	const body = expectedUpdated ? { ...fields, _expectedUpdated: expectedUpdated } : fields
	return (await axios.patch(url(id), body)).data
}

/**
 * Delete a page.
 *
 * @param {string} id The page.
 * @return {Promise<void>}
 */
export async function deleteWikiPage(id) {
	await axios.delete(url(id))
}

/**
 * Take the page's lock for the editor.
 *
 * @param {string} id The page.
 * @return {Promise<object>} The locked page.
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-second-writer-sees-the-page-is-being-edited
 */
export async function lockWikiPage(id) {
	return (await axios.post(url(id, 'lock'), {})).data
}

/**
 * Release the page's lock.
 *
 * @param {string} id The page.
 * @return {Promise<void>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-second-writer-sees-the-page-is-being-edited
 */
export async function unlockWikiPage(id) {
	await axios.post(url(id, 'unlock'), {})
}

/**
 * The page's saved versions, newest first.
 *
 * @param {string} id The page.
 * @return {Promise<Array<object>>}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-restoring-an-earlier-version
 */
export async function fetchWikiPageHistory(id) {
	const entries = rows((await axios.get(url(id, 'audit-trails'))).data)
	return [...entries].sort((a, b) => String(b.created || '').localeCompare(String(a.created || '')))
}

/**
 * Restore the page as it was after an audit trail entry. The revert is
 * itself a new entry in the history.
 *
 * @param {string} id The page.
 * @param {string|number} auditTrailId The version to restore.
 * @return {Promise<object>} The restored page.
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-restoring-an-earlier-version
 */
export async function restoreWikiPage(id, auditTrailId) {
	return (await axios.post(url(id, 'revert'), { auditTrailId })).data
}

/**
 * Who holds the page's lock, or null when nobody does or it expired.
 *
 * @param {object} page The page.
 * @param {Date} [now] The time to judge expiry by.
 * @return {{user: string, displayName: string}|null}
 * @spec openspec/changes/projects-wiki/specs/project-wiki/spec.md#scenario-a-second-writer-sees-the-page-is-being-edited
 */
export function lockHolder(page, now = new Date()) {
	const lock = page?.['@self']?.locked ?? page?.locked
	if (!lock || typeof lock !== 'object') {
		return null
	}
	const expires = lock.expiration ? new Date(lock.expiration) : null
	if (expires && !Number.isNaN(expires.getTime()) && expires <= now) {
		return null
	}
	const user = String(lock.user || lock.userId || '')
	if (user === '') {
		return null
	}
	return { user, displayName: String(lock.displayName || lock.userName || user) }
}
