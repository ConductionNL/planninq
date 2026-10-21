/**
 * The project creation policy (projects-lifecycle-policy, section 2): all
 * signed-in users, administrators only, or members of chosen groups. The
 * server answers `canCreateProject` for the current user; the admin page
 * stores the chosen groups as a JSON list of group ids.
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.2
 */

/**
 * Whether the current user may create a project.
 *
 * @param {object} settings The settings payload.
 * @param {boolean} isAdmin Whether the user is an admin.
 * @return {boolean}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.2
 */
export function canCreateFrom(settings, isAdmin) {
	if (typeof settings?.canCreateProject === 'boolean') {
		return settings.canCreateProject
	}
	const policy = settings?.allow_project_creation || 'all'
	if (policy === 'admins' || policy === 'groups') {
		return !!isAdmin
	}
	return true
}

/**
 * The group ids in the stored setting.
 *
 * @param {string} raw The stored JSON list.
 * @return {string[]}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.2
 */
export function creationGroupIds(raw) {
	try {
		const list = JSON.parse(raw || '[]')
		return Array.isArray(list) ? list.filter((id) => typeof id === 'string' && id !== '') : []
	} catch {
		return []
	}
}

/**
 * The setting value for the picked groups.
 *
 * @param {Array<object|string>|null} picked The picked options or ids.
 * @return {string}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-2.2
 */
export function creationGroupsSetting(picked) {
	return JSON.stringify((picked || []).map((group) => (typeof group === 'string' ? group : group.id)))
}
