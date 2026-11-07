/**
 * Vitest unit tests for the status report helpers: the suggestions for money,
 * time and risk, the worst-status rule, the report order and the payload the
 * Status tab writes (portfolio-status-overview).
 *
 * @spec openspec/changes/portfolio-status-overview/tasks.md#task-1.3
 */
import Ajv from 'ajv'
import addFormats from 'ajv-formats'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { DEFAULT_RISK_SCALE } from '../../src/utils/riskHelpers.js'
import {
	ASPECTS,
	MONEY_AT_RISK_RATIO,
	reportPayload,
	sortReports,
	suggestMoney,
	suggestRisk,
	suggestTime,
	worstStatus,
} from '../../src/utils/statusReports.js'

const project = '11111111-1111-4111-8111-111111111111'
const today = '2026-09-28'
const register = JSON.parse(readFileSync(new URL('../../lib/Settings/planninq_register.json', import.meta.url), 'utf8'))

describe('suggestTime', () => {
	it('suggests at risk with the count of late open tasks (scenario: a time suggestion from late tasks)', () => {
		const tasks = [
			{ status: 'todo', dueDate: '2026-09-01' },
			{ status: 'in_progress', dueDate: '2026-09-27' },
			{ status: 'review', dueDate: '2026-09-10T12:00:00Z' },
			{ status: 'done', dueDate: '2026-09-01' },
			{ status: 'cancelled', dueDate: '2026-09-01' },
			{ status: 'todo', dueDate: '2026-09-28' },
			{ status: 'todo' },
		]
		expect(suggestTime(tasks, { endDate: '2026-12-31' }, today)).toEqual({ status: 'atRisk', code: 'late', count: 3 })
	})

	it('suggests off track when the end date has passed with open tasks', () => {
		const tasks = [{ status: 'todo' }, { status: 'done' }]
		expect(suggestTime(tasks, { endDate: '2026-09-27' }, today)).toEqual({ status: 'offTrack', code: 'endPassed', count: 1 })
	})

	it('suggests on track when nothing is late', () => {
		expect(suggestTime([{ status: 'todo', dueDate: '2026-10-01' }], { endDate: '2026-12-01' }, today).status).toBe('onTrack')
		expect(suggestTime([], null, today)).toEqual({ status: 'onTrack', code: 'onSchedule', count: 0 })
	})
})

describe('suggestRisk', () => {
	it('takes the band of the highest open risk', () => {
		const risks = [
			{ title: 'Closed', score: 25, status: 'closed' },
			{ title: 'Supplier', score: 12, status: 'open' },
			{ title: 'Weather', score: 6, status: 'mitigating' },
		]
		expect(suggestRisk(risks, DEFAULT_RISK_SCALE)).toEqual({ status: 'offTrack', code: 'highestRisk', score: 12, title: 'Supplier' })
		expect(suggestRisk([{ title: 'W', score: 6, status: 'open' }], DEFAULT_RISK_SCALE).status).toBe('atRisk')
		expect(suggestRisk([{ title: 'W', score: 2, status: 'open' }], DEFAULT_RISK_SCALE).status).toBe('onTrack')
	})

	it('suggests on track without open risks', () => {
		expect(suggestRisk([{ score: 25, status: 'occurred' }], DEFAULT_RISK_SCALE)).toEqual({ status: 'onTrack', code: 'noOpenRisks' })
	})
})

describe('suggestMoney', () => {
	it('uses the named threshold: at risk past 90 percent, off track past the budget', () => {
		expect(MONEY_AT_RISK_RATIO).toBe(0.9)
		expect(suggestMoney({ budget: 1000, cost: 900 }).status).toBe('onTrack')
		expect(suggestMoney({ budget: 1000, cost: 901 })).toEqual({ status: 'atRisk', code: 'nearBudget', percent: 90 })
		expect(suggestMoney({ budget: 1000, cost: 1200 })).toEqual({ status: 'offTrack', code: 'overBudget', percent: 120 })
	})

	it('suggests nothing without a budget or without recorded costs', () => {
		expect(suggestMoney({ budget: 0, cost: 10 })).toEqual({ status: null, code: 'noBudget' })
		expect(suggestMoney({ budget: 1000, cost: null })).toEqual({ status: null, code: 'noCosts' })
	})
})

describe('worstStatus and sortReports', () => {
	it('takes the worst of the six, like the server calculation', () => {
		expect(worstStatus(['onTrack', 'atRisk', 'onTrack'])).toBe('atRisk')
		expect(worstStatus(['onTrack', 'offTrack', 'atRisk'])).toBe('offTrack')
		expect(worstStatus([])).toBe(null)
	})

	it('lists the newest report first (scenario: writing a status report)', () => {
		const reports = [
			{ id: 'a', reportDate: '2026-08-01' },
			{ id: 'b', reportDate: '2026-09-28', '@self': { created: '2026-09-28T09:00:00Z' } },
			{ id: 'c', reportDate: '2026-09-28', '@self': { created: '2026-09-28T15:00:00Z' } },
		]
		expect(sortReports(reports).map((r) => r.id)).toEqual(['c', 'b', 'a'])
	})
})

describe('reportPayload', () => {
	const schema = register.components.schemas.projectStatusReport
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

	it('builds a report the real schema accepts, without an overall of its own', () => {
		const fields = { reportDate: today }
		for (const aspect of ASPECTS) {
			fields[aspect] = { status: 'onTrack', note: '' }
		}
		fields.time = { status: 'atRisk', note: ' Permit delayed by 3 weeks ' }
		const payload = reportPayload(fields, project)
		expect(payload).toMatchObject({ project, reportDate: today, statusTime: 'atRisk', noteTime: 'Permit delayed by 3 weeks', statusMoney: 'onTrack' })
		expect(payload).not.toHaveProperty('overall')
		expect(validate(payload), JSON.stringify(validate.errors)).toBe(true)
	})

	it('a report with an aspect left open is refused by the schema', () => {
		const payload = reportPayload({ reportDate: today, money: { status: 'onTrack' } }, project)
		expect(payload).not.toHaveProperty('statusTime')
		expect(validate(payload)).toBe(false)
	})
})
