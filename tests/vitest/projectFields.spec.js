/**
 * Vitest unit tests for project custom fields (projects-grouping-hierarchy-fields,
 * section 3): the order of the fields, the required check next to the field,
 * the values the Details tab saves, validated against the real project schema,
 * and the filled values the overview shows.
 *
 * @spec openspec/changes/projects-grouping-hierarchy-fields/tasks.md#task-3.3
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { customFieldValues, filledFields, missingRequired, sortFields } from '../../src/utils/projectFields.js'

const fields = [
	{ key: 'contractwaarde', label: 'Contractwaarde', type: 'number', order: 2 },
	{ key: 'beleidsveld', label: 'Beleidsveld', type: 'choice', options: ['Wonen', 'Mobiliteit', 'Economie'], required: true, order: 1 },
	{ key: 'subsidie', label: 'Subsidie', type: 'boolean', order: 3 },
	{ key: 'oud', label: 'Oud', type: 'text', order: 4, appliesTo: 'task' },
]

describe('sortFields', () => {
	it('keeps the project fields, lowest order first', () => {
		expect(sortFields(fields).map((field) => field.key)).toEqual(['beleidsveld', 'contractwaarde', 'subsidie'])
	})
})

describe('missingRequired (scenario: adding a choice field)', () => {
	it('names an empty required field', () => {
		expect(missingRequired(sortFields(fields), {})).toEqual(['beleidsveld'])
		expect(missingRequired(sortFields(fields), { beleidsveld: 'Wonen' })).toEqual([])
	})
})

describe('customFieldValues', () => {
	it('turns the form into values: numbers as numbers, empty inputs left out', () => {
		const values = customFieldValues(sortFields(fields), { beleidsveld: 'Wonen', contractwaarde: '125000.5', subsidie: false })
		expect(values).toEqual({ beleidsveld: 'Wonen', contractwaarde: 125000.5, subsidie: false })
		expect(customFieldValues(sortFields(fields), { beleidsveld: '', contractwaarde: '' })).toEqual({})
	})

	it('saves values the project schema accepts', () => {
		const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
		const property = register.components.schemas.project.properties.customFields
		const ajv = new Ajv({ strict: false })
		addFormats(ajv)
		const validate = ajv.compile({ type: 'object', properties: { customFields: { type: property.type } } })
		const customFields = customFieldValues(sortFields(fields), { beleidsveld: 'Wonen', contractwaarde: '3' })
		expect(validate({ customFields }), JSON.stringify(validate.errors)).toBe(true)
		expect(validate({ customFields: 'Wonen' })).toBe(false)
	})
})

describe('filledFields (the overview)', () => {
	it('lists the filled values with their labels, and skips a value whose field was removed', () => {
		const project = { customFields: { beleidsveld: 'Wonen', subsidie: true, verwijderd: 'x', contractwaarde: '' } }
		expect(filledFields(sortFields(fields), project)).toEqual([
			{ key: 'beleidsveld', label: 'Beleidsveld', type: 'choice', value: 'Wonen' },
			{ key: 'subsidie', label: 'Subsidie', type: 'boolean', value: true },
		])
	})
})
