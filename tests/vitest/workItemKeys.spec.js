/**
 * Vitest unit tests for readable keys (tasks-readable-keys): the key the New
 * project dialog suggests, the format check it shares with the server, when
 * the sidebar may still change a key, the server refusals it shows, and the
 * label a task shows. Keys are validated against the real register schema.
 *
 * @spec openspec/changes/tasks-readable-keys/tasks.md#task-1.1
 */
import Ajv from 'ajv'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { isValidProjectKey, keyEditable, keyRefusal, normaliseProjectKey, suggestProjectKey, taskHeading } from '../../src/utils/workItemKeys.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))

describe('suggestProjectKey (scenario: create a project with a key)', () => {
	it('takes the initials of a title of several words', () => {
		expect(suggestProjectKey('Vergunningen Centrum')).toBe('VC')
		expect(suggestProjectKey('renovatie van het stadhuis 2027')).toBe('RVHS2')
	})

	it('takes the first four letters of a single word', () => {
		expect(suggestProjectKey('Vergunningen')).toBe('VERG')
		expect(suggestProjectKey('Één')).toBe('EEN')
	})

	it('suggests nothing it could not store', () => {
		expect(suggestProjectKey('')).toBe('')
		expect(suggestProjectKey('2027')).toBe('')
		expect(suggestProjectKey('A')).toBe('')
		for (const title of ['Vergunningen Centrum', 'renovatie van het stadhuis 2027', 'Vergunningen', 'Één', 'a b c d e f g h i j k l']) {
			expect(isValidProjectKey(suggestProjectKey(title))).toBe(true)
		}
	})
})

describe('isValidProjectKey', () => {
	it('wants 2 to 10 letters and digits starting with a letter', () => {
		expect(['VERG', 'V2', 'ABCDEFGHIJ'].every(isValidProjectKey)).toBe(true)
		expect(['', 'V', '1AB', 'VE-RG', 'ABCDEFGHIJK', 'verg'].some(isValidProjectKey)).toBe(false)
		expect(isValidProjectKey(normaliseProjectKey(' verg '))).toBe(true)
	})

	it('accepts what the project schema accepts', () => {
		const ajv = new Ajv({ strict: false })
		const { key, nextTaskNumber } = register.components.schemas.project.properties
		const validate = ajv.compile({ type: 'object', properties: { key, nextTaskNumber } })
		expect(validate({ key: normaliseProjectKey('verg'), nextTaskNumber: 43 })).toBe(true)
		expect(validate({ key: 'VERG', nextTaskNumber: 0 })).toBe(false)
	})
})

describe('keyEditable (the sidebar)', () => {
	it('lets the key change until a task carries it', () => {
		expect(keyEditable({})).toBe(true)
		expect(keyEditable({ key: 'VERG' })).toBe(true)
		expect(keyEditable({ key: 'VERG', nextTaskNumber: 1 })).toBe(true)
		expect(keyEditable({ key: 'VERG', nextTaskNumber: 2 })).toBe(false)
	})
})

describe('keyRefusal', () => {
	it('reads the server code or message', () => {
		expect(keyRefusal('planninq-project-key-used')).toBe('used')
		expect(keyRefusal('This key is already used by another project.')).toBe('used')
		expect(keyRefusal('planninq-project-key-format')).toBe('format')
		expect(keyRefusal('planninq-project-key-fixed')).toBe('fixed')
		expect(keyRefusal('something else')).toBe('')
	})
})

describe('taskHeading (scenario: numbered on create)', () => {
	it('puts the key before the title', () => {
		expect(taskHeading({ key: 'VERG-42', title: 'Check the zoning plan' })).toBe('VERG-42 Check the zoning plan')
		expect(taskHeading({ title: 'Check the zoning plan' })).toBe('Check the zoning plan')
	})
})
