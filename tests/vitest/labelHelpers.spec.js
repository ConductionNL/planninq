/**
 * Vitest unit tests for the label-management pure helpers.
 *
 * Covers the hex-colour validation (matching the schema's 6-digit pattern),
 * the required-title check, the combined draft validation (empty title, bad
 * hex), and payload normalisation (trim + colour default).
 *
 * @spec openspec/changes/label-management-admin/specs/admin-user-settings/spec.md
 */
import { describe, it, expect } from 'vitest'
import {
	DEFAULT_LABEL_COLOR,
	isValidHexColor,
	isValidLabelTitle,
	validateLabelDraft,
	normaliseLabelPayload,
} from '../../src/utils/labelHelpers.js'

describe('isValidHexColor', () => {
	it('accepts a 6-digit hex colour', () => {
		expect(isValidHexColor('#4376FC')).toBe(true)
		expect(isValidHexColor('#abcdef')).toBe(true)
	})

	it('rejects non-6-digit / malformed values', () => {
		expect(isValidHexColor('#FFF')).toBe(false)
		expect(isValidHexColor('4376FC')).toBe(false)
		expect(isValidHexColor('#GGGGGG')).toBe(false)
		expect(isValidHexColor('')).toBe(false)
		expect(isValidHexColor(null)).toBe(false)
	})
})

describe('isValidLabelTitle', () => {
	it('requires a non-empty trimmed title', () => {
		expect(isValidLabelTitle('Bug')).toBe(true)
		expect(isValidLabelTitle('   ')).toBe(false)
		expect(isValidLabelTitle('')).toBe(false)
		expect(isValidLabelTitle(undefined)).toBe(false)
	})
})

describe('validateLabelDraft', () => {
	it('returns no errors for a valid draft', () => {
		expect(validateLabelDraft({ title: 'Bug', color: '#E74C3C' })).toEqual({})
	})

	it('flags an empty title', () => {
		const errors = validateLabelDraft({ title: '', color: '#E74C3C' })
		expect(errors.title).toBeTruthy()
		expect(errors.color).toBeUndefined()
	})

	it('flags an invalid hex colour', () => {
		const errors = validateLabelDraft({ title: 'Bug', color: 'red' })
		expect(errors.color).toBeTruthy()
		expect(errors.title).toBeUndefined()
	})

	it('flags both when both are invalid', () => {
		const errors = validateLabelDraft({ title: '', color: 'nope' })
		expect(errors.title).toBeTruthy()
		expect(errors.color).toBeTruthy()
	})
})

describe('normaliseLabelPayload', () => {
	it('trims title and description and keeps the colour', () => {
		expect(normaliseLabelPayload({ title: '  Bug  ', color: '#E74C3C', description: '  x ' }))
			.toEqual({ title: 'Bug', color: '#E74C3C', description: 'x' })
	})

	it('defaults a blank colour to the schema default', () => {
		expect(normaliseLabelPayload({ title: 'Bug' }).color).toBe(DEFAULT_LABEL_COLOR)
	})

	it('handles a null draft without throwing', () => {
		expect(normaliseLabelPayload(null)).toEqual({ title: '', color: DEFAULT_LABEL_COLOR, description: '' })
	})
})
