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
 * The autocomplete URL for a search term, asking for users and groups, or
 * for the share types given.
 *
 * @param {string} term What the person typed.
 * @param {number} limit The most results to return.
 * @param {Array<number>} shareTypes The share types to ask for.
 * @return {string}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
 */
export function autocompleteUrl(term, limit = 10, shareTypes = [SHARE_TYPE_USER, SHARE_TYPE_GROUP]) {
	const params = new URLSearchParams({ search: term, itemType: '', itemId: '' })
	for (const shareType of shareTypes) {
		params.append('shareTypes[]', String(shareType))
	}
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
 * @param {Array<number>} options.shareTypes The share types to ask for (users and groups by default).
 * @param {AbortSignal} options.signal Cancels the request.
 * @return {Promise<Array<object>>}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-3.1
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
 */
export async function searchMembers(term, { members = [], groups = [], groupSubname = '', shareTypes = [SHARE_TYPE_USER, SHARE_TYPE_GROUP], signal } = {}) {
	if (typeof term !== 'string' || term.trim().length < MIN_SEARCH_LENGTH) {
		return []
	}
	const response = await fetch(autocompleteUrl(term.trim(), 10, shareTypes), {
		signal,
		headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	if (!response.ok) {
		throw new Error(`${response.status} ${response.statusText}`)
	}
	const body = await response.json()
	return toMemberOptions(body?.ocs?.data, { members, groups, groupSubname })
}

/** The user list and the group list behind each role, highest first. */
const ROLE_SOURCES = [
	['owner', 'ownerGroups'],
	['manager', 'managers', 'managerGroups'],
	['member', 'members', 'memberGroups'],
	['viewer', 'viewers', 'viewerGroups'],
]

/**
 * The rows of the Members tab: people by display name, then groups by name,
 * each once with the highest role it holds.
 *
 * @param {object} project The project.
 * @param {object} userNames Display names keyed by user id.
 * @param {object} groupNames Group names keyed by group id.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.3
 */
export function memberEntries(project, userNames = {}, groupNames = {}) {
	const list = (field) => (Array.isArray(project?.[field]) ? project[field] : [])
	const people = new Map()
	const groups = new Map()
	if (project?.owner) {
		people.set(project.owner, 'owner')
	}
	for (const [role, users, groupField] of ROLE_SOURCES) {
		if (role === 'owner') {
			list(users).forEach((gid) => groups.has(gid) || groups.set(gid, role))
			continue
		}
		list(users).forEach((uid) => people.has(uid) || people.set(uid, role))
		list(groupField).forEach((gid) => groups.has(gid) || groups.set(gid, role))
	}
	return [
		...[...people].map(([id, role]) => ({ key: `user:${id}`, id, type: 'user', role, name: userNames[id] || id })),
		...[...groups].map(([id, role]) => ({ key: `group:${id}`, id, type: 'group', role, name: groupNames[id] || id })),
	]
}
