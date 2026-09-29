/**
 * Vitest unit tests for the phase helpers (planning-phase-gate-document):
 * the phase order, a keyboard reorder that swaps two order values, the one
 * write that closes a phase, the flag for a completed phase whose concluding
 * document is gone, and the payload checked against the register.
 *
 * @spec openspec/changes/planning-phase-gate-document/tasks.md#task-2.1
 */
import Ajv from 'ajv'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	closePatch,
	missingConcludingDocument,
	phasePayload,
	refusalMessage,
	reorderPatches,
	sortPhases,
} from '../../src/utils/phaseHelpers.js'

const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))

/**
 * Validate a payload against the register's projectPhase schema, with
 * OpenRegister's nullable rule applied as in the other register specs.
 *
 * @param {object} payload The payload.
 * @return {Array|null} The errors, or null.
 */
function phaseErrors(payload) {
	const schema = register.components.schemas.projectPhase
	const properties = {}
	for (const [name, prop] of Object.entries(schema.properties)) {
		const { title, description, nullable, visible, $ref, ...rest } = prop
		if (nullable && rest.type) {
			rest.type = [rest.type, 'null']
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false, allErrors: true })
	const validate = ajv.compile({ type: 'object', required: schema.required, properties })
	return validate(payload) ? null : validate.errors
}

const phases = [
	{ id: 'c', title: 'Uitvoering', order: 3 },
	{ id: 'a', title: 'Initiatie', order: 1 },
	{ id: 'b', title: 'Definitie', order: 2 },
]

describe('sortPhases', () => {
	it('orders phases by order, then title', () => {
		expect(sortPhases(phases).map((p) => p.id)).toEqual(['a', 'b', 'c'])
	})
})

describe('reorderPatches (scenario: member reorders phases with the keyboard)', () => {
	it('reorder swaps order values', () => {
		expect(reorderPatches(phases, 'b', -1)).toEqual([{ id: 'b', order: 1 }, { id: 'a', order: 2 }])
		expect(reorderPatches(phases, 'b', 1)).toEqual([{ id: 'b', order: 3 }, { id: 'c', order: 2 }])
	})

	it('does nothing at the ends', () => {
		expect(reorderPatches(phases, 'a', -1)).toEqual([])
		expect(reorderPatches(phases, 'c', 1)).toEqual([])
	})

	it('gives distinct orders when two phases share one', () => {
		const tied = [{ id: 'x', title: 'A', order: 0 }, { id: 'y', title: 'B', order: 0 }]
		expect(reorderPatches(tied, 'y', -1)).toEqual([{ id: 'y', order: 0 }, { id: 'x', order: 1 }])
	})
})

describe('closePatch (scenario: closing with a concluding document completes the phase)', () => {
	it('close sends document and status in one write', () => {
		expect(closePatch(4711)).toEqual({ concludingDocument: '4711', status: 'completed' })
		expect(phaseErrors({ title: 'Initiatie', project: 'p1', ...closePatch(4711) })).toBeNull()
	})
})

describe('refusalMessage (scenario: closing without a document explains what is needed)', () => {
	it('recognises the guard refusal and the refused transition', () => {
		expect(refusalMessage(403, { errors: { code: 'lifecycle-guard-denied' } })).toBe('guard')
		expect(refusalMessage(422, { errors: { code: 'lifecycle-invalid-transition' } })).toBe('transition')
		expect(refusalMessage(500, {})).toBe('other')
	})
})

describe('missingConcludingDocument', () => {
	it('completed phase without its file is flagged', () => {
		const files = [{ id: 4711, title: 'Fase-afsluiting.pdf' }]
		expect(missingConcludingDocument({ status: 'completed', concludingDocument: '4711' }, files)).toBe(false)
		expect(missingConcludingDocument({ status: 'completed', concludingDocument: '999' }, files)).toBe(true)
		expect(missingConcludingDocument({ status: 'completed' }, files)).toBe(true)
		expect(missingConcludingDocument({ status: 'in_progress' }, [])).toBe(false)
	})
})

describe('phasePayload (scenario: member adds a phase)', () => {
	it('writes a phase the register accepts, last in order', () => {
		const payload = phasePayload({ title: ' Definitie ', startDate: '2026-03-01', endDate: '', budgetHours: '40' }, 'p1', phases)
		expect(payload).toEqual({ title: 'Definitie', project: 'p1', startDate: '2026-03-01', budgetHours: 40, order: 4 })
		expect(phaseErrors(payload)).toBeNull()
	})
})
