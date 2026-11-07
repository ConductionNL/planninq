/**
 * The project lifecycle (projects-lifecycle-policy): archive and restore go
 * through OpenRegister's transition endpoint, so the schema's lifecycle
 * block decides who may move a project, and the buttons follow the actions
 * OpenRegister offers for it.
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.3
 */

/**
 * The transitions each status allows, as declared on the project schema
 * (lib/Settings/planninq_register.json): used only when the list of
 * available actions could not be read.
 */
const FROM = {
	archive: ['active', 'completed'],
	restore: ['archived', 'completed', 'cancelled'],
}

/**
 * The request that runs a transition on a project.
 *
 * @param {string} id The project id.
 * @param {string} action The transition name.
 * @return {{path: string, body: {action: string}}}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.3
 */
export function transitionRequest(id, action) {
	return { path: `/apps/openregister/api/objects/${encodeURIComponent(id)}/transition`, body: { action } }
}

/**
 * The action names in an available-actions answer, blocked ones left out; null when unreadable.
 *
 * @param {object|null} answer The answer of GET /api/objects/{id}/available-actions.
 * @return {string[]|null}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.4
 */
export function actionNames(answer) {
	if (!answer || !Array.isArray(answer.actions)) {
		return null
	}
	return answer.actions.filter((entry) => entry && !entry.blocked).map((entry) => entry.action)
}

/**
 * Whether a project shows "Archive project" and "Restore project".
 *
 * @param {object} project The project.
 * @param {string[]|null} actions The actions OpenRegister offers, or null when unknown.
 * @return {{archive: boolean, restore: boolean}}
 *
 * @spec openspec/changes/projects-lifecycle-policy/tasks.md#task-1.4
 */
export function lifecycleButtons(project, actions) {
	if (Array.isArray(actions)) {
		return { archive: actions.includes('archive'), restore: actions.includes('restore') }
	}
	const status = project?.status || 'active'
	return { archive: FROM.archive.includes(status), restore: FROM.restore.includes(status) }
}
