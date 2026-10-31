/**
 * Subprojects (projects-grouping-hierarchy-fields, section 2): a programme
 * holds projects, a project holds subprojects, three levels deep at most.
 * The server refuses a cycle and a fourth level; these helpers show the tree
 * and keep the parent picker from offering a cycle in the first place.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.2
 */

/**
 * The parent of a project, as a uuid, or '' when it has none.
 *
 * @param {object} project The project.
 * @return {string}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
 */
export function parentIdOf(project) {
	const parent = project?.parent
	if (parent && typeof parent === 'object') {
		return String(parent.id ?? parent['@self']?.id ?? '')
	}
	return parent ? String(parent) : ''
}

/**
 * The direct subprojects of a project, in list order.
 *
 * @param {Array<object>} projects The projects.
 * @param {string} id The parent project.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.2
 */
export function subprojectsOf(projects, id) {
	return (projects || []).filter((project) => id && parentIdOf(project) === String(id))
}

/**
 * Every project below a project, parents before their children.
 *
 * @param {Array<object>} projects The projects.
 * @param {string} id The top project.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.2
 */
export function descendantsOf(projects, id) {
	const out = []
	const seen = new Set([String(id)])
	const queue = [String(id)]
	while (queue.length) {
		for (const child of subprojectsOf(projects, queue.shift())) {
			if (!seen.has(String(child.id))) {
				seen.add(String(child.id))
				out.push(child)
				queue.push(String(child.id))
			}
		}
	}
	return out
}

/**
 * The projects a project may sit under: every other project but its own descendants.
 *
 * @param {Array<object>} projects The projects the user can read.
 * @param {object} project The project whose parent is picked.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
 */
export function parentOptions(projects, project) {
	const excluded = new Set([String(project?.id), ...descendantsOf(projects, project?.id).map((p) => String(p.id))])
	return (projects || []).filter((candidate) => !excluded.has(String(candidate.id)))
}

/**
 * A programme's progress: its own tasks plus those of its subprojects.
 *
 * @param {{done: number, total: number}} own The project's own progress.
 * @param {Array<{done: number, total: number}>} children The subprojects' progress.
 * @return {{done: number, total: number}}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.2
 */
export function rollupProgress(own, children = []) {
	return (children || []).reduce(
		(sum, child) => ({ done: sum.done + (child?.done || 0), total: sum.total + (child?.total || 0) }),
		{ done: own?.done || 0, total: own?.total || 0 },
	)
}

/**
 * The rows of an indented project list: each project after its parent, one
 * level deeper, and hidden while an ancestor is folded. A project whose
 * parent is not in the list is a top row.
 *
 * @param {Array<object>} projects The projects, in list order.
 * @param {Set<string>} folded The ids of the folded parents.
 * @return {Array<{project: object, depth: number, hasChildren: boolean, expanded: boolean}>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.3
 */
export function treeRows(projects, folded = new Set()) {
	const list = projects || []
	const ids = new Set(list.map((project) => String(project.id)))
	const rows = []
	const placed = new Set()
	const visit = (project, depth) => {
		const id = String(project.id)
		if (placed.has(id)) {
			return
		}
		placed.add(id)
		const children = subprojectsOf(list, id)
		const expanded = !folded.has(id)
		rows.push({ project, depth, hasChildren: children.length > 0, expanded })
		if (expanded) {
			children.forEach((child) => visit(child, depth + 1))
		} else {
			descendantsOf(list, id).forEach((hidden) => placed.add(String(hidden.id)))
		}
	}
	list.filter((project) => !ids.has(parentIdOf(project))).forEach((project) => visit(project, 0))
	return rows
}

/**
 * Which parent refusal a failed save carries: 'cycle', 'depth' or ''.
 *
 * @param {string} error The store's error text.
 * @return {string}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-2.1
 */
export function parentRefusal(error) {
	const text = String(error || '')
	if (text.includes('planninq-project-cycle') || text.includes('own subprojects')) {
		return 'cycle'
	}
	if (text.includes('planninq-project-too-deep') || text.includes('three levels')) {
		return 'depth'
	}
	return ''
}
