/**
 * Shared workflows (projects-templates-shared-workflow): the columns and
 * estimate scale an admin edits, the estimate a task on a workflow shows, and
 * where a project's tasks land when it moves onto a workflow.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
 */

/** The estimate scales a workflow can set. */
export const ESTIMATE_SCALES = ['none', 'hours', 'storyPoints', 'tshirt']

/**
 * A column key from a title, unique among the keys already taken: lower-case
 * letters, digits and dashes, `column` for a title without any.
 *
 * @param {string} title The column's title.
 * @param {Array<string>} taken The keys already in use.
 * @return {string}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
 */
export function columnKey(title, taken) {
	const base = String(title || '').normalize('NFKD').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'column'
	let key = base
	for (let n = 2; taken.includes(key); n++) {
		key = `${base}-${n}`
	}
	return key
}

/**
 * The allowed estimate values typed as a comma-separated list: words for
 * t-shirt sizes, positive numbers otherwise, each once; none without a scale.
 *
 * @param {string} text The typed values.
 * @param {string} scale One of ESTIMATE_SCALES.
 * @return {Array<number|string>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
 */
export function parseEstimateValues(text, scale) {
	if (scale === 'none') {
		return []
	}
	const items = String(text || '').split(',').map((item) => item.trim()).filter(Boolean)
	const values = scale === 'tshirt' ? items : items.map(Number).filter((value) => Number.isFinite(value) && value > 0)
	return [...new Set(values)]
}

/**
 * The workflow to store from the edit form: the trimmed title and
 * description, the named columns in order, each with a stable key (a new
 * column gets one from its title), and the estimate scale with its values.
 *
 * @param {{title: string, description?: string, columns: Array<object>, estimateScale?: string, estimateValues?: string}} form The form.
 * @return {object}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.3
 */
export function workflowBody(form) {
	const scale = ESTIMATE_SCALES.includes(form.estimateScale) ? form.estimateScale : 'none'
	const taken = form.columns.map((column) => column.key).filter(Boolean)
	const columns = form.columns
		.filter((column) => String(column.title || '').trim())
		.map((column) => storedColumn(column, taken))
	const body = { title: String(form.title || '').trim(), columns, estimateScale: scale, estimateValues: parseEstimateValues(form.estimateValues, scale) }
	const description = String(form.description || '').trim()
	return description ? { ...body, description } : body
}

/**
 * One column as stored: its key (made from the title, and taken, when new),
 * trimmed title and type.
 *
 * @param {{key?: string, title: string, type?: string}} column The edited column.
 * @param {Array<string>} taken The keys in use; a new key is added to it.
 * @return {{key: string, title: string, type: string}}
 */
function storedColumn(column, taken) {
	const title = String(column.title).trim()
	const key = column.key || columnKey(title, taken)
	taken.push(key)
	return { key, title, type: column.type === 'done' ? 'done' : 'active' }
}

/** The task field each estimate scale writes. */
const ESTIMATE_FIELDS = { storyPoints: 'storyPoints', hours: 'estimatedDuration', tshirt: 'estimateSize' }

/**
 * The estimate scale a workflow sets that a task can store, or null: story
 * points in `storyPoints`, hours in `estimatedDuration` (minutes), t-shirt
 * sizes in `estimateSize`.
 *
 * @param {object|null} workflow The project's workflow.
 * @return {string|null}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
 */
export function storedScale(workflow) {
	const scale = workflow?.estimateScale
	return Object.hasOwn(ESTIMATE_FIELDS, scale) ? scale : null
}

/**
 * A task's estimate on its workflow's scale: "5 pt", "3 h" or the size ("M"),
 * or '' when the task has none or the workflow sets no stored scale.
 *
 * @param {object} task The task.
 * @param {object|null} workflow The project's workflow.
 * @return {string}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
 */
export function workflowEstimate(task, workflow) {
	const scale = storedScale(workflow)
	if (scale === 'tshirt') {
		return typeof task?.estimateSize === 'string' ? task.estimateSize.trim() : ''
	}
	const value = Number(task?.[ESTIMATE_FIELDS[scale]])
	if (!value) {
		return ''
	}
	return scale === 'storyPoints' ? `${value} pt` : `${Math.round((value / 60) * 100) / 100} h`
}

/**
 * The estimates a task on the workflow may take, as select options.
 *
 * @param {object|null} workflow The project's workflow.
 * @return {Array<{id: number|string, label: string}>}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
 */
export function estimateChoices(workflow) {
	const scale = storedScale(workflow)
	if (scale === 'tshirt') {
		return parseEstimateValues((workflow?.estimateValues || []).join(','), 'tshirt').map((value) => ({ id: value, label: value }))
	}
	const unit = scale === 'storyPoints' ? 'pt' : 'h'
	return parseEstimateValues((workflow?.estimateValues || []).join(','), 'storyPoints').map((value) => ({ id: value, label: `${value} ${unit}` }))
}

/**
 * The task PATCH that stores a picked estimate, or clears it with null.
 *
 * @param {object|null} workflow The project's workflow.
 * @param {number|string|null} value The picked value.
 * @return {object}
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.4
 */
export function estimatePatch(workflow, value) {
	const scale = storedScale(workflow)
	const stored = scale === 'hours' && value !== null ? Math.round(value * 60) : value
	return { [ESTIMATE_FIELDS[scale]]: stored }
}

/**
 * What moving a project onto a workflow does to its tasks: a task keeps its
 * place when its column has the same title as a workflow column, and the rest
 * go to the workflow's first column. The same rule the server's sync applies.
 *
 * @param {Array<{id: string, title: string, workflowKey?: string}>} columns The project's columns.
 * @param {Array<{column?: string}>} tasks The project's tasks.
 * @param {object} workflow The workflow.
 * @return {{moving: number, target: string}} How many tasks move, and the column they move to.
 *
 * @spec openspec/changes/projects-templates-shared-workflow/tasks.md#task-2.5
 */
export function workflowMapping(columns, tasks, workflow) {
	const wanted = (workflow?.columns || []).filter((column) => column?.key && String(column.title || '').trim())
	if (!wanted.length) {
		return { moving: 0, target: '' }
	}
	const titles = new Set(wanted.map((column) => titleKey(column.title)))
	const keys = new Set(wanted.map((column) => column.key))
	const leaving = new Set(columns.filter((column) => !keys.has(column.workflowKey) && !titles.has(titleKey(column.title))).map((column) => column.id))
	return { moving: tasks.filter((task) => leaving.has(task.column)).length, target: String(wanted[0].title).trim() }
}

/**
 * A column title as the mapping compares it: trimmed and lower-case.
 *
 * @param {string} title The title.
 * @return {string}
 */
function titleKey(title) {
	return String(title || '').trim().toLowerCase()
}
