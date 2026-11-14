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
