/**
 * Vitest tests for the timetable wish editor helpers (timetabling-generator, section 3).
 *
 * @spec openspec/changes/archive/2026-09-30-timetabling-generator/tasks.md#task-3.1
 */
import Ajv from 'ajv'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	DEFAULT_GRID,
	gridRows,
	parsePeriodKey,
	periodKey,
	readGrid,
	sortPeriodKeys,
	togglePeriod,
	wishForm,
	wishPayload,
	wishProblems,
} from '../../src/utils/timetableWishes.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
const fragment = register.components.schemas.timetableWish

/**
 * OpenRegister's nullable becomes a JSON Schema null type, so ajv reads the fragment as OR does.
 *
 * @param {object} property A property of the fragment.
 * @return {object} The property ajv validates with.
 */
function asJsonSchema(property) {
	const copy = { ...property }
	delete copy.title
	if (copy.nullable === true) {
		delete copy.nullable
		copy.type = [copy.type, 'null']
	}
	return copy
}

const validate = new Ajv({ strict: false }).compile({
	type: 'object',
	required: fragment.required,
	additionalProperties: false,
	properties: Object.fromEntries(Object.entries(fragment.properties).map(([key, value]) => [key, asJsonSchema(value)])),
})

describe('period keys', () => {
	it('round-trips a key through its parts', () => {
		expect(periodKey('wed', 5)).toBe('wed-5')
		expect(parsePeriodKey('wed-5')).toEqual({ day: 'wed', number: 5 })
		const parts = parsePeriodKey(periodKey('fri', 12))
		expect(periodKey(parts.day, parts.number)).toBe('fri-12')
	})

	it('refuses what the schema pattern refuses', () => {
		expect(parsePeriodKey('wed-0')).toBeNull()
		expect(parsePeriodKey('wednesday-1')).toBeNull()
		expect(parsePeriodKey('')).toBeNull()
		expect(parsePeriodKey(null)).toBeNull()
	})

	it('sorts by week day, then period, and drops what is not a key', () => {
		expect(sortPeriodKeys(['fri-1', 'mon-10', 'mon-2', 'nope'])).toEqual(['mon-2', 'mon-10', 'fri-1'])
	})

	it('switches a period on and off', () => {
		expect(togglePeriod(['tue-3'], 'mon-1')).toEqual(['mon-1', 'tue-3'])
		expect(togglePeriod(['mon-1', 'tue-3'], 'mon-1')).toEqual(['tue-3'])
	})
})

describe('the week grid', () => {
	it('reads the stored setting and falls back to the default grid', () => {
		expect(readGrid('{"days":["mon","tue"],"periods":[{"start":"09:00","end":"10:00"}]}'))
			.toEqual({ days: ['mon', 'tue'], periods: [{ start: '09:00', end: '10:00' }] })
		expect(readGrid('not json')).toBe(DEFAULT_GRID)
		expect(readGrid(null)).toBe(DEFAULT_GRID)
		expect(readGrid({ days: [], periods: [] })).toBe(DEFAULT_GRID)
	})

	it('lays out one row per period with a cell per day, marking the chosen ones', () => {
		const rows = gridRows(readGrid({ days: ['mon', 'wed'], periods: [{ start: '08:30', end: '09:20' }, { start: '09:20', end: '10:10' }] }), ['wed-2'])
		expect(rows).toHaveLength(2)
		expect(rows[1]).toMatchObject({ number: 2, start: '09:20', end: '10:10' })
		expect(rows[1].cells.map((cell) => [cell.key, cell.selected])).toEqual([['mon-2', false], ['wed-2', true]])
	})
})

describe('the wish that is saved', () => {
	it('keeps a weight only for a soft wish (scenario: a timetabler marks a wish as hard)', () => {
		const soft = wishPayload({ ...wishForm(null), reference: 'noor', periods: ['wed-5'], strength: 'soft', weight: 2 })
		expect(soft.weight).toBe(2)
		const hard = wishPayload({ ...wishForm(soft), strength: 'hard' })
		expect(hard).toMatchObject({ strength: 'hard', weight: null, periods: ['wed-5'] })
		expect(validate(soft)).toBe(true)
		expect(validate(hard)).toBe(true)
	})

	it('keeps periods only for a kind that names periods, and a limit only for at most a number a day', () => {
		const limit = wishPayload({ ...wishForm(null), appliesTo: 'group', reference: '3A', kind: 'maxPerDay', periods: ['mon-1'], limit: '6' })
		expect(limit).toMatchObject({ periods: [], limit: 6 })
		expect(validate(limit)).toBe(true)
		const avoid = wishPayload({ ...wishForm(null), reference: 'B12', appliesTo: 'room', kind: 'avoid', periods: ['tue-8', 'mon-8'], limit: 4, note: ' Cleaning ' })
		expect(avoid).toMatchObject({ periods: ['mon-8', 'tue-8'], limit: null, note: 'Cleaning' })
		expect(validate(avoid)).toBe(true)
	})

	it('clamps the weight to 1 to 3, as the schema allows', () => {
		expect(wishPayload({ ...wishForm(null), reference: 'x', periods: ['mon-1'], weight: 9 }).weight).toBe(3)
		expect(wishPayload({ ...wishForm(null), reference: 'x', periods: ['mon-1'], weight: 0 }).weight).toBe(1)
	})

	it('names what stops the form from saving', () => {
		expect(wishProblems(wishForm(null)).map((problem) => problem.field)).toEqual(['reference', 'periods'])
		expect(wishProblems({ ...wishForm(null), reference: 'noor', kind: 'maxPerDay', limit: 0 }).map((problem) => problem.field)).toEqual(['limit'])
		expect(wishProblems({ ...wishForm(null), reference: 'noor', kind: 'noGaps' })).toEqual([])
	})

	it('opens a stored wish as it was saved', () => {
		const stored = { appliesTo: 'activity', reference: '3A:Maths', kind: 'sameRoom', periods: [], limit: null, strength: 'hard', weight: null }
		expect(wishPayload(wishForm(stored))).toEqual(stored)
	})
})
