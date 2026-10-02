/**
 * A person's role on a project, from the project's user and group lists
 * (projects-members-and-roles, Decision 2 and 3).
 *
 * The browser uses it to decide what to show; the server's schema rules
 * decide what is allowed. When a person is in more than one list, the
 * highest role wins.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */
import { loadState } from '@nextcloud/initial-state'

/** Roles from lowest to highest. */
export const ROLES = ['none', 'viewer', 'member', 'manager', 'owner']

/** The roles a manager hands out on the Members tab. */
export const ASSIGNABLE_ROLES = ['manager', 'member', 'viewer']

/** The user list and the group list that hold each assignable role. */
export const ROLE_LISTS = {
	manager: { users: 'managers', groups: 'managerGroups' },
	member: { users: 'members', groups: 'memberGroups' },
	viewer: { users: 'viewers', groups: 'viewerGroups' },
}

/**
 * The list a project holds under `field`, or an empty list.
 *
 * @param {object} project The project.
 * @param {string} field The property.
 * @return {Array<string>}
 */
function listOf(project, field) {
	return Array.isArray(project?.[field]) ? project[field] : []
}

/**
 * Whether a list shares an entry with the caller's group ids.
 *
 * @param {Array<string>} list Group ids on the project.
 * @param {Array<string>} groupIds The caller's group ids.
 * @return {boolean}
 */
function anyGroup(list, groupIds) {
	return list.some((gid) => groupIds.includes(gid))
}

/**
 * The caller's Nextcloud group ids, provided through IInitialState.
 *
 * @return {Array<string>}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
 */
export function currentGroupIds() {
	try {
		const groups = loadState('planninq', 'groups', [])
		return Array.isArray(groups) ? groups.filter((gid) => typeof gid === 'string') : []
	} catch {
		return []
	}
}

/**
 * The highest role a person holds on a project.
 *
 * @param {object|null} project The project.
 * @param {string} uid The user id.
 * @param {Array<string>} groupIds The person's group ids.
 * @return {'owner'|'manager'|'member'|'viewer'|'none'}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.1
 */
export function projectRole(project, uid, groupIds = []) {
	if (!project || !uid) {
		return 'none'
	}
	const groups = Array.isArray(groupIds) ? groupIds : []
	if (project.owner === uid || anyGroup(listOf(project, 'ownerGroups'), groups)) {
		return 'owner'
	}
	for (const role of ASSIGNABLE_ROLES) {
		const lists = ROLE_LISTS[role]
		if (listOf(project, lists.users).includes(uid) || anyGroup(listOf(project, lists.groups), groups)) {
			return role
		}
	}
	return listOf(project, 'portfolioReaders').includes(uid) ? 'viewer' : 'none'
}

/**
 * Whether a role may change tasks, columns and phases.
 *
 * @param {string} role A role from projectRole.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.3
 */
export function canWrite(role) {
	return ['owner', 'manager', 'member'].includes(role)
}

/**
 * Whether a role may manage the project's members.
 *
 * @param {string} role A role from projectRole.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.3
 */
export function canManageMembers(role) {
	return role === 'owner' || role === 'manager'
}

/**
 * Whether a role may set or clear the owning group: the owner, which includes
 * everyone in the owning group, as the schema's `ownerGroups` update rule does.
 *
 * @param {string} role A role from projectRole.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
 */
export function canSetOwnerGroup(role) {
	return role === 'owner'
}

/**
 * The group that owns the project, or null.
 *
 * @param {object} project The project.
 * @return {string|null}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-5.1
 */
export function ownerGroupOf(project) {
	const gid = listOf(project, 'ownerGroups')[0]
	return typeof gid === 'string' && gid !== '' ? gid : null
}

/**
 * The PATCH body that gives one person or group a role: it names only the
 * lists that change, so nothing else on the project is rewritten.
 *
 * @param {object} project The project.
 * @param {string} id The user or group id.
 * @param {'user'|'group'} type Whether the id is a user or a group.
 * @param {'manager'|'member'|'viewer'|null} role The new role, or null to remove.
 * @return {object}
 *
 * @spec openspec/changes/projects-members-and-roles/tasks.md#task-4.4
 */
export function rolePatch(project, id, type, role) {
	if (role !== null && !ASSIGNABLE_ROLES.includes(role)) {
		throw new Error(`Unknown role: ${role}`)
	}
	const key = type === 'group' ? 'groups' : 'users'
	const patch = {}
	for (const candidate of ASSIGNABLE_ROLES) {
		const field = ROLE_LISTS[candidate][key]
		const before = listOf(project, field)
		const after = candidate === role
			? (before.includes(id) ? before : [...before, id])
			: before.filter((entry) => entry !== id)
		if (after.length !== before.length) {
			patch[field] = after
		}
	}
	return patch
}
