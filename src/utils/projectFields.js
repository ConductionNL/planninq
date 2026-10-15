/**
 * Project custom fields (projects-grouping-hierarchy-fields, section 3): the
 * fields an admin defines, the values the Details tab saves and the filled
 * values the overview shows. The server checks every value again.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
 */

/**
 * Whether a value counts as empty.
 *
 * @param {string|number|boolean|null|undefined} value The value.
 * @return {boolean}
 */
function isEmpty(value) {
	return value === undefined || value === null || value === ''
}

/**
 * The project fields, lowest order first.
 *
 * @param {Array<object>} fields Every field definition.
 * @return {Array<object>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
 */
export function sortFields(fields) {
	return (fields || [])
		.filter((field) => field?.key && (field.appliesTo || 'project') === 'project')
		.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0))
}

/**
 * The keys of the required fields left empty.
 *
 * @param {Array<object>} fields The project fields.
 * @param {object} form The form values keyed by field key.
 * @return {Array<string>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
 */
export function missingRequired(fields, form) {
	return fields.filter((field) => field.required === true && isEmpty(form?.[field.key])).map((field) => field.key)
}

/**
 * The values to save: numbers as numbers, yes/no as booleans, empty inputs left out.
 *
 * @param {Array<object>} fields The project fields.
 * @param {object} form The form values keyed by field key.
 * @return {object}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
 */
export function customFieldValues(fields, form) {
	const values = {}
	for (const field of fields) {
		const value = form?.[field.key]
		if (field.type === 'boolean') {
			if (typeof value === 'boolean') {
				values[field.key] = value
			}
			continue
		}
		if (isEmpty(value)) {
			continue
		}
		values[field.key] = field.type === 'number' ? Number(value) : String(value)
	}
	return values
}

/**
 * The filled values of a project with their labels, in field order. A value
 * whose field was removed is not shown.
 *
 * @param {Array<object>} fields The project fields.
 * @param {object} project The project.
 * @return {Array<{key: string, label: string, type: string, value: (string|number|boolean)}>}
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
 */
export function filledFields(fields, project) {
	const values = project?.customFields || {}
	return fields
		.filter((field) => !isEmpty(values[field.key]))
		.map((field) => ({ key: field.key, label: field.label, type: field.type, value: values[field.key] }))
}
