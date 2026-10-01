/**
 * Finding people and groups to add to a project.
 *
 * Reads Nextcloud's core autocomplete endpoint, which every signed-in user may
 * call and which applies the admin's sharing settings (a user the caller may
 * not see is not listed). The provisioning API it replaces answered admins and
 * group sub-admins only.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { generateOcsUrl } from '@nextcloud/router'

/** Share type of a user in core's autocomplete. */
export const SHARE_TYPE_USER = 0

/** Share type of a group in core's autocomplete. */
export const SHARE_TYPE_GROUP = 1

/** Fewer characters than this ask nothing. */
export const MIN_SEARCH_LENGTH = 2

/**
 * The autocomplete URL for a search term, asking for users and groups.
 *
 * @param {string} term What the person typed.
 * @param {number} limit The most results to return.
 * @return {string}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
 */
export function autocompleteUrl(term, limit = 10) {
	const params = new URLSearchParams({ search: term, itemType: '', itemId: '' })
	params.append('shareTypes[]', String(SHARE_TYPE_USER))
	params.append('shareTypes[]', String(SHARE_TYPE_GROUP))
	params.append('limit', String(limit))
	params.append('format', 'json')
	return generateOcsUrl('/core/autocomplete/get') + '?' + params.toString()
}

/**
 * The picker options for an autocomplete answer, leaving out who is already on
 * the project.
 *
 * @param {Array<object>} data The `ocs.data` list of the answer.
 * @param {object} options What to leave out and how to describe a group.
 * @param {Array<string>} options.members User ids already on the project.
 * @param {Array<string>} options.groups Group ids already on the project.
 * @param {string} options.groupSubname The second line under a group.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
 */
export function toMemberOptions(data, { members = [], groups = [], groupSubname = '' } = {}) {
	return (Array.isArray(data) ? data : [])
		.filter((item) => item && typeof item.id === 'string' && item.id !== '')
		.map((item) => {
			const type = item.source === 'groups' ? 'group' : 'user'
			return {
				key: `${type}:${item.id}`,
				id: item.id,
				type,
				displayName: item.label || item.id,
				subname: type === 'group' ? groupSubname : (item.shareWithDisplayNameUnique !== item.id ? (item.shareWithDisplayNameUnique || '') : ''),
				isNoUser: type === 'group',
			}
		})
		.filter((option) => !(option.type === 'user' ? members : groups).includes(option.id))
}

/**
 * Search users and groups for the Members tab.
 *
 * @param {string} term What the person typed.
 * @param {object} options Who is already on the project, the group line and an abort signal.
 * @param {Array<string>} options.members User ids already on the project.
 * @param {Array<string>} options.groups Group ids already on the project.
 * @param {string} options.groupSubname The second line under a group.
 * @param {AbortSignal} options.signal Cancels the request.
 * @return {Promise<Array<object>>}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
 */
export async function searchMembers(term, { members = [], groups = [], groupSubname = '', signal } = {}) {
	if (typeof term !== 'string' || term.trim().length < MIN_SEARCH_LENGTH) {
		return []
	}
	const response = await fetch(autocompleteUrl(term.trim()), {
		signal,
		headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	if (!response.ok) {
		throw new Error(`${response.status} ${response.statusText}`)
	}
	const body = await response.json()
	return toMemberOptions(body?.ocs?.data, { members, groups, groupSubname })
}

/**
 * The rows of the Members tab: people by display name, then groups by name.
 *
 * @param {object} project The project.
 * @param {object} userNames Display names keyed by user id.
 * @param {object} groupNames Group names keyed by group id.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.2
 */
export function memberEntries(project, userNames = {}, groupNames = {}) {
	const users = Array.isArray(project?.members) ? project.members : []
	const groups = Array.isArray(project?.memberGroups) ? project.memberGroups : []
	return [
		...users.map((id) => ({ key: `user:${id}`, id, type: 'user', name: userNames[id] || id })),
		...groups.map((id) => ({ key: `group:${id}`, id, type: 'group', name: groupNames[id] || id })),
	]
}
