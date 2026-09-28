/**
 * Vitest unit tests for the risk register helpers: the scale, the bands, the
 * heat map cells, the list order and the payload the Risks tab writes
 * (projects-overview-logs-risks).
 *
 * @spec openspec/changes/projects-overview-logs-risks/tasks.md#task-3.2
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import {
	DEFAULT_RISK_SCALE,
	defaultThresholds,
	heatMapRows,
	parseRiskScale,
	riskBand,
	riskPayload,
	sortRisks,
	topOpenRisks,
} from '../../src/utils/riskHelpers.js'

const project = '11111111-1111-4111-8111-111111111111'
const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))
const threeLevels = { levels: 3, likelihood: ['Low', 'Medium', 'High'], impact: ['Low', 'Medium', 'High'], thresholds: { medium: 3, high: 6 } }

describe('parseRiskScale', () => {
	it('reads the stored JSON and falls back to the five-level default', () => {
		expect(parseRiskScale(JSON.stringify(threeLevels))).toEqual(threeLevels)
		expect(parseRiskScale('')).toEqual(DEFAULT_RISK_SCALE)
		expect(parseRiskScale('{"levels": 9}')).toEqual(DEFAULT_RISK_SCALE)
		expect(DEFAULT_RISK_SCALE.levels).toBe(5)
	})

	it('the default matches the server default', () => {
		const php = readFileSync(new URL('../../lib/Service/RiskScaleService.php', import.meta.url), 'utf8')
		const json = php.match(/DEFAULT_SCALE = ((?:'[^']*'\s*\.?\s*)+);/)[1].split(/'\s*\.\s*'/).join('').replace(/^'|'$/g, '')
		expect(JSON.parse(json)).toEqual(DEFAULT_RISK_SCALE)
	})
})

describe('riskBand', () => {
	it('splits scores into low, medium and high at the two thresholds', () => {
		expect(riskBand(4, DEFAULT_RISK_SCALE)).toBe('low')
		expect(riskBand(5, DEFAULT_RISK_SCALE)).toBe('medium')
		expect(riskBand(12, DEFAULT_RISK_SCALE)).toBe('high')
	})

	it('offers sensible thresholds per level count', () => {
		expect(defaultThresholds(3)).toEqual({ medium: 3, high: 6 })
		expect(defaultThresholds(4)).toEqual({ medium: 4, high: 9 })
		expect(defaultThresholds(5)).toEqual({ medium: 5, high: 12 })
	})
})

describe('heatMapRows', () => {
	const risks = [
		{ id: 'a', likelihood: 4, impact: 3, score: 12, status: 'open' },
		{ id: 'b', likelihood: 1, impact: 1, score: 1, status: 'open' },
		{ id: 'c', likelihood: 4, impact: 3, score: 12, status: 'closed' },
	]

	it('counts the risks per likelihood and impact cell, with the band as text (scenario: adding a risk)', () => {
		const rows = heatMapRows(risks, DEFAULT_RISK_SCALE)
		expect(rows).toHaveLength(5)
		expect(rows[0].impact).toBe(5)
		const cell = rows.find((row) => row.impact === 3).cells.find((c) => c.likelihood === 4)
		expect(cell).toMatchObject({ count: 2, score: 12, band: 'high' })
		expect(rows.find((row) => row.impact === 1).cells[0]).toMatchObject({ count: 1, band: 'low' })
	})

	it('a three-level scale gives a three by three map (scenario: switching to a three-level scale)', () => {
		const rows = heatMapRows([], threeLevels)
		expect(rows).toHaveLength(3)
		expect(rows.every((row) => row.cells.length === 3)).toBe(true)
		expect(rows[0].label).toBe('High')
		expect(rows[0].cells.map((cell) => cell.label)).toEqual(['Low', 'Medium', 'High'])
	})
})

describe('risk lists', () => {
	const risks = [
		{ id: 'a', title: 'A', score: 4, status: 'open' },
		{ id: 'b', title: 'B', score: 20, status: 'closed' },
		{ id: 'c', title: 'C', score: 12, status: 'mitigating' },
		{ id: 'd', title: 'D', score: 9, status: 'open' },
		{ id: 'e', title: 'E', score: 1, status: 'open' },
	]

	it('sorts by score, highest first', () => {
		expect(sortRisks(risks).map((risk) => risk.id)).toEqual(['b', 'c', 'd', 'a', 'e'])
	})

	it('the overview shows the three highest open risks', () => {
		expect(topOpenRisks(risks).map((risk) => risk.id)).toEqual(['c', 'd', 'a'])
	})
})

describe('riskPayload', () => {
	const schema = register.components.schemas.risk
	const properties = {}
	for (const [name, property] of Object.entries(schema.properties)) {
		const { $ref, visible, ...rest } = property
		for (const key of Object.keys(rest)) {
			if (key.startsWith('x-')) {
				delete rest[key]
			}
		}
		properties[name] = rest
	}
	const ajv = new Ajv({ strict: false })
	addFormats(ajv)
	const validate = ajv.compile({ type: 'object', required: schema.required, properties })

	it('builds a risk the real schema accepts, without a score of its own (scenario: adding a risk)', () => {
		const payload = riskPayload({ title: ' Supplier delivers late ', likelihood: 4, impact: 3, response: 'reduce', countermeasures: 'Order early.', owner: 'ada' }, project)
		expect(payload).toMatchObject({ title: 'Supplier delivers late', likelihood: 4, impact: 3, response: 'reduce', status: 'open' })
		expect(payload).not.toHaveProperty('score')
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('a likelihood above the schema maximum is refused', () => {
		expect(validate(riskPayload({ title: 'X', likelihood: 6, impact: 1 }, project))).toBe(false)
	})
})
